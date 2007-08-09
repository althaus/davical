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

if ( isset($c->dbg['ALL']) || $c->dbg['report'] ) {
  $fh = fopen('/tmp/REPORT.txt','w');
  fwrite($fh,$request->raw_post);
  fclose($fh);
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
if ( isset($prop_filter) ) unset($prop_filter);

$position = 0;
$xmltree = BuildXMLTree( $request->xml_tags, $position);
if ( $xmltree->GetTag() == "URN:IETF:PARAMS:XML:NS:CALDAV:FREE-BUSY-QUERY" ) {
  include("caldav-REPORT-freebusy.php");
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

  $url = $c->protocol_server_port_script . $item->dav_name;
  $prop = new XMLElement("prop");
  if ( isset($properties['GETCONTENTLENGTH']) ) {
    $contentlength = strlen($item->caldav_data);
    $prop->NewElement("getcontentlength", $contentlength );
  }
  if ( isset($properties['CALENDAR-DATA']) ) {
    if ( !is_numeric(strpos($item->permissions,'A')) && $session->user_no != $item->user_no ){
      // the user is not admin / owner of this calendarlooking at his calendar and can not admin the other cal
      if ( $item->class == 'CONFIDENTIAL' ) {
        // if the event is confidential we fake one that just says "Busy"
        $ical = new iCalendar( array( "icalendar" => $item->caldav_data) );
        $ical->Put( 'SUMMARY', translate("Busy") );
        $caldav_data = $ical->render(true, $item->caldav_type, $ical->DefaultPropertyList() );
        $prop->NewElement("calendar-data","$caldav_data" , array("xmlns" => "urn:ietf:params:xml:ns:caldav") );
      }
      elseif ( $c->hide_alarm ) {
        // Otherwise we hide the alarms (if configured to)
        $ical = new iCalendar( array( "icalendar" => $item->caldav_data) );
        $caldav_data = $ical->render(true, $item->caldav_type, $ical->DefaultPropertyList()() );
        $prop->NewElement("calendar-data","$caldav_data" , array("xmlns" => "urn:ietf:params:xml:ns:caldav") );
      }
      else {
        // Just send the raw event
        $prop->NewElement("calendar-data", $item->caldav_data, array("xmlns" => "urn:ietf:params:xml:ns:caldav") );
      }
    }
    else
      // Just send the raw event
      $prop->NewElement("calendar-data", $item->caldav_data, array("xmlns" => "urn:ietf:params:xml:ns:caldav") );
  }
  if ( isset($properties['GETCONTENTTYPE']) ) {
    $prop->NewElement("getcontenttype", "text/calendar" );
  }
  if ( isset($properties['RESOURCETYPE']) ) {
    $prop->NewElement("resourcetype", new XMLElement("calendar", false, array("xmlns" => "urn:ietf:params:xml:ns:caldav")) );
  }
  if ( isset($properties['DISPLAYNAME']) ) {
    $prop->NewElement("displayname");
  }
  if ( isset($properties['GETETAG']) ) {
    $prop->NewElement("getetag", '"'.$item->dav_etag.'"' );
  }
  if ( isset($properties['CURRENT-USER-PRIVILEGE-SET']) ) {
    $prop->NewElement("current-user-privilege-set", privileges($request->permissions) );
  }
  $status = new XMLElement("status", "HTTP/1.1 200 OK" );

  $propstat = new XMLElement( "propstat", array( $prop, $status) );
  $href = new XMLElement("href", $url );

  $response = new XMLElement( "response", array($href,$propstat));

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