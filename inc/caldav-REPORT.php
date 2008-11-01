<?php
/**
* CalDAV Server - handle REPORT method
*
* @package   davical
* @subpackage   caldav
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("REPORT", "method handler");

require_once("XMLDocument.php");

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
  $request->DoResponse( 403, translate("REPORT body contains no XML data!") );
}
$position = 0;
$xmltree = BuildXMLTree( $request->xml_tags, $position);
if ( !is_object($xmltree) ) {
  $request->DoResponse( 403, translate("REPORT body is not valid XML data!") );
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
if ( $xmltree->GetTag() == "DAV::principal-property-search" ) {
  include("caldav-REPORT-principal.php");
  exit; // Not that the above include should return anyway
}

// Must have read privilege for all other reports
if ( ! ($request->AllowedTo('read') ) ) {
  // If they got this far they *do* have freebusy access, so can know the
  // calendar really exists.  Informing them is therefore OK.
  $request->DoResponse( 404, translate("You may not access that calendar") );
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
      if ( $item->class == 'CONFIDENTIAL' ) {
        $ical = new iCalendar( array( "icalendar" => $caldav_data) );
        // if the event is confidential we fake one that just says "Busy"
        $confidential = new iCalendar( array(
                              'SUMMARY' => translate('Busy'), 'CLASS' => 'CONFIDENTIAL',
                              'DTSTART' => $ical->Get('DTSTART')
                          ) );
        $rrule = $ical->Get('RRULE');
        if ( isset($rrule) && $rrule != '' ) $confidential->Set('RRULE', $rrule);
        $duration = $ical->Get('DURATION');
        if ( isset($duration) && $duration != "" ) {
          $confidential->Set('DURATION', $duration );
        }
        else {
          $confidential->Set('DTEND', $ical->Get('DTEND') );
        }
        $caldav_data = $confidential->Render( true, $item->caldav_type );
      }
      elseif ( isset($c->hide_alarm) && $c->hide_alarm ) {
        // Otherwise we hide the alarms (if configured to)
        $ical = new iCalendar( array( "icalendar" => $caldav_data) );
        $ical->component->ClearComponents('VALARM');
        $caldav_data = $ical->render(true, $caldav_type );
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
        $prop->NewElement($reply->Caldav($k), $caldav_data );
        break;
      case 'getcontenttype':
        $prop->NewElement($k, "text/calendar" );
        break;
/*
* I don't think this is correct.  We should only list these properties against
* the (relevant) collection, not against it's contents.
      case 'resourcetype':
        $prop->NewElement($k, new XMLElement($reply->Caldav("calendar"), false) );
        if ( $request->collection_type == 'in' ) {
          $prop->NewElement($k, new XMLElement($reply->Caldav("schedule-inbox"), false) );
        }
        else if ( $request->collection_type == 'out' ) {
          $prop->NewElement($k, new XMLElement($reply->Caldav("schedule-outbox"), false) );
        }
        else {
          $prop->NewElement($k, new XMLElement($reply->Caldav("schedule-calendar"), false) );
        }
        break;
*/
      case 'current-user-principal':
        $prop->NewElement("current-user-principal", $request->current_user_principal_xml);
        break;
      case 'displayname':
        $prop->NewElement($k, $displayname );
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

