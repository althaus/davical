<?php

$responses = array();

/**
* Return XML for a single Principal (user) from the DB
*
* @param array $properties The requested properties for this principal
* @param string $item The user data for this calendar
*
* @return string An XML document which is the response for the principal
*/
function principal_to_xml( $properties, $item ) {
  global $session, $c, $request;

  dbg_error_log("REPORT","Building XML Response for principal '%s'", $item->username );

  $this_url = $c->protocol_server_port_script . $request->dav_name;
  $principal_url = sprintf( "%s/%s/", $c->protocol_server_port_script, $item->username);
  $home_calendar = sprintf( "%s/%s/%s/", $c->protocol_server_port_script, $item->username, $c->home_calendar_name);
  $prop = new XMLElement("prop");
  $denied = array();
  foreach( $properties AS $k => $v ) {
    switch( $v ) {
      case 'DAV::RESOURCETYPE':
        $prop->NewElement("resourcetype", new XMLElement("principal") );
        break;
      case 'DAV::DISPLAYNAME':
        $prop->NewElement("displayname", $item->username );
        break;
      case 'DAV::PRINCIPAL-URL':
        $prop->NewElement("principal-url", $principal_url );
        break;
      case 'DAV::ALTERNATE-URI':
        $prop->NewElement("alternate-uri" );
        break;
      case 'DAV::GROUP-MEMBER-SET':
        $qry = new PgQuery("SELECT * FROM relationship LEFT JOIN usr ON (from_user = usr.user_no) LEFT JOIN role_member ON (to_user = role_member.user_no) LEFT JOIN roles USING (role_no) WHERE to_user = ? AND role_name = 'Group';", $item->user_no );
        $group = array();
        if ( $qry->Exec("REPORT-principal") && $qry->rows > 0 ) {
          while( $membership = $qry->Fetch() ) {
            $group[] = new XMLElement("href", sprintf( "%s/%s/", $c->protocol_server_port_script, $membership->username) );
          }
        }
        $prop->NewElement("group-member-set", $group );
        break;
      case 'DAV::GROUP-MEMBERSHIP':
        $qry = new PgQuery("SELECT * FROM relationship LEFT JOIN usr ON (to_user = user_no) LEFT JOIN role_member USING (user_no) LEFT JOIN roles USING (role_no) WHERE from_user = ? AND role_name = 'Group';", $item->user_no );
        $group = array();
        if ( $qry->Exec("REPORT-principal") && $qry->rows > 0 ) {
          while( $membership = $qry->Fetch() ) {
            $group[] = new XMLElement("href", sprintf( "%s/%s/", $c->protocol_server_port_script, $membership->username) );
          }
        }
        $prop->NewElement("group-membership", $group );
        break;
      case 'URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-HOME-SET':
        $prop->NewElement("calendar-home-set", $home_calendar, array("xmlns" => "urn:ietf:params:xml:ns:caldav") );
        break;
      case 'SOME-DENIED-PROPERTY':  /** TODO: indicating the style for future expansion */
        $denied[] = $v;
        break;
      default:
        dbg_error_log( 'REPORT', "Request for unsupported property '%s' of principal.", $item->username );
        break;
    }
  }
  $status = new XMLElement("status", "HTTP/1.1 200 OK" );

  $propstat = new XMLElement( "propstat", array( $prop, $status) );
  $href = new XMLElement("href", $url );

  $elements = array($href,$propstat);

  if ( count($denied) > 0 ) {
    $status = new XMLElement("status", "HTTP/1.1 403 Forbidden" );
    $noprop = new XMLElement("prop");
    foreach( $denied AS $k => $v ) {
      $noprop->NewElement( strtolower($v) );
    }
    $elements[] = new XMLElement( "propstat", array( $noprop, $status) );
  }

  $response = new XMLElement( "response", $elements );

  return $response;
}

/**
 * Build the array of properties to include in the report output
 */
$searches = $xmltree->GetPath('/DAV::PRINCIPAL-PROPERTY-SEARCH/DAV::PROPERTY-SEARCH');
dbg_log_array( "principal", "SEARCH", $searches, true );

$where = "";
foreach( $searches AS $k => $search ) {
  $qry_props = $search->GetPath('/DAV::PROPERTY-SEARCH/DAV::PROP/*');  // There may be many
  $match     = $search->GetPath('/DAV::PROPERTY-SEARCH/DAV::MATCH');   // There may only be one
  dbg_log_array( "principal", "MATCH", $match, true );
  $match = qpg($match[0]->GetContent());
  $subwhere = "";
  foreach( $qry_props AS $k1 => $v1 ) {
    if ( $subwhere != "" ) $subwhere .= " OR ";
    switch( $v1->GetTag() ) {
      case 'DAV::DISPLAYNAME':
        $subwhere .= "username = ".$match;
        break;
      default:
        printf("Unhandled tag '%s'\n", $v1->GetTag() );
    }
  }
  if ( $subwhere != "" ) {
    $where .= sprintf( "%s(%s)", ($where == "" ? "" : " AND "), $subwhere );
  }
}
if ( $where != "" ) $where = "WHERE $where";
$sql = "SELECT * FROM usr $where";
$qry = new PgQuery($sql);


$get_props = $xmltree->GetPath('/DAV::PRINCIPAL-PROPERTY-SEARCH/DAV::PROP/*');
$properties = array();
foreach( $get_props AS $k1 => $v1 ) {
  $properties[] = $v1->GetTag();
}

if ( $qry->Exec("REPORT",__LINE__,__FILE__) && $qry->rows > 0 ) {
  while( $principal_object = $qry->Fetch() ) {
    $responses[] = principal_to_xml( $properties, $principal_object );
  }
}

$multistatus = new XMLElement( "multistatus", $responses, array('xmlns'=>'DAV:') );

$request->XMLResponse( 207, $multistatus );
