<?php

dbg_error_log("PUT", "method handler");

// The PUT method is not sent with any wrapping XML so we simply store it
// after constructing an eTag and getting a name for it...

$fh = fopen('/tmp/PUT.txt','w');
fwrite($fh,$raw_post);
fclose($fh);

$etag = md5($raw_post);
$put_path = $_SERVER['PATH_INFO'];
$etag_none_match = str_replace('"','',$_SERVER["HTTP_IF_NONE_MATCH"]);
$etag_match = str_replace('"','',$_SERVER["HTTP_IF_MATCH"]);

dbg_log_array( "PUT", 'HEADERS', $raw_headers );
dbg_log_array( "PUT", '_SERVER', $_SERVER, true );

include_once("vEvent.php");
$ev = new vEvent(array( 'vevent' => $raw_post ));

dbg_log_array( "PUT", 'EVENT', $ev, true );


if ( $etag_match == '*' || $etag_match == '' ) {
  /**
  * If they didn't send an etag_match header, we need to check if the PUT object already exists
  * and we are hence updating it.  And we just set our etag_match to that.
  */
  $qry = new PgQuery( "SELECT * FROM vevent_data WHERE user_no=? AND vevent_name=?", $session->user_no, $put_path );
  $qry->Exec("PUT");
  if ( $qry->rows > 1 ) {
    header("HTTP/1.1 500 Infernal Server Error");
    dbg_error_log("ERROR","Multiple events match replaced path for user %d, path %s", $session->user_no, $put_path );
    exit(0);
  }
  elseif ( $qry->rows == 1 ) {
    $event = $qry->Fetch();
    $etag_match = $event->vevent_etag;
  }
}

if ( $etag_match == '*' || $etag_match == '' ) {
  /**
  * If we got this far without an etag we must be inserting it.
  */
  $qry = new PgQuery( "INSERT INTO vevent_data ( user_no, vevent_name, vevent_etag, vevent_data, logged_user ) VALUES( ?, ?, ?, ?, ?)",
                         $session->user_no, $put_path, $etag, $raw_post, $session->user_no );
  $qry->Exec("PUT");

  header("HTTP/1.1 201 Created");
  header("ETag: $etag");
  dbg_error_log( "PUT", "INSERT INTO vevent_data ( user_no, vevent_name, vevent_etag, vevent_data, logged_user ) VALUES( %d, '%s', '%s', '%s', %d)",
                         $session->user_no, $put_path, $etag, $raw_post, $session->user_no );
}
else {
  $qry = new PgQuery( "UPDATE vevent_data SET vevent_data=?, vevent_etag=?, logged_user=? WHERE user_no=? AND vevent_name=? AND vevent_etag=?",
                                                        $raw_post, $etag, $session->user_no, $session->user_no, $put_path, $etag_match );
  $qry->Exec("PUT");

  header("HTTP/1.1 201 Replaced");
  header("ETag: $etag");
}

$sql = "SET TIMEZONE TO ".qpg($ev->tz_locn).";";
if ( $etag_match == '*' || $etag_match == '' ) {
  $sql .= <<<EOSQL
INSERT INTO event (user_no, vevent_name, vevent_etag, uid, dtstamp, dtstart, dtend, summary, location, class, transp, description, rrule, tz_id)
                 VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
EOSQL;

  $qry = new PgQuery( $sql, $session->user_no, $put_path, $etag, $ev->Get('uid'), $ev->Get('dtstamp'),
                            $ev->Get('dtstart'), $ev->Get('dtend'), $ev->Get('summary'), $ev->Get('location'),
                            $ev->Get('class'), $ev->Get('transp'), $ev->Get('description'), $ev->Get('rrule'), $ev->Get('tz_id') );
  $qry->Exec("PUT");
}
else {
  $sql = <<<EOSQL
UPDATE event SET uid=?, dtstamp=?, dtstart=?, dtend=?, summary=?, location=?, class=?, transp=?, description=?, rrule=?, tz_id=?
                 WHERE user_no=? AND vevent_name=? AND vevent_etag=?
EOSQL;

  $qry = new PgQuery( $sql, $ev->Get('uid'), $ev->Get('dtstamp'), $ev->Get('dtstart'), $ev->Get('dtend'), $ev->Get('summary'),
                            $ev->Get('location'), $ev->Get('class'), $ev->Get('transp'), $ev->Get('description'), $ev->Get('rrule'),
                            $ev->Get('tz_id'), $session->user_no, $put_path, $etag );
  $qry->Exec("PUT");
}

dbg_error_log( "PUT", "User: %d, ETag: %s, Path: %s", $session->user_no, $etag, $put_path);

?>