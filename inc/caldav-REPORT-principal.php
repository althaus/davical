<?php

$responses = array();


/**
 * Build the array of properties to include in the report output
 */
$searches = $xmltree->GetPath('/DAV::principal-property-search/DAV::property-search');
dbg_log_array( "principal", "SEARCH", $searches, true );

$clause_joiner = " AND ";
$CS_search_test = $xmltree->GetAttribute('test');
if ( isset($CS_search_test) && $CS_search_test == 'anyof' ) {
  $clause_joiner = " OR ";
}

$where = "";
foreach( $searches AS $k => $search ) {
  $qry_props = $search->GetPath('/DAV::property-search/DAV::prop/*');  // There may be many
  $match     = $search->GetPath('/DAV::property-search/DAV::match');   // There may only be one
  dbg_log_array( "principal", "MATCH", $match, true );
  $match = $match[0]->GetContent();
  $subwhere = "";
  foreach( $qry_props AS $k1 => $v1 ) {
    if ( $subwhere != "" ) $subwhere .= " OR ";
    switch( $v1->GetTag() ) {
      case 'DAV::displayname':
        $subwhere .= "displayname ~* ".qpg('***='.$match);
        break;

      case 'urn:ietf:params:xml:ns:caldav:calendar-user-address-set':
        $match = preg_replace('#^.*/caldav.php/([^/]+)(/.*)?$#', "\\1", $match);
        $match = qpg('***=' . preg_replace('#^mailto:#', '', $match));
        $subwhere .= sprintf('(email ~* %s OR username ~* %s)', $match, $match );
        break;

      case 'urn:ietf:params:xml:ns:caldav:calendar-home-set':
        $subwhere .= "dav_name ~* ".qpg(preg_replace('#^.*/caldav.php#', '', $match));
        break;

      default:
        /**
        * @todo We should handle a lot more properties here.  principal-URL seems a likely one to be used.
        * @todo We should catch the unsupported properties in the query and fire back an error indicating so.
        */
        dbg_error_log("principal", "Unhandled tag '%s' to match '%s'\n", $v1->GetTag(), $match );
    }
  }
  if ( $subwhere != "" ) {
    $where .= sprintf( "%s(%s)", ($where == "" ? "" : $clause_joiner), $subwhere );
  }
}
if ( $where != "" ) $where = "WHERE $where";
$sql = "SELECT * FROM dav_principal $where LIMIT 100";
$qry = new AwlQuery($sql);


$get_props = $xmltree->GetPath('/DAV::principal-property-search/DAV::prop/*');
$properties = array();
foreach( $get_props AS $k1 => $v1 ) {
  $properties[] = $v1->GetTag();
}

if ( $qry->Exec("REPORT",__LINE__,__FILE__) && $qry->rows() > 0 ) {
  while( $row = $qry->Fetch() ) {
    $principal = new DAVResource($row);
    $responses[] = $principal->RenderAsXML( $properties, $reply );
  }
}

$multistatus = new XMLElement( "multistatus", $responses, $reply->GetXmlNsArray() );

$request->XMLResponse( 207, $multistatus );
