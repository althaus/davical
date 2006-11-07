<?php
/**
* CalDAV Server - handle MKCOL method
*
* @package   rscds
* @subpackage   caldav
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("MKCOL", "method handler");

if ( ! isset($permissions['write']) ) {
  header("HTTP/1.1 403 Forbidden");
  header("Content-type: text/plain");
  echo "You may not create a calendar there.";
  dbg_error_log("ERROR", "MKCOL Access denied for User: %d, Path: %s", $session->user_no, $get_path);
  return;
}

$displayname = $request_path;
$parent_container = '/';
if ( preg_match( '#^(.*/)([^/]+)(/)?$#', $request_path, $matches ) ) {
  $parent_container = $matches[1];
  $displayname = $matches[2];
}

$sql = "SELECT * FROM collection WHERE user_no = ? AND dav_name = ?;";
$qry = new PgQuery( $sql, $session->user_no, $request_path );
if ( ! $qry->Exec("MKCOL") ) {
  header("HTTP/1.1 500 Infernal Server Error");
  dbg_error_log( "ERROR", " MKCOL Failed (database error) for '%s' named '%s', user '%d' in parent '%s'", $request_path, $displayname, $session->user_no, $parent_container);
  exit(0);
}
if ( $qry->rows != 0 ) {
  header("HTTP/1.1 412 Collection Already Exists");
  dbg_error_log( "ERROR", " MKCOL Failed (already exists) for '%s' named '%s', user '%d' in parent '%s'", $request_path, $displayname, $session->user_no, $parent_container);
  exit(0);
}

$sql = "INSERT INTO collection ( user_no, parent_container, dav_name, dav_etag, dav_displayname, is_calendar, created, modified ) VALUES( ?, ?, ?, ?, ?, FALSE, current_timestamp, current_timestamp );";
$qry = new PgQuery( $sql, $session->user_no, $parent_container, $request_path, md5($session->user_no. $request_path), $displayname );

if ( $qry->Exec("MKCOL",__LINE__,__FILE__) ) {
  header("HTTP/1.1 200 Created");
  dbg_error_log( "MKCOL", "New collection '%s' created named '%s' for user '%d' in parent '%s'", $request_path, $displayname, $session->user_no, $parent_container);
}
else {
  header("HTTP/1.1 500 Infernal Server Error");
  dbg_error_log( "ERROR", " MKCOL Failed for '%s' named '%s', user '%d' in parent '%s'", $request_path, $displayname, $session->user_no, $parent_container);
}

?>