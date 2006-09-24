<?php

dbg_error_log("get", "GET method handler");

// The GET method is not sent with any wrapping XML so we simply fetch it

$get_path = $_SERVER['PATH_INFO'];
$etag_none_match = str_replace('"','',$_SERVER["HTTP_IF_NONE_MATCH"]);

$qry = new PgQuery( "SELECT * FROM caldav_data WHERE user_no = ? AND dav_name = ? ;", $session->user_no, $get_path);
dbg_error_log("get", "%s", $qry->querystring );
if ( $qry->Exec("GET") && $qry->rows == 1 ) {
  $event = $qry->Fetch();

  header("HTTP/1.1 200 OK");
  header("ETag: $event->dav_etag");
  header("Content-Type: text/calendar");

  print $event->caldav_data;

  dbg_error_log( "GET", "User: %d, ETag: %s, Path: %s", $session->user_no, $event->dav_etag, $get_path);

}
else if ( $qry->rows != 1 ) {
  header("HTTP/1.1 500 Internal Server Error");
  dbg_error_log("ERROR", "Multiple rows match for User: %d, ETag: %s, Path: %s", $session->user_no, $event->dav_etag, $get_path);
}
else {
  header("HTTP/1.1 500 Infernal Server Error");
  dbg_error_log("get", "Infernal Server Error");
}

?>