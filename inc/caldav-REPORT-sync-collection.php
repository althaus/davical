<?php
require("DAVResource.php");
$responses = array();

/**
 * Build the array of properties to include in the report output
 */
$sync_tokens = $xmltree->GetPath('/DAV::sync-collection/DAV::sync-token');
$sync_token = $sync_tokens[0]->GetContent();
if ( !isset($sync_token) ) $sync_token = 0;
$sync_token = intval($sync_token);
dbg_error_log( 'sync', " sync-token: %s", $sync_token );


$props = $xmltree->GetElements('DAV::prop');
$v = $props[0];
$props = $v->GetContent();
$proplist = array();
foreach( $props AS $k => $v ) {
  $proplist[] = $v->GetTag();
}

function display_status( $status_code ) {
  return sprintf( 'HTTP/1.1 %03d %s', intval($status_code), getStatusMessage($status_code) );
}
  
$sql = "SELECT new_sync_token(?,?)";
$qry = new PgQuery($sql, $sync_token, $request->CollectionId());
if ( !$qry->Exec("REPORT",__LINE__,__FILE__) || $qry->rows <= 0 ) {
  $request->DoResponse( 500, translate("Database error") );
}
$row = $qry->Fetch();
$new_token = $row->new_sync_token;

if ( $sync_token == 0 ) {
  $sql = <<<EOSQL
SELECT *, 201 AS sync_status FROM collection
            LEFT JOIN caldav_data USING (collection_id)
            LEFT JOIN calendar_item USING (dav_id)
     WHERE collection.collection_id = ?
EOSQL;
  $qry = new PgQuery($sql, $request->CollectionId());
}
else {
  $sql = <<<EOSQL
SELECT * FROM collection LEFT JOIN sync_changes USING(collection_id)
                         LEFT JOIN calendar_item USING (dav_id)
                         LEFT JOIN caldav_data USING (dav_id)
     WHERE collection.collection_id = ?
      AND sync_time > (SELECT modification_time FROM sync_tokens WHERE sync_token = ?)
EOSQL;
  $qry = new PgQuery($sql, $request->CollectionId(), $sync_token);
}

if ( $qry->Exec("REPORT",__LINE__,__FILE__) ) {
  while( $object = $qry->Fetch() ) {
    $resultset = array(
      new XMLElement( 'href', ConstructURL($object->dav_name) ), 
      new XMLElement( 'status', display_status($object->sync_status) )
    );
    if ( $status != 404 ) {
      $dav_resource = new DAVResource($object);
      $resultset = array_merge( $resultset, $dav_resource->GetPropStat($proplist) );
    } 
    $responses[] = new XMLElement( 'sync-response', $resultset );
  }
  $responses[] = new XMLElement( 'sync-token', $new_token );
}
else {
  $request->DoResponse( 500, translate("Database error") );
}

$multistatus = new XMLElement( "multistatus", $responses, $reply->GetXmlNsArray() );

$request->XMLResponse( 207, $multistatus );
