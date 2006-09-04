<?php
  error_log("caldav: DBG: REPORT method handler");

include_once("iCalendar.php");

error_reporting(E_ALL);

$parser = xml_parser_create_ns('UTF-8');
xml_parser_set_option ( $parser, XML_OPTION_SKIP_WHITE, 1 );

function xml_start_callback( $parser, $el_name, $el_attrs ) {
  error_log( "DBG: Parsing $el_name" );
  dbg_log_array( "$el_name::attrs", $el_attrs, true );
}

function xml_end_callback( $parser, $el_name ) {
  error_log( "DBG: Finished Parsing $el_name" );
}

xml_set_element_handler ( $parser, 'xml_start_callback', 'xml_end_callback' );

$rpt_request = array();
xml_parse_into_struct( $parser, $raw_post, $rpt_request );
xml_parser_free($parser);


$reportnum = -1;
$report = array();
foreach( $rpt_request AS $k => $v ) {

  switch ( $v['tag'] ) {

    case 'URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-QUERY':
      if ( $v['type'] == "open" ) {
        $reportnum++;
        $report_type = substr($v['tag'],30);
        $report[$reportnum]['type'] = $report_type;
      }
      else {
        unset($report_type);
      }
      break;

    case 'DAV::PROP':
      if ( isset($report_type) ) {
        if ( $v['type'] == "open" ) {
          $report_properties = array();
        }
        else if ( $v['type'] == "close" ) {
          $report[$reportnum]['properties'] = $report_properties;
          unset($report_properties);
        }
        else {
          error_log( "DBG: Unexpected DAV::PROP type of ".$v['type'] );
        }
      }
      else {
        error_log( "DBG: Unexpected DAV::PROP type of ".$v['type']." when no active report type.");
      }
      break;

    case 'DAV::GETETAG':
      if ( isset($report_properties) ) {
        if ( $v['type'] == "complete" ) {
          $report_properties['GETETAG'] = 1;
        }
      }
      break;

     default:
       error_log("caldav: DBG: Unhandled tag >>".$v['tag']."<<");
  }
}

dbg_log_array( 'RPT', $rpt_request, true );

dbg_log_array( 'REPORT', $report, true );

header("Content-type: text/xml");

echo <<<EOXML
<?xml version="1.0" encoding="utf-8" ?>
<D:multistatus xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
  <D:response>
    <D:href>http://mycaldav/?andrewmcmillan.ics</D:href>
    <D:propstat>
      <D:prop>
        <D:getetag>"fffff-abcd1"</D:getetag>
        <C:calendar-data>BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Andrew's CalDAV Server//PHP//0.0.1
EOXML;

echo iCalendar::vTimeZone("Pacific/Auckland");
$event = new vEvent( '20060517T150000Z', 'PT2H', 'andrew@catalyst.net.nz', '', 'A Test Event for Andrew' );
echo $event->ToString();

echo <<<EOXML
END:VCALENDAR
        </C:calendar-data>
      </D:prop>
      <D:status>HTTP/1.1 200 OK</D:status>
    </D:propstat>
  </D:response>
</D:multistatus>
EOXML;

?>