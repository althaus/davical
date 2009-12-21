<?php

/**
* @todo Tidy up namespace handling in the responses.
*/

$responses = array();

/**
 * Build the array of properties to include in the report output
 */
$mg_content = $xmltree->GetContent('urn:ietf:params:xml:ns:caldav:calendar-multiget');
$proptype = $mg_content[0]->GetTag();
$properties = array();
switch( $proptype ) {
  case 'DAV::prop':
    $mg_props = $xmltree->GetPath('/urn:ietf:params:xml:ns:caldav:calendar-multiget/DAV::prop/*');
    foreach( $mg_props AS $k => $v ) {
      $propertyname = preg_replace( '/^.*:/', '', $v->GetTag() );
      $properties[$propertyname] = 1;
    }
    break;

  case 'DAV::allprop':
    $properties['allprop'] = 1;
    break;

  default:
    $propertyname = preg_replace( '/^.*:/', '', $proptype );
    $properties[$propertyname] = 1;
}

/**
 * Build the href list for the IN ( href, href, href, ... ) clause.
 */
$mg_hrefs = $xmltree->GetPath('/urn:ietf:params:xml:ns:caldav:calendar-multiget/DAV::href');
$href_in = '';
foreach( $mg_hrefs AS $k => $v ) {
  /**
   * We need this to work if they specified a relative *or* a full path, so we strip off
   * anything up to the matching request->path (which will include any http...) and then
   * put the $request->path back on.
   */
  $href = $request->path . preg_replace( "#^.*$request->path#", '', rawurldecode($v->GetContent()) );
  dbg_error_log("REPORT", "Reporting on href '%s'", $href );
  $href_in .= ($href_in == '' ? '' : ', ');
  $href_in .= qpg($href);
}

$where = " WHERE caldav_data.dav_name ~ ".qpg("^".$request->path)." ";
$where .= "AND caldav_data.dav_name IN ( $href_in ) ";
$where .= "AND (calendar_item.class != 'PRIVATE' OR calendar_item.class IS NULL ";
$where .=      "OR (uprivs($session->user_no,calendar_item.user_no,$c->permission_scan_depth) = privilege_to_bits('all')) ) ";

if ( isset($c->hide_TODO) && $c->hide_TODO && ! $request->AllowedTo('all') ) {
  $where .= "AND caldav_data.caldav_type NOT IN ('VTODO') ";
}

if ( isset($c->strict_result_ordering) && $c->strict_result_ordering ) $where .= " ORDER BY caldav_data.dav_id";
$qry = new PgQuery( "SELECT caldav_data.*,calendar_item.* FROM caldav_data INNER JOIN calendar_item USING(dav_id, user_no, dav_name, collection_id) LEFT JOIN collection USING(collection_id)". $where );
if ( $qry->Exec('REPORT',__LINE__,__FILE__) && $qry->rows > 0 ) {
  while( $calendar_object = $qry->Fetch() ) {
    $responses[] = calendar_to_xml( $properties, $calendar_object );
  }
}

$multistatus = new XMLElement( "multistatus", $responses, $reply->GetXmlNsArray() );

$request->XMLResponse( 207, $multistatus );
