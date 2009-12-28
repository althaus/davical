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

if ( ! ini_get('open_basedir') && (isset($c->dbg['ALL']) || (isset($c->dbg['report']) && $c->dbg['report'])) ) {
  $fh = fopen('/tmp/REPORT.txt','w');
  if ( $fh ) {
    fwrite($fh,$request->raw_post);
    fclose($fh);
  }
}

if ( ! ($request->AllowedTo('read') || $request->AllowedTo('freebusy')) ) {
  // The specification states that a lack of privileges MUST result in a 404. RFC4791, Section 7.10
  $request->DoResponse( 404 );
}

if ( !isset($request->xml_tags) ) {
  $request->DoResponse( 406, translate("REPORT body contains no XML data!") );
}
$position = 0;
$xmltree = BuildXMLTree( $request->xml_tags, $position);
if ( !is_object($xmltree) ) {
  $request->DoResponse( 406, translate("REPORT body is not valid XML data!") );
}

require_once("iCalendar.php");

$reportnum = -1;
$report = array();
$denied = array();
$unsupported = array();
if ( isset($prop_filter) ) unset($prop_filter);

if ( $xmltree->GetTag() == "urn:ietf:params:xml:ns:caldav:free-busy-query" ) {
  include("caldav-REPORT-freebusy.php");
  exit; // Not that the above include should return anyway
}

$reply = new XMLDocument( array( "DAV:" => "" ) );
switch( $xmltree->GetTag() ) {
  case 'DAV::principal-property-search':
    include("caldav-REPORT-principal.php");
    exit; // Not that it should return anyway.
  case 'DAV::principal-search-property-set':
    include("caldav-REPORT-pps-set.php");
    exit; // Not that it should return anyway.
  case 'DAV::sync-collection':
    include("caldav-REPORT-sync-collection.php");
    exit; // Not that it should return anyway.
  case 'DAV::expand-property':
    include("caldav-REPORT-expand-property.php");
    exit; // Not that it should return anyway.
}


/**
* Return XML for a single calendar (or todo) entry from the DB
*
* @param array $properties The properties for this calendar
* @param string $item The calendar data for this calendar
*
* @return string An XML document which is the response for the calendar
*/
function calendar_to_xml( $properties, $item ) {
  global $session, $c, $request, $reply;

  dbg_error_log("REPORT","Building XML Response for item '%s'", $item->dav_name );

  $denied = array();
  $caldav_data = $item->caldav_data;
  $displayname = $item->summary;
  if ( isset($properties['calendar-data']) || isset($properties['displayname']) ) {
    if ( !$request->AllowedTo('all') && $session->user_no != $item->user_no ){
      // the user is not admin / owner of this calendarlooking at his calendar and can not admin the other cal
      /** @todo We should examine the ORGANIZER and ATTENDEE fields in the event.  If this person is there then they should see this */
      if ( $item->class == 'CONFIDENTIAL' || !$request->AllowedTo('read') ) {
        $ical = new iCalComponent( $caldav_data );
        $resources = $ical->GetComponents('VTIMEZONE',false);
        $first = $resources[0];

        // if the event is confidential we fake one that just says "Busy"
        $confidential = new iCalComponent();
        $confidential->SetType($first->GetType());
        $confidential->AddProperty( 'SUMMARY', translate('Busy') );
        $confidential->AddProperty( 'CLASS', 'CONFIDENTIAL' );
        $confidential->SetProperties( $first->GetProperties('DTSTART'), 'DTSTART' );
        $confidential->SetProperties( $first->GetProperties('RRULE'), 'RRULE' );
        $confidential->SetProperties( $first->GetProperties('DURATION'), 'DURATION' );
        $confidential->SetProperties( $first->GetProperties('DTEND'), 'DTEND' );
        $confidential->SetProperties( $first->GetProperties('UID'), 'UID' );
        $ical->SetComponents(array($confidential),$confidential->GetType());

        $caldav_data = $ical->Render();
        $displayname = translate('Busy');
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
      case 'calendar-data':
        $reply->CalDAVElement($prop, $k, $caldav_data );
        break;
      case 'getcontenttype':
        $prop->NewElement($k, "text/calendar" );
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
        $denied[] = $v;
        break;
      default:
        dbg_error_log( 'REPORT', "Request for unsupported property '%s' of calendar item.", $v );
        $unsupported[] = $v;
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

if ( $xmltree->GetTag() == "urn:ietf:params:xml:ns:caldav:calendar-query" ) {
  $calquery = $xmltree->GetPath("/urn:ietf:params:xml:ns:caldav:calendar-query/*");
  include("caldav-REPORT-calquery.php");
}
elseif ( $xmltree->GetTag() == "urn:ietf:params:xml:ns:caldav:calendar-multiget" ) {
  $multiget = $xmltree->GetPath("/urn:ietf:params:xml:ns:caldav:calendar-multiget/*");
  include("caldav-REPORT-multiget.php");
}
else {
  $request->DoResponse( 501, "The XML is not a supported REPORT query document" );
}

