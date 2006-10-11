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

dbg_log_array( "MKCOL", 'HEADERS', $raw_headers );
dbg_log_array( "MKCOL", '_SERVER', $_SERVER, true );
dbg_error_log( "MKCOL", "RAW: %s", str_replace("\n", "",str_replace("\r", "", $raw_post)) );

$make_path = $_SERVER['PATH_INFO'];

$displayname = $make_path;
$parent_container = '/';
if ( preg_match( '#^(.*/)([^/]+)(/)?$#', $make_path, $matches ) ) {
  $parent_container = $matches[1];
  $displayname = $matches[2];
}

$sql = "INSERT INTO collection ( user_no, parent_container, dav_name, dav_etag, dav_displayname, is_calendar, created, modified ) VALUES( ?, ?, ?, ?, ?, FALSE, current_timestamp, current_timestamp );";
$qry = new PgQuery( $sql, $session->user_no, $parent_container, $make_path, md5($session->user_no. $make_path), $displayname );

if ( $qry->Exec("MKCOL",__LINE__,__FILE__) ) {
  header("HTTP/1.1 200 Created");
  dbg_error_log( "MKCOL", "New collection '%s' created named '%s' for user '%d' in parent '%s'", $make_path, $displayname, $session->user_no, $parent_container);
}
else {
  header("HTTP/1.1 500 Infernal Server Error");
  dbg_error_log( "ERROR", " MKCOL Failed for '%s' named '%s', user '%d' in parent '%s'", $make_path, $displayname, $session->user_no, $parent_container);
}

?>