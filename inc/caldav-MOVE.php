<?php
/**
* CalDAV Server - handle MOVE method
*
* @package   davical
* @subpackage   caldav
* @author    Andrew McMillan <andrew@morphoss.com>
* @copyright Morphoss Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("MOVE", "method handler");

if ( ! $request->AllowedTo("read") ) {
  $request->DoResponse(403);
}

if ( ! ini_get('open_basedir') && (isset($c->dbg['ALL']) || (isset($c->dbg['put']) && $c->dbg['put'])) ) {
  $fh = fopen('/tmp/MOVE.txt','w');
  if ( $fh ) {
    fwrite($fh,$request->raw_post);
    fclose($fh);
  }
}

$lock_opener = $request->FailIfLocked();

if ( $request->path == '/' || $request->IsPrincipal() || $request->destination == '' ) {
  $request->DoResponse( 403 );
}

if ( !class_exists('DAVResource') ) require('DAVResource.php');
$dest = new DAVResource($request->destination);

if ( $dest->path == '/' || $dest->IsPrincipal() ) {
  $request->DoResponse( 403 );
}

if ( ! $request->overwrite && $dest->Exists() ) {
  $request->DoResponse( 412 );
}

if ( $request->IsCollection() ) {
  switch( $dest->ContainerType() ) {
    case 'calendar':
    case 'addressbook':
    case 'schedule-inbox':
    case 'schedule-outbox':
      $request->DoResponse( 412, translate('Special collections may not contain a calendar or other special collection.') );
  };
}

if ( ! $request->AllowedTo('delete') ) $request->DoResponse( 403 );
if ( ! $dest->HaveRightsTo('DAV::write') ) $request->DoResponse( 403 );
if ( ! $dest->Exists() && !$dest->HaveRightsTo('DAV::bind') ) $request->DoResponse( 403 );
// if ( ! $request->HaveRightsTo('DAV::unbind') ) $request->DoResponse( 403 );


function rollback( $response_code = 412 ) {
  $qry = new AwlQuery('ROLLBACK');
  $qry->Exec('move'); // Just in case
  $request->DoResponse( $response_code );
  // And we don't return from that.
}


$qry = new AwlQuery('BEGIN');
if ( !$qry->Exec('move') ) rollback(500);

if ( $request->IsCollection()  ) {
  /** @TODO: Need to confirm this will work correctly if we move this into another user's hierarchy. */
  $qry = new AwlQuery( 'UPDATE collection SET dav_name = :new_dav_name WHERE collection_id = :collection_id', array(
    ':new_dav_name' => $dest->dav_name(),
    ':collection_id' => $request->collection
  );
}
else {
  $qry = new AwlQuery( 'UPDATE caldav_data SET dav_name = :new_dav_name WHERE dav_name = :old_dav_name', array(
    ':old_dav_name' => $request->dav_name(),
    ':new_dav_name' => $dest->dav_name()
  );
}

$qry = new PgQuery('COMMIT');
if ( !$qry->Exec('move') ) rollback(500);

$request->DoResponse( ($put_action_type == 'INSERT' ? 201 : 204) );
