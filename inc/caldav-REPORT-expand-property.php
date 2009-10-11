<?php
$responses = array();

/**
 * Build the array of properties to include in the report output
 */
$props = $xmltree->GetPath('/DAV::expand-property/DAV::property');
$proplist = array();
foreach( $props AS $k => $v ) {
  $proplist[] = $v->GetContent();
}
function display_status( $status_code ) {
  return sprintf( 'HTTP/1.1 %03d %s', $status_code, getStatusMessage($status_code) );
}
  
$sql = "SELECT * FROM sync_changes LEFT JOIN calendar_item USING (dav_id) LEFT JOIN caldav_data USING (dav_id) WHERE sync_time > (SELECT modification_time FROM sync_tokens WHERE sync_token = ?)";
$qry = new PgQuery($sql);

if ( $qry->Exec("REPORT",__LINE__,__FILE__) && $qry->rows > 0 ) {
  while( $object = $qry->Fetch() ) {
    $href = new XMLElement( 'dav_name', ConstructURL($change->href) ); 
    $status = new XMLElement( 'status', display_status($change->status) );
    if ( $status != 404 ) {
      $propstat = $request->ObjectPropStat($proplist, $object);
    } 
    $responses[] = new XMLElement( 'sync-response', array() );
  }
}

$multistatus = new XMLElement( "multistatus", $responses, $reply->GetXmlNsArray() );

$request->XMLResponse( 207, $multistatus );
