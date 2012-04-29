<?php
/**
* CalDAV Server - handle REPORT method
*
* @package   davical
* @subpackage   caldav
* @author    Andrew McMillan <andrew@morphoss.com>
* @copyright Catalyst .Net Ltd, Morphoss Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("REPORT", "method handler");

require_once("XMLDocument.php");
require_once('DAVResource.php');

require_once('RRule-v2.php');

if ( ! ini_get('open_basedir') && (isset($c->dbg['ALL']) || (isset($c->dbg['report']) && $c->dbg['report'])) ) {
  $fh = fopen('/tmp/REPORT.txt','w');
  if ( $fh ) {
    fwrite($fh,$request->raw_post);
    fclose($fh);
  }
}

if ( !isset($request->xml_tags) ) {
  $request->DoResponse( 406, translate("REPORT body contains no XML data!") );
}
$position = 0;
$xmltree = BuildXMLTree( $request->xml_tags, $position);
if ( !is_object($xmltree) ) {
  $request->DoResponse( 406, translate("REPORT body is not valid XML data!") );
}

$target = new DAVResource($request->path);

if ( $xmltree->GetNSTag() != 'DAV::principal-property-search'
                && $xmltree->GetNSTag() != 'DAV::principal-property-search-set' ) {
  $target->NeedPrivilege( array('DAV::read', 'urn:ietf:params:xml:ns:caldav:read-free-busy'), true ); // They may have either
}

require_once("vCalendar.php");

$reportnum = -1;
$report = array();
$denied = array();
$unsupported = array();
if ( isset($prop_filter) ) unset($prop_filter);

if ( $xmltree->GetNSTag() == 'urn:ietf:params:xml:ns:caldav:free-busy-query' ) {
  include("caldav-REPORT-freebusy.php");
  exit; // Not that the above include should return anyway
}

$reply = new XMLDocument( array( "DAV:" => "" ) );
switch( $xmltree->GetNSTag() ) {
  case 'DAV::principal-property-search':
    include("caldav-REPORT-principal.php");
    exit; // Not that it should return anyway.
  case 'DAV::principal-search-property-set':
    include("caldav-REPORT-pps-set.php");
    exit; // Not that it should return anyway.
  case 'DAV::sync-collection':
		if ( $target->IsExternal() ) {
			require_once("external-fetch.php");
			update_external ( $target );
		}
    include("caldav-REPORT-sync-collection.php");
    exit; // Not that it should return anyway.
  case 'DAV::expand-property':
    if ( $target->IsExternal() ) {
			require_once("external-fetch.php");
			update_external ( $target );
		}
    include("caldav-REPORT-expand-property.php");
    exit; // Not that it should return anyway.
  case 'DAV::principal-match':
    include("caldav-REPORT-principal-match.php");
    exit; // Not that it should return anyway.
}



/**
* Return XML for a single component from the DB
*
* @param array $properties The properties for this component
* @param string $item The DB row data for this component
*
* @return string An XML document which is the response for the component
*/
function component_to_xml( $properties, $item ) {
  global $session, $c, $request, $reply;

  dbg_error_log("REPORT","Building XML Response for item '%s'", $item->dav_name );

  $denied = array();
  $unsupported = array();
  $caldav_data = $item->caldav_data;
  $displayname = preg_replace( '{^.*/}', '', $item->dav_name );
  $type = 'unknown';
  $contenttype = 'text/plain';
  switch( $item->caldav_type ) {
    case 'VJOURNAL':
    case 'VEVENT':
    case 'VTODO':
      $displayname = $item->summary;
      $type = 'calendar';
      $contenttype = 'text/calendar';
      break;

    case 'VCARD':
      $displayname = $item->fn;
      $type = 'vcard';
      $contenttype = 'text/vcard';
      break;
  }
  if ( $type == 'calendar' ) {
    if ( isset($properties['calendar-data']) || isset($properties['displayname']) ) {
      if ( !$request->AllowedTo('all') && $session->user_no != $item->user_no ) {
        // the user is not admin / owner of this calendar looking at his calendar and can not admin the other cal
        if ( $item->class == 'CONFIDENTIAL' || !$request->AllowedTo('read') ) {
          dbg_error_log("REPORT","Anonymising confidential event for: %s", $item->dav_name );
          $vcal = new vCalendar( $caldav_data );
          $caldav_data = $vcal->Confidential()->Render();
          $displayname = translate('Busy');
        }
      }
    }

    if ( isset($c->hide_alarm) && $c->hide_alarm ) {
      $dav_resource = new DAVResource($request->path);
      if ( isset($properties['calendar-data']) && !$dav_resource->HavePrivilegeTo('write') ) {
        dbg_error_log("REPORT","Stripping event alarms for: %s", $item->dav_name );
        $vcal = new vCalendar($caldav_data);
        $vcal->ClearComponents('VALARM');
        $caldav_data = $vcal->Render();
      }
    }
  }
  
  $url = ConstructURL($item->dav_name);

  $prop = new XMLElement("prop");
  foreach( $properties AS $k => $v ) {
    switch( $k ) {
      case 'getcontentlength':
        $contentlength = strlen($caldav_data);
        $prop->NewElement($k, $contentlength );
        break;
      case 'getlastmodified':
        $prop->NewElement($k, ISODateToHTTPDate($item->modified) );
        break;
      case 'calendar-data':
        if ( $type == 'calendar' ) $reply->CalDAVElement($prop, $k, $caldav_data );
        else $unsupported[] = $k;
        break;
      case 'address-data':
        if ( $type == 'vcard' ) $reply->CardDAVElement($prop, $k, $caldav_data );
        else $unsupported[] = $k;
        break;
      case 'getcontenttype':
        $prop->NewElement($k, $contenttype );
        break;
      case 'current-user-principal':
        $prop->NewElement("current-user-principal", $request->current_user_principal_xml);
        break;
      case 'displayname':
        $prop->NewElement($k, $displayname );
        break;
      case 'resourcetype':
        $prop->NewElement($k); // Just an empty resourcetype for a non-collection.
        break;
      case 'getetag':
        $prop->NewElement($k, '"'.$item->dav_etag.'"' );
        break;
      case '"current-user-privilege-set"':
        $prop->NewElement($k, privileges($request->permissions) );
        break;
      case 'SOME-DENIED-PROPERTY':  /** indicating the style for future expansion */
        $denied[] = $k;
        break;
      default:
        dbg_error_log( 'REPORT', "Request for unsupported property '%s' of calendar item.", $v );
        $unsupported[] = $k;
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

  if ( !(isset($request->brief_response) && $request->brief_response) && count($unsupported) > 0 ) {
    $status = new XMLElement("status", "HTTP/1.1 404 Not Found" );
    $noprop = new XMLElement("prop");
    foreach( $unsupported AS $k => $v ) {
      $noprop->NewElement( strtolower($v) );
    }
    $elements[] = new XMLElement( "propstat", array( $noprop, $status) );
  }

  $response = new XMLElement( "response", $elements );

  return $response;
}

if ( $target->IsExternal() ) {
	require_once("external-fetch.php");
	update_external ( $target );
}

if ( $xmltree->GetNSTag() == "urn:ietf:params:xml:ns:caldav:calendar-query" ) {
  $calquery = $xmltree->GetPath("/urn:ietf:params:xml:ns:caldav:calendar-query/*");
  include("caldav-REPORT-calquery.php");
}
elseif ( $xmltree->GetNSTag() == "urn:ietf:params:xml:ns:caldav:calendar-multiget" ) {
  $mode = 'caldav';
  $qry_content = $xmltree->GetContent('urn:ietf:params:xml:ns:caldav:calendar-multiget');
  include("caldav-REPORT-multiget.php");
}
elseif ( $xmltree->GetNSTag() == "urn:ietf:params:xml:ns:carddav:addressbook-multiget" ) {
  $mode = 'carddav';
  $qry_content = $xmltree->GetContent('urn:ietf:params:xml:ns:carddav:addressbook-multiget');
  include("caldav-REPORT-multiget.php");
}
elseif ( $xmltree->GetNSTag() == "urn:ietf:params:xml:ns:carddav:addressbook-query" ) {
  $cardquery = $xmltree->GetPath("/urn:ietf:params:xml:ns:carddav:addressbook-query/*");
  include("caldav-REPORT-cardquery.php");
}
else {
  dbg_error_log( 'ERROR', "Request for unsupported report type '%s'.", $xmltree->GetNSTag() );
  $request->PreconditionFailed( 403, 'DAV::supported-report', sprintf( '"%s" is not a supported report type', $xmltree->GetNSTag()) );
}

