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

if ( !isset($request->etag_if_match) && isset($request->etag_none_match) && isset($_SERVER['HTTP_USER_AGENT'])
          && preg_match('#Evolution/([0-9]+[.][0-9]+)#', $_SERVER['HTTP_USER_AGENT'], $matches ) ) {
  if ( doubleval($matches[1]) <= 1.9 ) {
    $request->etag_if_match = $request->etag_none_match;
    unset($request->etag_none_match);
  }
}


if ( ! $request->AllowedTo('delete') ) {
  $request->DoResponse( 403, translate("You may not delete entries from this calendar.") );
}

/**
* We read the resource first, so we can check if it matches (or does not match)
*/
$qry = new PgQuery( "SELECT * FROM caldav_data WHERE user_no = ? AND dav_name = ?;", (isset($request->user_no)?$request->user_no:$session->user_no), $request->path );
if ( $qry->Exec("DELETE") && $qry->rows == 1 ) {
  $delete_row = $qry->Fetch();
  if ( (isset($request->etag_if_match) && $request->etag_if_match != $delete_row->dav_etag) ) {
    $request->DoResponse( 412, translate("Resource does not match 'If-Match' header - not deleted") );
  }
  $qry = new PgQuery( "DELETE FROM caldav_data WHERE user_no = ? AND dav_name = ?;", (isset($request->user_no)?$request->user_no:$session->user_no), $request->path );
  if ( $qry->Exec("DELETE") ) {
    @dbg_error_log( "DELETE", "DELETE: User: %d, ETag: %s, Path: %s", $session->user_no, $request->etag_if_match, $request->path);
    $request->DoResponse( 200, "" );
  }
  else {
    $request->DoResponse( 500, translate("Error querying database.") );
  }
}
else {
    $request->DoResponse( 404, "");
}

?>