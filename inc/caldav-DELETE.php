<?php

dbg_error_log("delete", "DELETE method handler");

// The DELETE method is not sent with any wrapping XML so we simply delete it

$get_path = $_SERVER['PATH_INFO'];
$etag_none_match = str_replace('"','',$_SERVER["HTTP_IF_NONE_MATCH"]);

if ( $etag_none_match != '' ) {
  /**
  * etag_none_match is saying that we should only delete a row if it matches this etag
  * (only rows not matching should exist afterwards, I guess.)
  */
  $only_this_etag = " AND dav_etag = ".qpg($etag_none_match);
}

$qry = new PgQuery( "SELECT * FROM caldav_data WHERE user_no = ? AND dav_name = ? $only_this_etag;", $session->user_no, $get_path );
if ( $qry->Exec("caldav-DELETE") && $qry->rows == 1 ) {
  $qry = new PgQuery( "DELETE FROM caldav_data WHERE user_no = ? AND dav_name = ? $only_this_etag;", $session->user_no, $get_path );
  if ( $qry->Exec("caldav-DELETE") ) {
    header("HTTP/1.1 200 OK");
    dbg_error_log( "delete", "DELETE: User: %d, ETag: %s, Path: %s", $session->user_no, $etag_none_match, $get_path);
  }
  else {
    header("HTTP/1.1 500 Infernal Server Error");
    dbg_error_log( "delete", "DELETE failed: User: %d, ETag: %s, Path: %s, SQL: %s", $session->user_no, $etag_none_match, $get_path, $qry->querystring);
  }
}
else {
  header("HTTP/1.1 404 Not Found");
  dbg_error_log( "delete", "DELETE row not found: User: %d, ETag: %s, Path: %s", $qry->rows, $session->user_no, $etag_none_match, $get_path);
}

?>