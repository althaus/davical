<?php
/**
* CalDAV Server - handle REPORT method
*
* @package   rscds
* @subpackage   caldav
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("REPORT", "method handler");

if ( ! ini_get('open_basedir') && (isset($c->dbg['ALL']) || $c->dbg['report']) ) {
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
  $request->DoResponse( 403, "REPORT body contains no XML data!" );
}

require_once("iCalendar.php");

$reportnum = -1;
$report = array();
$denied = array();
$unsupported = array();
if ( isset($prop_filter) ) unset($prop_filter);

$position = 0;
$xmltree = BuildXMLTree( $request->xml_tags, $position);
if ( $xmltree->GetTag() == "URN:IETF:PARAMS:XML:NS:CALDAV:FREE-BUSY-QUERY" ) {
  include("caldav-REPORT-freebusy.php");
  exit; // Not that the above include should return anyway
}
if ( $xmltree->GetTag() == "DAV::PRINCIPAL-PROPERTY-SEARCH" ) {
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
  global $session, $c, $request;

  dbg_error_log("REPORT","Building XML Response for item '%s'", $item->dav_name );

  $caldav_data = $item->caldav_data;
  $displayname = $item->summary;
  if ( isset($properties['CALENDAR-DATA']) || isset($properties['DISPLAYNAME']) ) {
    if ( !$request->AllowedTo('all') && $session->user_no != $item->user_no ){
      // the user is not admin / owner of this calendarlooking at his calendar and can not admin the other cal
      if ( $item->class == 'CONFIDENTIAL' ) {
        $ical = new iCalendar( array( "icalendar" => $caldav_data) );
        // if the event is confidential we fake one that just says "Busy"
        $confidential = new iCalendar( array(
                              'SUMMARY' => translate('Busy'), 'CLASS' => 'CONFIDENTIAL',
                              'DTSTART'  => $ical->Get('DTSTART'),
                              'RRULE'    => $ical->Get('RRULE')
                          ) );
        $duration = $ical->Get('DURATION');
        if ( isset($duration) && $duration != "" ) {
          $confidential->Set('DURATION', $duration );
        }
        else {
          $confidential->Set('DTEND', $ical->Get('DTEND') );
        }
        $caldav_data = $confidential->Render( true, $caldav_type );
      }
      elseif ( $c->hide_alarm ) {
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
      case 'GETCONTENTLENGTH':
        $contentlength = strlen($caldav_data);
        $prop->NewElement("getcontentlength", $contentlength );
        break;
      case 'CALENDAR-DATA':
        $prop->NewElement("calendar-data","$caldav_data" , array("xmlns" => "urn:ietf:params:xml:ns:caldav") );
        break;
      case 'GETCONTENTTYPE':
        $prop->NewElement("getcontenttype", "text/calendar" );
        break;
      case 'RESOURCETYPE':
        $prop->NewElement("resourcetype", new XMLElement("calendar", false, array("xmlns" => "urn:ietf:params:xml:ns:caldav")) );
        break;
      case 'DISPLAYNAME':
        $prop->NewElement("displayname", $displayname );
        break;
      case 'GETETAG':
        $prop->NewElement("getetag", '"'.$item->dav_etag.'"' );
        break;
      case 'CURRENT-USER-PRIVILEGE-SET':
        $prop->NewElement("current-user-privilege-set", privileges($request->permissions) );
        break;
      case 'SOME-DENIED-PROPERTY':  /** TODO: indicating the style for future expansion */
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

if ( $xmltree->GetTag() == "URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-QUERY" ) {
  $calquery = $xmltree->GetPath("/URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-QUERY/*");
  include("caldav-REPORT-calquery.php");
}
elseif ( $xmltree->GetTag() == "URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-MULTIGET" ) {
  $multiget = $xmltree->GetPath("/URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-MULTIGET/*");
  include("caldav-REPORT-multiget.php");
}
else {
  $request->DoResponse( 501, "XML is not a supported REPORT query document" );
}

?>