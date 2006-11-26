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
fwrite($fh,$request->raw_post);
fclose($fh);

$etag = md5($request->raw_post);

include_once("iCalendar.php");
$ic = new iCalendar(array( 'icalendar' => $request->raw_post ));

dbg_log_array( "PUT", 'EVENT', $ic->properties['VCALENDAR'][0], true );

if ( isset($request_container) ) unset($request_container);
if ( isset($request_name) ) unset($request_name);
if ( preg_match( '#^(.*/)([^/]+)$#', $request->path, $matches ) ) {
  $request_container = $matches[1];
  $request_name = $matches[2];
}
else {
  $request->DoResponse( 406, translate("You may not PUT a collection - you may only PUT things *in* one or MKCOL/MKCALENDAR to create one."));
}

/**
* Before we write the event, we check the container exists, creating it if it doesn't
*/
if ( $request_container == "/$path_username/" ) {
  dbg_error_log( "WARN", " Storing events directly in user's base folders is not recommended!");
}
else {
  $sql = "SELECT * FROM collection WHERE user_no = ? AND dav_name = ?;";
  $qry = new PgQuery( $sql, $request->user_no, $request_container );
  if ( ! $qry->Exec("PUT") ) {
    $request->DoResponse( 500, translate("Error querying database.") );
  }
  if ( $qry->rows == 0 ) {
    if ( preg_match( '#^(.*/)([^/]+/)$#', $request_container, $matches ) ) {
      $parent_container = $matches[1];
      $displayname = $matches[2];
    }
    $sql = "INSERT INTO collection ( user_no, parent_container, dav_name, dav_etag, dav_displayname, is_calendar, created, modified ) VALUES( ?, ?, ?, ?, ?, TRUE, current_timestamp, current_timestamp );";
    $qry = new PgQuery( $sql, $request->user_no, $parent_container, $request_container, md5($request->user_no. $request_container), $displayname );
    $qry->Exec("PUT");
  }
}


/**
* We read any existing object so we can check the ETag.
*/
unset($put_action_type);
$qry = new PgQuery( "SELECT * FROM caldav_data WHERE user_no=? AND dav_name=?", $request->user_no, $request->path );
if ( !$qry->Exec("PUT") || $qry->rows > 1 ) {
  $request->DoResponse( 500, translate("Error querying database.") );
}
elseif ( $qry->rows < 1 ) {
  if ( isset($etag_if_match) && $etag_if_match != '' ) {
    /**
    * RFC2068, 14.25:
    * If none of the entity tags match, or if "*" is given and no current
    * entity exists, the server MUST NOT perform the requested method, and
    * MUST return a 412 (Precondition Failed) response.
    */
    $request->DoResponse( 412, translate("No existing resource matching 'If-Match' header - not accepted.") );
  }

  $put_action_type = 'INSERT';

  if ( ! $request->AllowedTo("create") ) {
    $request->DoResponse( 403, translate("You may not add entries to this calendar.") );
  }
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
    if ( isset($etag_if_match) && $etag_if_match != $icalendar->dav_etag ) {
      $error = translate( "Existing resource does not match 'If-Match' header - not accepted.");
    }
    if ( isset($etag_none_match) && $etag_none_match != '' && ($etag_none_match == $icalendar->dav_etag || $etag_none_match == '*') ) {
      $error = translate( "Existing resource matches 'If-None-Match' header - not accepted.");
    }
    $request->DoResponse( 412, $error );
  }

  $put_action_type = 'UPDATE';

  if ( ! $request->AllowedTo("modify") ) {
    $request->DoResponse( 403, translate("You may not modify entries on this calendar.") );
  }
}

if ( $put_action_type == 'INSERT' ) {
  $qry = new PgQuery( "INSERT INTO caldav_data ( user_no, dav_name, dav_etag, caldav_data, caldav_type, logged_user, created, modified ) VALUES( ?, ?, ?, ?, ?, ?, current_timestamp, current_timestamp )",
                         $request->user_no, $request->path, $etag, $request->raw_post, $ic->type, $session->user_no );
  $qry->Exec("PUT");
}
else {
  $qry = new PgQuery( "UPDATE caldav_data SET caldav_data=?, dav_etag=?, caldav_type=?, logged_user=?, modified=current_timestamp WHERE user_no=? AND dav_name=?",
                         $request->raw_post, $etag, $ic->type, $session->user_no, $request->user_no, $request->path );
  $qry->Exec("PUT");
}

$sql = "BEGIN;".( $ic->tz_locn == '' ? '' : "SET TIMEZONE TO ".qpg($ic->tz_locn).";" );

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

$last_modified = $ic->Get("last-modified");
if ( !isset($last_modified) || $last_modified == '' ) {
  $last_modified = gmdate( 'Ymd\THis\Z' );
}

$dtstamp = $ic->Get("dtstamp");
if ( !isset($dtstamp) || $dtstamp == '' ) {
  $dtstamp = $last_modified;
}

if ( $put_action_type != 'INSERT' ) {
  $sql .= "DELETE FROM calendar_item WHERE user_no=$request->user_no AND dav_name=".qpg($request->path).";";
}
$sql .= <<<EOSQL
INSERT INTO calendar_item (user_no, dav_name, dav_etag, uid, dtstamp, dtstart, dtend, summary, location, class, transp,
                    description, rrule, tz_id, last_modified, url, priority, created, due, percent_complete )
                 VALUES ( ?, ?, ?, ?, ?, ?, $dtend, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
COMMIT;
EOSQL;

$qry = new PgQuery( $sql, $request->user_no, $request->path, $etag, $ic->Get('uid'), $dtstamp,
                          $ic->Get('dtstart'), $ic->Get('summary'), $ic->Get('location'),
                          $ic->Get('class'), $ic->Get('transp'), $ic->Get('description'), $ic->Get('rrule'), $ic->Get('tz_id'),
                          $last_modified, $ic->Get('url'), $ic->Get('priority'), $ic->Get('created'),
                          $ic->Get('due'), $ic->Get('percent-complete')
                    );
$qry->Exec("PUT");
dbg_error_log( "PUT", "User: %d, ETag: %s, Path: %s", $session->user_no, $etag, $request->path);

header(sprintf('ETag: "%s"', (isset($bogus_etag) ? $bogus_etag : $etag) ) );
$request->DoResponse( 201, "" );
?>