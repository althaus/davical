<?php
/**
* CalDAV Server - handle PUT method
*
* @package   rscds
* @subpackage   caldav
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("PUT", "method handler");

// The PUT method is not sent with any wrapping XML so we simply store it
// after constructing an eTag and getting a name for it...

$fh = fopen('/tmp/PUT.txt','w');
fwrite($fh,$raw_post);
fclose($fh);

$etag = md5($raw_post);
if ( preg_match('#Evolution/([0-9.]+)#', $_SERVER['HTTP_USER_AGENT'], $matches ) ) {
  /**
  * Evolution can't handle an ETag that doesn't change, so we give it a fake one
  * first for the PUT reply and it'll figure it out in due course.  Sad but true.
  *         - See http://bugzilla.gnome.org/show_bug.cgi?id=355659
  */
  $bogus_etag = "BogusEvolutionETagOnPUT".rand(7,2139876547);
}

include_once("iCalendar.php");
$ic = new iCalendar(array( 'icalendar' => $raw_post ));

dbg_log_array( "PUT", 'EVENT', $ic->properties['VCALENDAR'][0], true );


/**
* We read any existing object so we can check the ETag.
*/
unset($put_action_type);
$qry = new PgQuery( "SELECT * FROM caldav_data WHERE user_no=? AND dav_name=?", $session->user_no, $request_path );
if ( !$qry->Exec("PUT") || $qry->rows > 1 ) {
  header("HTTP/1.1 500 Infernal Server Error");
  dbg_error_log("ERROR","Query failure, or multiple events match replaced path for user %d, path %s", $session->user_no, $request_path );
  exit(0);
}
elseif ( $qry->rows < 1 ) {
  if ( isset($etag_if_match) && $etag_if_match != '' ) {
    /**
    * RFC2068, 14.25:
    * If none of the entity tags match, or if "*" is given and no current
    * entity exists, the server MUST NOT perform the requested method, and
    * MUST return a 412 (Precondition Failed) response.
    */
    header("HTTP/1.1 412 Precondition Failed");
    header("Content-type: text/plain");
    header("Content-length: 0");
    if ( isset($etag_if_match) && $etag_if_match != $delete_row->dav_etag ) {
      echo "No existing resource matching 'If-Match' header - not accepted.\n";
    }
    exit(0);
  }
  $put_action_type = 'INSERT';
}
elseif ( $qry->rows == 1 ) {
  $icalendar = $qry->Fetch();

  if ( ( isset($etag_if_match) && $etag_if_match != '' && $etag_if_match != $icalendar->dav_etag )
       || ( isset($etag_none_match) && $etag_none_match != '' && ($etag_none_match == $icalendar->dav_etag || $etag_none_match == '*') ) ) {
    /**
    * RFC2068, 14.25:
    * If none of the entity tags match, or if "*" is given and no current
    * entity exists, the server MUST NOT perform the requested method, and
    * MUST return a 412 (Precondition Failed) response.
    *
    * RFC2068, 14.26:
    * If any of the entity tags match the entity tag of the entity that
    * would have been returned in the response to a similar GET request
    * (without the If-None-Match header) on that resource, or if "*" is
    * given and any current entity exists for that resource, then the
    * server MUST NOT perform the requested method.
    */
    header("HTTP/1.1 412 Precondition Failed");
    header("Content-type: text/plain");
    header("Content-length: 0");
    if ( isset($etag_if_match) && $etag_if_match != $icalendar->dav_etag ) {
      echo "Existing resource does not match 'If-Match' header - not accepted.\n";
    }
    if ( isset($etag_none_match) && $etag_none_match != '' && ($etag_none_match == $icalendar->dav_etag || $etag_none_match == '*') ) {
      echo "Existing resource matches 'If-None-Match' header - not accepted.\n";
    }
    exit(0);
  }

  $put_action_type = 'UPDATE';
}

if ( $put_action_type == 'INSERT' ) {
  $qry = new PgQuery( "INSERT INTO caldav_data ( user_no, dav_name, dav_etag, caldav_data, caldav_type, logged_user, created, modified ) VALUES( ?, ?, ?, ?, ?, ?, current_timestamp, current_timestamp )",
                         $session->user_no, $request_path, $etag, $raw_post, $ic->type, $session->user_no );
  $qry->Exec("PUT");

  header("HTTP/1.1 201 Created", true, 201);
}
else {
  $qry = new PgQuery( "UPDATE caldav_data SET caldav_data=?, dav_etag=?, caldav_type=?, logged_user=?, modified=current_timestamp WHERE user_no=? AND dav_name=? AND dav_etag=?",
                         $raw_post, $etag, $ic->type, $session->user_no, $session->user_no, $request_path, $etag_if_match );
  $qry->Exec("PUT");

  header("HTTP/1.1 201 Replaced", true, 201);
}

header(sprintf('ETag: "%s"', (isset($bogus_etag) ? $bogus_etag : $etag) ) );
header("Content-length: 0");

$sql = ( $ic->tz_locn == '' ? '' : "SET TIMEZONE TO ".qpg($ic->tz_locn).";" );

$dtstart = $ic->Get('dtstart');
if ( (!isset($dtstart) || $dtstart == "") && $ic->Get('due') != "" ) {
  $dtstart = $ic->Get('due');
}
$dtend = $ic->Get('dtend');
if ( (!isset($dtend) || "$dtend" == "") && $ic->Get('duration') != "" AND $dtstart != "" ) {
  $duration = preg_replace( '#[PT]#', ' ', $ic->Get('duration') );
  $dtend = '('.qpg($dtstart).'::timestamp with time zone + '.qpg($duration).'::interval)';
}
else {
  dbg_error_log( "PUT", " DTEND: '%s', DTSTART: '%s', DURATION: '%s'", $dtend, $dtstart, $ic->Get('duration') );
  $dtend = qpg($dtend);
}



if ( $put_action_type == 'INSERT' ) {
  $sql .= <<<EOSQL
INSERT INTO calendar_item (user_no, dav_name, dav_etag, uid, dtstamp, dtstart, dtend, summary, location, class, transp,
                    description, rrule, tz_id, last_modified, url, priority, created, due, percent_complete )
                 VALUES ( ?, ?, ?, ?, ?, ?, $dtend, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
EOSQL;

  $qry = new PgQuery( $sql, $session->user_no, $request_path, $etag, $ic->Get('uid'), $ic->Get('dtstamp'),
                            $ic->Get('dtstart'), $ic->Get('summary'), $ic->Get('location'),
                            $ic->Get('class'), $ic->Get('transp'), $ic->Get('description'), $ic->Get('rrule'), $ic->Get('tz_id'),
                            $ic->Get('last-modified'), $ic->Get('url'), $ic->Get('priority'), $ic->Get('created'),
                            $ic->Get('due'), $ic->Get('percent-complete')
                     );
  $qry->Exec("PUT");
}
else {
  $sql = <<<EOSQL
UPDATE calendar_item SET uid=?, dtstamp=?, dtstart=?, dtend=$dtend, summary=?, location=?, class=?, transp=?, description=?, rrule=?,
                  tz_id=?, last_modified=?, url=?, priority=?, dav_etag=?, due=?, percent_complete=?
                 WHERE user_no=? AND dav_name=?
EOSQL;

  $qry = new PgQuery( $sql, $ic->Get('uid'), $ic->Get('dtstamp'), $ic->Get('dtstart'), $ic->Get('summary'),
                            $ic->Get('location'), $ic->Get('class'), $ic->Get('transp'), $ic->Get('description'), $ic->Get('rrule'),
                            $ic->Get('tz_id'), $ic->Get('last-modified'), $ic->Get('url'), $ic->Get('priority'), $etag,
                            $ic->Get('due'), $ic->Get('percent-complete'),
                            $session->user_no, $request_path );
  $qry->Exec("PUT");
}

dbg_error_log( "PUT", "User: %d, ETag: %s, Path: %s", $session->user_no, $etag, $request_path);

// Just ensure we exit without sending anything more.
exit(0);
?>