<?php

error_log("caldav: DBG: PUT method handler");

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

$put_request = array();
xml_parse_into_struct( $parser, $raw_post, $put_request );
xml_parser_free($parser);


$putnum = -1;
$put = array();
foreach( $put_request AS $k => $v ) {

  switch ( $v['tag'] ) {

    case 'URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-QUERY':
      if ( $v['type'] == "open" ) {
        $putnum++;
        $put_type = substr($v['tag'],30);
        $put[$putnum]['type'] = $put_type;
      }
      else {
        unset($put_type);
      }
      break;

    case 'DAV::PROP':
      if ( isset($put_type) ) {
        if ( $v['type'] == "open" ) {
          $put_properties = array();
        }
        else if ( $v['type'] == "close" ) {
          $put[$putnum]['properties'] = $put_properties;
          unset($put_properties);
        }
        else {
          error_log( "DBG: Unexpected DAV::PROP type of ".$v['type'] );
        }
      }
      else {
        error_log( "DBG: Unexpected DAV::PROP type of ".$v['type']." when no active put type.");
      }
      break;

    case 'DAV::GETETAG':
      if ( isset($put_properties) ) {
        if ( $v['type'] == "complete" ) {
          $put_properties['GETETAG'] = 1;
        }
      }
      break;

     default:
       error_log("caldav: DBG: Unhandled tag >>".$v['tag']."<<");
  }
}

dbg_log_array( 'RPT', $put_request, true );

dbg_log_array( 'REPORT', $put, true );

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