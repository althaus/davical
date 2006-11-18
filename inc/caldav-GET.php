<?php
/**
* CalDAV Server - handle GET method
*
* @package   rscds
* @subpackage   caldav
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("get", "GET method handler");

if ( ! isset($permissions['read']) ) {
  header("HTTP/1.1 403 Forbidden");
  header("Content-type: text/plain");
  echo "You may not access that calendar.";
  dbg_error_log("GET", "Access denied for User: %d, Path: %s", $session->user_no, $request_path);
  return;
}

$qry = new PgQuery( "SELECT * FROM caldav_data WHERE user_no = ? AND dav_name = ? ;", $path_user_no, $request_path);
dbg_error_log("get", "%s", $qry->querystring );
if ( $qry->Exec("GET") && $qry->rows == 1 ) {
  $event = $qry->Fetch();

  /**
  * FIXME: There may be some circumstances where someone wants to send
  * an If-Match or If-None-Match header.  I'm not sure what that means here,
  * so we will leave it unimplemented at this point.
  */

  header("HTTP/1.1 200 OK");
  header("ETag: \"$event->dav_etag\"");
  header("Content-type: text/calendar");

  if ( $request_method != "HEAD" )
    echo $event->caldav_data;

  dbg_error_log( "GET", "User: %d, ETag: %s, Path: %s", $session->user_no, $event->dav_etag, $get_path);

}
else if ( $qry->rows < 1 ) {
  header("HTTP/1.1 404 Not Found");
  header("Content-type: text/plain");
  echo "Calendar Resource Not Found.";
  dbg_error_log("ERROR", "No rows match for User: %d, ETag: %s, Path: %s", $session->user_no, $event->dav_etag, $get_path);
}
else if ( $qry->rows > 1 ) {
  header("HTTP/1.1 500 Internal Server Error");
  header("Content-type: text/plain");
  echo "Database Error - Multiple Rows Match";
  dbg_error_log("ERROR", "Multiple rows match for User: %d, ETag: %s, Path: %s", $session->user_no, $event->dav_etag, $get_path);
}
else {
  header("HTTP/1.1 500 Infernal Server Error");
  header("Content-type: text/plain");
  echo "Database Error";
  dbg_error_log("get", "Database Error");
}

?>