<?php

dbg_error_log("get", "GET method handler");

// The GET method is not sent with any wrapping XML so we simply fetch it

$get_path = $_SERVER['PATH_INFO'];
$etag_none_match = str_replace('"','',$_SERVER["HTTP_IF_NONE_MATCH"]);

$qry = new PgQuery( "SELECT * FROM ics_event_data WHERE user_no = ? AND ics_event_name = ? ;", $session->user_no, $get_path);
if ( $qry->Exec("caldav-GET") && $qry->rows == 1 ) {
  $event = $qry->Fetch();

  header("HTTP/1.1 200 OK");
  header("ETag: $event->ics_event_etag");
  header("Content-Type: text/calendar");

  print $event->ics_raw_data;

  dbg_error_log( "GET", "User: %d, ETag: %s, Path: %s", $session->user_no, $event->ics_event_etag, $get_path);

}
else {
  header("HTTP/1.1 500 Infernal Server Error");
}

?>