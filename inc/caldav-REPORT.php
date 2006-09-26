<?php

dbg_error_log("REPORT", "method handler");

$attributes = array();
$parser = xml_parser_create_ns('UTF-8');
xml_parser_set_option ( $parser, XML_OPTION_SKIP_WHITE, 1 );

function xml_start_callback( $parser, $el_name, $el_attrs ) {
//  dbg_error_log( "REPORT", "Parsing $el_name" );
  dbg_log_array( "REPORT", "$el_name::attrs", $el_attrs, true );
  $attributes[$el_name] = $el_attrs;
}

function xml_end_callback( $parser, $el_name ) {
//  dbg_error_log( "REPORT", "Finished Parsing $el_name" );
}

xml_set_element_handler ( $parser, 'xml_start_callback', 'xml_end_callback' );

$rpt_request = array();
xml_parse_into_struct( $parser, $raw_post, $rpt_request );
xml_parser_free($parser);

$reportnum = -1;
$report = array();
foreach( $rpt_request AS $k => $v ) {

  switch ( $v['tag'] ) {

    case 'URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-MULTIGET':
      dbg_log_array( "REPORT", "CALENDAR-MULTIGET", $v, true );
      $report[$reportnum]['multiget'] = 1;
      if ( $v['type'] == "open" ) {
        $multiget_names = array();
      }
      else if ( $v['type'] == "close" ) {
        $report[$reportnum]['get_names'] = $multiget_names;
        unset($multiget_names);
      }
      break;

    case 'URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-DATA':
      dbg_log_array( "REPORT", "CALENDAR-DATA", $v, true );
      if ( $v['type'] == "complete" ) {
        $report[$reportnum]['include_data'] = 1;
      }
      break;

    case 'URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-QUERY':
      dbg_log_array( "REPORT", "CALENDAR-QUERY", $v, true );
      if ( $v['type'] == "open" ) {
        $reportnum++;
        $report_type = substr($v['tag'],30);
        $report[$reportnum]['type'] = $report_type;
        $report[$reportnum]['include_href'] = 1;
        $report[$reportnum]['include_data'] = 1;
      }
      else {
        unset($report_type);
      }
      break;

    case 'URN:IETF:PARAMS:XML:NS:CALDAV:TIME-RANGE':
      dbg_log_array( "REPORT", "TIME-RANGE", $v, true );
      if ( isset($v['attributes']['START']) ) {
        $report[$reportnum]['start'] = $v['attributes']['START'];
      }
      if ( isset($v['attributes']['END']) ) {
        $report[$reportnum]['end'] = $v['attributes']['END'];
      }
      break;

    case 'URN:IETF:PARAMS:XML:NS:CALDAV:COMP-FILTER':
      dbg_log_array( "REPORT", "COMP-FILTER", $v, true );
      if ( isset($v['attributes']['NAME']) && ($v['attributes']['NAME'] == 'VCALENDAR' )) {
        $report[$reportnum]['calendar'] = 1;
      }
      if ( isset($v['attributes']['NAME']) ) {
        if ( isset($report[$reportnum]['calendar']) && ($v['attributes']['NAME'] == 'VEVENT') ) {
          $report[$reportnum]['calendar-event'] = 1;
        }
        if ( isset($report[$reportnum]['calendar']) && ($v['attributes']['NAME'] == 'VTODO') ) {
          $report[$reportnum]['calendar-todo'] = 1;
        }
        if ( isset($report[$reportnum]['calendar']) && ($v['attributes']['NAME'] == 'VFREEBUSY') ) {
          $report[$reportnum]['calendar-freebusy'] = 1;
        }
      }
      break;

    case 'URN:IETF:PARAMS:XML:NS:CALDAV:FILTER':
      dbg_error_log( "REPORT", "Not using %s information which follows...", $v['tag'] );
      dbg_log_array( "REPORT", "FILTER", $v, true );
      break;

    case 'DAV::PROP':
      dbg_log_array( "REPORT", "DAV::PROP", $v, true );
      if ( isset($report_type) ) {
        if ( $v['type'] == "open" ) {
          $report_properties = array();
        }
        else if ( $v['type'] == "close" ) {
          $report[$reportnum]['properties'] = $report_properties;
          unset($report_properties);
        }
        else {
          dbg_error_log( "REPORT", "Unexpected DAV::PROP type of ".$v['type'] );
        }
      }
      else {
        dbg_error_log( "REPORT", "Unexpected DAV::PROP type of ".$v['type']." when no active report type.");
      }
      break;

    case 'DAV::GETETAG':
    case 'DAV::GETCONTENTLENGTH':
    case 'DAV::GETCONTENTTYPE':
    case 'DAV::RESOURCETYPE':
      if ( isset($report_properties) ) {
        $attribute = substr($v['tag'],5);
        $report_properties[$attribute] = 1;
      }
      break;

    case 'DAV::HREF':
      dbg_log_array( "REPORT", "DAV::HREF", $v, true );
      if ( isset($report[$reportnum]['multiget']) ) {
        $multiget_names[] = $v['value'];
      }

     default:
       dbg_error_log( "REPORT", "Unhandled tag >>".$v['tag']."<<");
  }
}

if ( $unsupported_stuff ) {
   header('HTTP/1.1 403 Forbidden');
   header('Content-Type: application/xml; charset="utf-8"');
   echo <<<EOXML
<?xml version="1.0" encoding="utf-8" ?>
<D:error xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
  <C:supported-filter>
    <C:prop-filter name="X-ABC-GUID"/>
  </C:supported-filter>
</D:error>
EOXML;
  exit(0);
}

header("HTTP/1.1 207 Multi-Status");
header("Content-type: text/xml;charset=UTF-8");

/**
* FIXME - this needs to be rewritten using XML libraries, in the same manner
* in which the REPORT request is parsed, in fact.  For the time being we will
* attach importance to the care and feeding of Evolution, however.
*/
$response_tpl = <<<RESPONSETPL
    <D:response>%s
        <D:propstat>
            <D:prop>%s
            </D:prop>
            <D:status>HTTP/1.1 200 OK</D:status>
        </D:propstat>
    </D:response>

RESPONSETPL;

$property_tpl = <<<PROPERTYTPL

                <D:%s>"%s"</D:%s>
PROPERTYTPL;

$calendar_href_tpl = <<<CALDATATPL

        <D:href>http://%s:%d%s%s</D:href>
CALDATATPL;

$calendar_data_tpl = <<<CALDATATPL

                <C:calendar-data>%s                </C:calendar-data>
CALDATATPL;

dbg_log_array("REPORT", "report", $report, true );

echo <<<REPORTHDR
<?xml version="1.0" encoding="utf-8" ?>
<D:multistatus xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">

REPORTHDR;

  for ( $i=0; $i <= $reportnum; $i++ ) {
    dbg_error_log("REPORT", "Report[%d] Start:%s, End: %s, Events: %d, Todos: %d, Freebusy: %d",
         $i, $report[$i]['start'], $report[$i]['end'], $report[$i]['calendar-event'], $report[$i]['calendar-todo'], $report[$i]['calendar-freebusy']);

    if ( isset($report[$i]['calendar-event']) ) {
      /**
      * Produce VEVENT data.
      */
      if ( isset($report[$i]['include_href']) ) dbg_error_log( "REPORT", "Returning href event data" );
      if ( isset($report[$i]['include_data']) ) dbg_error_log( "REPORT", "Returning full event data" );
      $sql = "SELECT * FROM caldav_data NATURAL JOIN event WHERE caldav_type = 'VEVENT' ";
      $where = "";
      if ( isset( $report[$i]['start'] ) ) {
        $where = "AND (dtend >= ".qpg($report[$i]['start'])."::timestamp with time zone ";
        $where .= "OR calculate_later_timestamp(".qpg($report[$i]['start'])."::timestamp with time zone,dtend,rrule) >= ".qpg($report[$i]['start'])."::timestamp with time zone) ";
      }
      if ( isset( $report[$i]['end'] ) ) {
        $where .= "AND dtstart <= ".qpg($report[$i]['end'])."::timestamp with time zone ";
      }
      $sql .= $where;
      $qry = new PgQuery( $sql );
      if ( $qry->Exec("REPORT",__LINE__,__FILE__) && $qry->rows > 0 ) {
        while( $event = $qry->Fetch() ) {
          $calhref = ( isset($report[$i]['include_href']) ? sprintf( $calendar_href_tpl, $_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT'], $_SERVER['SCRIPT_NAME'], $event->dav_name ) : "" );
          $caldata = ( isset($report[$i]['include_data']) ? sprintf( $calendar_data_tpl, $event->caldav_data ) : "" );
          $properties = "";
          foreach( $report[$i]['properties'] AS $k => $v ) {
            switch( $k ) {
              case 'GETETAG':             $value = $event->dav_etag;                 break;
              case 'GETCONTENTLENGTH':    $value = strlen($event->caldav_data);      break;
              case 'GETCONTENTTYPE':      $value = "text/calendar";                  break;
              case 'RESOURCETYPE':        $value = "VEVENT";                         break;
            }
            $value = sprintf( $property_tpl, strtolower($k), $value, strtolower($k));
          }
          printf( $response_tpl, $calhref, $value, $caldata );
          dbg_error_log("REPORT", "ETag >>%s<< >>http://%s:%s%s%s<<", $event->dav_etag,
                                $_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT'], $_SERVER['SCRIPT_NAME'], $event->dav_name);
        }
      }
    }

    if ( isset($report[$i]['calendar-todo']) ) {
      /**
      * Produce VTODO data.
      */
      if ( isset($report[$i]['include_href']) ) dbg_error_log( "REPORT", "Returning href event data" );
      if ( isset($report[$i]['include_data']) ) dbg_error_log( "REPORT", "Returning full event data" );
      $sql = "SELECT * FROM caldav_data NATURAL JOIN todo WHERE caldav_type = 'VTODO' ";
      $where = "";
      if ( isset( $report[$i]['start'] ) ) {
        $where = "AND (dtend >= ".qpg($report[$i]['start'])."::timestamp with time zone ";
        $where .= "OR calculate_later_timestamp(".qpg($report[$i]['start'])."::timestamp with time zone,dtend,rrule) >= ".qpg($report[$i]['start'])."::timestamp with time zone) ";
      }
      if ( isset( $report[$i]['end'] ) ) {
        $where .= "AND dtstart <= ".qpg($report[$i]['end'])."::timestamp with time zone ";
      }
      $sql .= $where;
      $qry = new PgQuery( $sql );
      if ( $qry->Exec("REPORT",__LINE__,__FILE__) && $qry->rows > 0 ) {
        while( $event = $qry->Fetch() ) {
          $calhref = ( isset($report[$i]['include_href']) ? sprintf( $calendar_href_tpl, $_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT'], $_SERVER['SCRIPT_NAME'], $event->dav_name ) : "" );
          $caldata = ( isset($report[$i]['include_data']) ? sprintf( $calendar_data_tpl, $event->caldav_data ) : "" );
          printf( $response_tpl, $calhref, $event->dav_etag, $caldata );
          dbg_error_log("REPORT", "ETag >>%s<< >>http://%s:%s%s%s<<", $event->dav_etag,
                                $_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT'], $_SERVER['SCRIPT_NAME'], $event->dav_name);
        }
      }
    }
    if ( isset($report[$i]['calendar-freebusy']) ) {
      if ( isset($report[$i]['include_data']) ) dbg_error_log( "REPORT", "FIXME: Not returning full freebusy data" );
    }
  }

echo <<<EOXML
</D:multistatus>
EOXML;

?>