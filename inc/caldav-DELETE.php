<?php
/**
* CalDAV Server - handle DELETE method
*
* @package   davical
* @subpackage   caldav
* @author    Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Catalyst .Net Ltd, Morphoss Ltd <http://www.morphoss.com/>
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
*/
dbg_error_log("delete", "DELETE method handler");

$request->NeedPrivilege('DAV::unbind');

$lock_opener = $request->FailIfLocked();

if ( $request->IsCollection() ) {
  /**
  * We read the collection first, so we can check if it matches (or does not match)
  */
  $qry = new AwlQuery( 'SELECT * FROM collection WHERE user_no = :user_no AND dav_name = :dav_name',
              array( ':user_no' => $request->user_no, ':dav_name' => $request->path ) );
  if ( $qry->Exec('DELETE',__LINE__,__FILE__) && $qry->rows() == 1 ) {
    $delete_row = $qry->Fetch();
    if ( (isset($request->etag_if_match) && $request->etag_if_match != $delete_row->dav_etag) ) {
      $request->DoResponse( 412, translate("Resource does not match 'If-Match' header - not deleted") );
    }

    $path_like = array( ':path_like' => $request->path.'%' );
    if ( $qry->Begin()
         && $qry->QDo("SELECT write_sync_change(collection_id, 404, caldav_data.dav_name) FROM caldav_data WHERE dav_name LIKE :path_like", $path_like )
         && $qry->QDo("DELETE FROM collection WHERE dav_name = :request_path", array(':request_path' => $request->path) )
         && $qry->QDo("DELETE FROM caldav_data WHERE dav_name LIKE :path_like", $path_like )
         && $qry->QDo("DELETE FROM property WHERE dav_name LIKE :path_like", $path_like )
         && $qry->QDo("DELETE FROM locks WHERE dav_name LIKE :path_like", $path_like )
         && $qry->Commit() ) {
      @dbg_error_log( "DELETE", "DELETE (collection): User: %d, ETag: %s, Path: %s", $session->user_no, $request->etag_if_match, $request->path);
      $request->DoResponse( 204 );
    }
    else {
      $request->DoResponse( 500, translate("Error querying database.") );
    }

  }
  else {
    $request->DoResponse( 404 );
  }
}
else {
  /**
  * We read the resource first, so we can check if it matches (or does not match)
  */
  $params = array( ':dav_name' => $request->path );
  $qry = new AwlQuery( 'SELECT cd.dav_etag, ci.uid, cd.collection_id FROM caldav_data cd JOIN calendar_item ci USING (dav_id) WHERE cd.dav_name = :dav_name', $params );
  if ( $qry->Exec('DELETE',__LINE__,__FILE__) && $qry->rows() == 1 ) {
    $delete_row = $qry->Fetch();
    if ( (isset($request->etag_if_match) && $request->etag_if_match != $delete_row->dav_etag) ) {
      $request->DoResponse( 412, translate("Resource has changed on server - not deleted") );
    }

    $collection_id = $delete_row->collection_id;

    if ( $qry->Begin()
         && $qry->QDo("DELETE FROM caldav_data WHERE collection_id = $collection_id AND dav_name = :dav_name", $params )
         && $qry->QDo("SELECT write_sync_change( $collection_id, 404, :dav_name)", $params )
         && $qry->QDo("DELETE FROM property WHERE dav_name = :dav_name", $params )
         && $qry->Commit() ) {
      @dbg_error_log( "DELETE", "DELETE: User: %d, ETag: %s, Path: %s", $session->user_no, $request->etag_if_match, $request->path);
      if ( function_exists('log_caldav_action') ) {
        log_caldav_action( 'DELETE', $delete_row->uid, $request->user_no, $request->collection_id, $request->path );
      }
      $request->DoResponse( 204 );
    }
    else {
      $request->DoResponse( 500, translate("Error querying database.") );
    }
  }
  else {
    $request->DoResponse( 404 );
  }
}

