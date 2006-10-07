<?php

dbg_error_log("PUT", "method handler");

// The PUT method is not sent with any wrapping XML so we simply store it
// after constructing an eTag and getting a name for it...

$fh = fopen('/tmp/PUT.txt','w');
fwrite($fh,$raw_post);
fclose($fh);

$etag = md5($raw_post);
if ( isset($_SERVER["HTTP_IF_MATCH"]) ) $etag_match = str_replace('"','',$_SERVER["HTTP_IF_MATCH"]);
if ( isset($_SERVER["HTTP_IF_NONE_MATCH"]) ) $etag_none_match = str_replace('"','',$_SERVER["HTTP_IF_NONE_MATCH"]);

include_once("iCalendar.php");
$ic = new iCalendar(array( 'icalendar' => $raw_post ));

dbg_log_array( "PUT", 'EVENT', $ic->properties['VCALENDAR'][0], true );


if ( !isset($etag_match) || $etag_match == '*' || $etag_match == '' ) {
  /**
  * If they didn't send an etag_match header, we need to check if the PUT object already exists
  * and we are hence updating it.  And we just set our etag_match to that.
  */
  $qry = new PgQuery( "SELECT * FROM caldav_data WHERE user_no=? AND dav_name=?", $session->user_no, $request_path );
  $qry->Exec("PUT");
  if ( $qry->rows > 1 ) {
    header("HTTP/1.1 500 Infernal Server Error");
    dbg_error_log("ERROR","Multiple events match replaced path for user %d, path %s", $session->user_no, $request_path );
    exit(0);
  }
  elseif ( $qry->rows == 1 ) {
    $icalendar = $qry->Fetch();
    $etag_match = $icalendar->dav_etag;
  }
}

if ( !isset($etag_match) || $etag_match == '*' || $etag_match == '' ) {
  /**
  * If we got this far without an etag we must be inserting it.
  */
  $qry = new PgQuery( "INSERT INTO caldav_data ( user_no, dav_name, dav_etag, caldav_data, caldav_type, logged_user, created, modified ) VALUES( ?, ?, ?, ?, ?, ?, current_timestamp, current_timestamp )",
                         $session->user_no, $request_path, $etag, $raw_post, $ic->type, $session->user_no );
  $qry->Exec("PUT");

  header("HTTP/1.1 201 Created");
  header("ETag: $etag");
}
else {
  $qry = new PgQuery( "UPDATE caldav_data SET caldav_data=?, dav_etag=?, caldav_type=?, logged_user=?, modified=current_timestamp WHERE user_no=? AND dav_name=? AND dav_etag=?",
                                              $raw_post, $etag, $ic->type, $session->user_no, $session->user_no, $request_path, $etag_match );
  $qry->Exec("PUT");

  header("HTTP/1.1 201 Replaced");
  header("ETag: $etag");
}

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



if ( !isset($etag_match) || $etag_match == '*' || $etag_match == '' ) {
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

?>