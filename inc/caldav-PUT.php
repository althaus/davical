<?php

dbg_error_log("PUT method handler");

// The PUT method is not sent with any wrapping XML so we simply store it
// after constructing an eTag and getting a name for it...

$fh = fopen('/tmp/PUT.txt','w');
fwrite($fh,$raw_post);
fclose($fh);

$etag = md5($raw_post);
$put_path = $_SERVER['PATH_INFO'];
$etag_none_match = str_replace('"','',$_SERVER["HTTP_IF_NONE_MATCH"]);
$etag_match = str_replace('"','',$_SERVER["HTTP_IF_MATCH"]);

dbg_log_array( 'HEADERS', $raw_headers );
dbg_log_array( '_SERVER', $_SERVER, true );

if ( $etag_match == '*' || $etag_match == '' ) {
  $qry = new PgQuery( "INSERT INTO ics_event_data ( user_no, ics_event_name, ics_event_etag, ics_raw_data ) VALUES( ?, ?, ?, ?)", $session->user_no, $put_path, $etag, $raw_post);
  $qry->Exec("caldav-PUT");

  header("HTTP/1.1 201 Created");
  header("ETag: $etag");
}
else {
  $qry = new PgQuery( "UPDATE ics_event_data SET ics_raw_data=?, ics_event_etag=? WHERE user_no=? AND ics_event_name=? AND ics_event_etag=?",
                                                        $raw_post, $etag, $session->user_no, $put_path, $etag_match );
  $qry->Exec("caldav-PUT");

  header("HTTP/1.1 201 Replaced");
  header("ETag: $etag");
}

dbg_error_log( "PUT: User: %d, ETag: %s, Path: %s", $session->user_no, $etag, $put_path);

?>