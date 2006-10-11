<?php
/**
* CalDAV Server - handle DELETE method
*
* @package   rscds
* @subpackage   caldav
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("delete", "DELETE method handler");

/**
* etag_none_match, if set, is telling us only to DELETE if it fails to match.  Likewise etag_if_match
* is telling us only to DELETE if it successfully matches the ETag.  Se Evolution's Bugzilla for the
* truth about Evolution's broken handling of this: http://bugzilla.gnome.org/show_bug.cgi?id=349573
*/

if ( !isset($etag_if_match) && isset($etag_none_match) && preg_match('#Evolution/([0-9.]+)#', $_SERVER['HTTP_USER_AGENT'], $matches ) ) {
  if ( doubleval($matches[1]) <= 1.8 ) {
    $etag_if_match = $etag_none_match;
    unset($etag_none_match);
  }
}


if ( !isset($permissions['write']) ) {
    header("HTTP/1.1 403 Forbidden");
    header("Content-type: text/plain");
    if ( isset($etag_none_match) && $etag_none_match == $delete_row->dav_etag ) {
      echo "Permission denied";
    }
  exit(0);
}

/**
* Wr read the resource first, so we can check if it matches (or does not match)
*/
$qry = new PgQuery( "SELECT * FROM caldav_data WHERE user_no = ? AND dav_name = ?;", (isset($path_user_no)?$path_user_no:$session->user_no), $request_path );
if ( $qry->Exec("DELETE") && $qry->rows == 1 ) {
  $delete_row = $qry->Fetch();
  if ( (isset($etag_none_match) && $etag_none_match == $delete_row->dav_etag) || (isset($etag_if_match) && $etag_if_match != $delete_row->dav_etag) ) {
    header("HTTP/1.1 412 Precondition Failed");
    header("Content-type: text/plain");
    if ( isset($etag_none_match) && $etag_none_match == $delete_row->dav_etag ) {
      echo "Resource matches 'If-None-Match' header - not deleted\n";
    }
    if ( isset($etag_if_match) && $etag_if_match != $delete_row->dav_etag ) {
      echo "Resource does not match 'If-Match' header - not deleted\n";
    }
    exit(0);
  }
  $qry = new PgQuery( "DELETE FROM caldav_data WHERE user_no = ? AND dav_name = ? $only_this_etag;", $session->user_no, $request_path );
  if ( $qry->Exec("DELETE") ) {
    header("HTTP/1.1 200 Deleted", true, 200);
    header("Content-length: 0");
    dbg_error_log( "DELETE", "DELETE: User: %d, ETag: %s, Path: %s", $session->user_no, $etag_none_match, $request_path);
  }
  else {
    header("HTTP/1.1 500 Infernal Server Error");
    dbg_error_log( "DELETE", "DELETE failed: User: %d, ETag: %s, Path: %s, SQL: %s", $session->user_no, $etag_none_match, $request_path, $qry->querystring);
  }
}
else {
  header("HTTP/1.1 404 Not Found");
  dbg_error_log( "DELETE", "DELETE row not found: User: %d, ETag: %s, Path: %s", $qry->rows, $session->user_no, $etag_none_match, $request_path);
}

?>