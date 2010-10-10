<?php
/**
* Handle the calendar-multiget REPORT request.
*/

$responses = array();

$need_expansion = false;
function check_for_expansion( $calendar_data_node ) {
  global $need_expansion, $expand_range_start, $expand_range_end;

  if ( !class_exists('DateTime') ) return; /** We don't support expansion on PHP5.1 */

  $expansion = $calendar_data_node->GetElements('urn:ietf:params:xml:ns:caldav:expand');
  if ( isset($expansion[0]) ) {
    $need_expansion = true;
    $expand_range_start = $expansion[0]->GetAttribute('start');
    $expand_range_end = $expansion[0]->GetAttribute('end');
    if ( isset($expand_range_start) ) $expand_range_start = new RepeatRuleDateTime($expand_range_start);
    if ( isset($expand_range_end) )   $expand_range_end   = new RepeatRuleDateTime($expand_range_end);
  }
}


/**
 * Build the array of properties to include in the report output
 */

$proptype = $qry_content[0]->GetTag();
$properties = array();
switch( $proptype ) {
  case 'DAV::prop':
    $qry_props = $xmltree->GetPath('/*/'.$proptype.'/*');
    foreach( $qry_content[0]->GetElements() AS $k => $v ) {
      $propertyname = preg_replace( '/^.*:/', '', $v->GetTag() );
      $properties[$propertyname] = 1;
      if ( $v->GetTag() == 'urn:ietf:params:xml:ns:caldav:calendar-data' ) check_for_expansion($v);
    }
    break;

  case 'DAV::allprop':
    $properties['allprop'] = 1;
    if ( $qry_content[1]->GetTag() == 'DAV::include' ) {
      foreach( $qry_content[1]->GetElements() AS $k => $v ) {
        $include_properties[] = $v->GetTag(); /** $include_properties is referenced in DAVResource where allprop is expanded */
        if ( $v->GetTag() == 'urn:ietf:params:xml:ns:caldav:calendar-data' ) check_for_expansion($v);
      }
    }
    break;

  default:
    $propertyname = preg_replace( '/^.*:/', '', $proptype );
    $properties[$propertyname] = 1;
}

$collection = new DAVResource($request->path);
$bound_from = $collection->bound_from();

/**
 * Build the href list for the IN ( href, href, href, ... ) clause.
 */
$mg_hrefs = $xmltree->GetPath('/*/DAV::href');
$href_in = '';
$params = array();
foreach( $mg_hrefs AS $k => $v ) {
  /**
   * We need this to work if they specified a relative *or* a full path, so we strip off
   * anything up to the matching request->path (which will include any http...) and then
   * put the $bound_from prefix back on.
   */
  $href = $bound_from . preg_replace( "{^.*\E$request->path\Q}", '', rawurldecode($v->GetContent()) );
  dbg_error_log("REPORT", "Reporting on href '%s'", $href );
  $href_in .= ($href_in == '' ? '' : ', ');
  $href_in .= ':href'.$k;
  $params[':href'.$k] = $href;
}

$where = " WHERE caldav_data.collection_id = " . $collection->resource_id();
$where .= " AND caldav_data.dav_name IN ( $href_in ) ";

if ( $mode == 'caldav' ) {
  if ( $collection->Privileges() != privilege_to_bits('DAV::all') ) {
    $where .= " AND (calendar_item.class != 'PRIVATE' OR calendar_item.class IS NULL) ";
  }

  if ( isset($c->hide_TODO) && $c->hide_TODO && ! $collection->Privileges() == privilege_to_bits('all') ) {
    $where .= " AND caldav_data.caldav_type NOT IN ('VTODO') ";
  }
  $sql = 'SELECT caldav_data.*,calendar_item.* FROM caldav_data
                  LEFT JOIN calendar_item USING(dav_id, user_no, dav_name, collection_id)
                  LEFT JOIN collection USING(collection_id)';
}
else if ( $mode == 'carddav' ) {
  $sql = 'SELECT caldav_data.*, addressbook_resource.* FROM caldav_data
                  LEFT JOIN addressbook_resource USING(dav_id)
                  LEFT JOIN collection USING(collection_id)';
}

if ( isset($c->strict_result_ordering) && $c->strict_result_ordering ) $where .= " ORDER BY caldav_data.dav_id";
$qry = new AwlQuery( $sql . $where, $params );
if ( $qry->Exec('REPORT',__LINE__,__FILE__) && $qry->rows() > 0 ) {
  while( $dav_object = $qry->Fetch() ) {
    if ( $bound_from != $collection->dav_name() ) {
      $dav_object->dav_name = str_replace( $bound_from, $collection->dav_name(), $dav_object->dav_name);
    }
    if ( $need_expansion ) {
      $vResource = new vComponent($dav_object->caldav_data);
      $expanded = expand_event_instances($vResource, $expand_range_start, $expand_range_end);
      $dav_object->caldav_data = $expanded->Render();
    }
    $responses[] = component_to_xml( $properties, $dav_object );
  }
}

$multistatus = new XMLElement( "multistatus", $responses, $reply->GetXmlNsArray() );

$request->XMLResponse( 207, $multistatus );
