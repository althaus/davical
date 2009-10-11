<?php
$responses = array();

/**
 * Build the array of properties to include in the report output
 */
$sync_tokens = $xmltree->GetPath('/DAV::sync-collection/DAV::sync-token');
$sync_token = $sync_tokens[0]->GetContent();
dbg_error_log( 'sync', " sync-token: %s", $sync_token );

$props = $xmltree->GetPath('/DAV::sync-collection/DAV::prop');
$proplist = array();
foreach( $props AS $k => $v ) {
  $proplist[] = $v->GetContent();
}
function display_status( $status_code ) {
  return sprintf( 'HTTP/1.1 %03d %s', $status_code, getStatusMessage($status_code) );
}
  
$sql = "SELECT * FROM sync_changes WHERE sync_time > (SELECT modification_time FROM sync_tokens WHERE sync_token = ?)";
$qry = new PgQuery($sql);

if ( $qry->Exec("REPORT",__LINE__,__FILE__) && $qry->rows > 0 ) {
  while( $change = $qry->Fetch() ) {
    $href = new XMLElement( 'href', $change->href ); 
    $status = new XMLElement( 'status', display_status($change->status) );
    $propstat = $request->GetPropStat($proplist); 
    $responses[] = new XMLElement( 'sync-response', array() );
  }
}

$multistatus = new XMLElement( "multistatus", $responses, $reply->GetXmlNsArray() );

$request->XMLResponse( 207, $multistatus );
