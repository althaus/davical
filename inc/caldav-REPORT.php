<?php

dbg_error_log("REPORT", "method handler");

require_once("XMLElement.php");

$reportnum = -1;
$report = array();
foreach( $xml_tags AS $k => $v ) {

  $fulltag = $v['tag'];
  if ( preg_match('/^(.*):([^:]+)$/', $fulltag, $matches) ) {
    $xmlns = $matches[1];
    $xmltag = $matches[2];
  }
  else {
    $xmlns = 'DAV:';
    $xmltag = $tag;
  }

  switch ( $fulltag ) {

    case 'URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-QUERY':
      dbg_error_log( "REPORT", ":Request: %s -> %s", $v['type'], $xmltag );
      if ( $v['type'] == "open" ) {
        $reportnum++;
        $report[$reportnum]['type'] = $xmltag;
        $report[$reportnum]['include_href'] = 1;
        $report[$reportnum]['include_data'] = 1;
      }
      else {
        unset($report_type);
      }
      break;

    case 'URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-MULTIGET':
      dbg_error_log( "REPORT", ":Request: %s -> %s", $v['type'], $xmltag );
      $report[$reportnum]['multiget'] = 1;
      if ( $v['type'] == "open" ) {
        $reportnum++;
        $report[$reportnum]['type'] = $xmltag;
        $multiget_names = array();
      }
      else if ( $v['type'] == "close" ) {
        $report[$reportnum]['get_names'] = $multiget_names;
        unset($multiget_names);
      }
      break;

    case 'URN:IETF:PARAMS:XML:NS:CALDAV:FILTER':
      dbg_error_log( "REPORT", ":Request: %s -> %s", $v['type'], $xmltag );
      if ( $v['type'] == "open" ) {
        $filters = array();
      }
      else if ( $v['type'] == "close" ) {
        $report[$reportnum]['filters'] = $filters;
        unset($filters);
      }
      break;

    case 'URN:IETF:PARAMS:XML:NS:CALDAV:IS-DEFINED':
    case 'URN:IETF:PARAMS:XML:NS:CALDAV:COMP-FILTER':
      dbg_error_log( "REPORT", ":Request: %s -> %s", $v['type'], $xmltag );
      if ( $v['type'] == "close" ) {
        break;
      }
      if ( $v['type'] == "complete" ) {
        $filter_name = $xmltag;
      }
      else {
        $filter_name = $v['attributes']['NAME'];
      }
      dbg_log_array( "REPORT", "COMP-FILTER", $v, true );
      if ( isset($filters) ) {
        dbg_error_log( "REPORT", "Adding filter '%s'", $filter_name );
        $filters[$filter_name] = 1;
      }
      else {
        dbg_error_log( "ERROR", "Not using COMP-FILTER '%s' outside of defined FILTER!", $filter_name );
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


    case 'URN:IETF:PARAMS:XML:NS:CALDAV:FILTER':
      dbg_error_log( "REPORT", "Not using %s information which follows...", $v['tag'] );
      dbg_log_array( "REPORT", "FILTER", $v, true );
      break;

    case 'DAV::PROP':
      dbg_log_array( "REPORT", "DAV::PROP", $v, true );
      if ( isset($report[$reportnum]['type']) ) {
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

    case 'URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-DATA':
    case 'DAV::GETETAG':
    case 'DAV::GETCONTENTLENGTH':
    case 'DAV::GETCONTENTTYPE':
    case 'DAV::RESOURCETYPE':
      if ( isset($report_properties) ) {
        dbg_error_log( "REPORT", "Adding property '%s'", $xmltag );
        $report_properties[$xmltag] = 1;
      }
      else {
        dbg_error_log( "ERROR", "Not using property '%s' outside of defined report!", $xmltag );
      }
      break;

    case 'DAV::HREF':
      if ( $report[$reportnum]['type'] == 'CALENDAR-MULTIGET' ) {
        $value = preg_replace( "#^.*".$_SERVER['SCRIPT_NAME']."/#", "/", $v['value'] );
        $multiget_names[] = $value;
      }
      else {
        dbg_error_log( "ERROR", "Not using DAV::HREF '%s' for report type '%s'!", $v['value'], $report[$reportnum]['type'] );
      }
      break;

     default:
      $unsupported[$xmltag] = $xmlns;
      dbg_error_log( "REPORT", "Unhandled tag >>%s<<", $fulltag );
  }
}


/**
* Return XML for a single calendar (or todo) entry from the DB
*/
function calendar_to_xml( $properties, $item ) {
  global $session, $c;

  dbg_error_log("REPORT","Building XML Response for item '%s'", $item->dav_name );

  $url = sprintf( "%s://%s:%d%s%s", 'http', $_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT'], $_SERVER['SCRIPT_NAME'], $item->dav_name );
  $prop = new XMLElement("prop");
  if ( isset($properties['GETCONTENTLENGTH']) ) {
    $contentlength = strlen($item->caldav_data);
    $prop->NewElement("getcontentlength", $contentlength );
  }
  if ( isset($properties['CALENDAR-DATA']) ) {
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
    /**
    * FIXME: Fairly basic set of privileges at present.
    */
    if ( $session->AllowedTo("Admin") && preg_match("#/.+/.#", $item->dav_name) ) {
      $privs = array("all");
    }
    else {
      $privs = array("read");
      if ( $session->user_no == $item->user_no || $session->AllowedTo("Admin") ) {
        $privs[] = "write";
      }
    }
    $prop->NewElement("current-user-privilege-set", privileges($privs) );
  }
  $status = new XMLElement("status", "HTTP/1.1 200 OK" );

  $propstat = new XMLElement( "propstat", array( $prop, $status) );
  $href = new XMLElement("href", $url );

  $response = new XMLElement( "response", array($href,$propstat));

  return $response;
}



if ( isset($unsupported) && count($unsupported) > 0 ) {

  /**
  * That's a *BAD* request!
  */

  header('HTTP/1.1 403 Forbidden');
  header('Content-Type: application/xml; charset="utf-8"');

  $badprops = new XMLElement( "prop" );
  foreach( $unsupported AS $k => $v ) {
    dbg_error_log("ERROR", " REPORT: Support for $v::$k properties is not implemented yet");
    $badprops->NewElement(strtolower($k),false,array("xmlns" => strtolower($v)));
  }
  $error = new XMLElement("error", new XMLElement( "propfind",$badprops), array("xmlns" => "DAV:") );

  echo $error->Render(0,'<?xml version="1.0" ?>');

  exit(0);
}
else {

  /**
  * Something that we can handle, at least roughly correctly.
  */

  $responses = array();

  for ( $i=0; $i <= $reportnum; $i++ ) {
//    dbg_error_log("REPORT", "Report[%d] Start:%s, End: %s, Events: %d, Todos: %d, Freebusy: %d",
//         $i, $report[$i]['start'], $report[$i]['end'], $report[$i]['filters']['VEVENT'], $report[$i]['filters']['VTODO'], $report[$i]['filters']['VFREEBUSY']);

    $where = "";
    switch( $report[$i]['type'] ) {
      case 'CALENDAR-QUERY':
        if ( isset( $report[$i]['start'] ) ) {
          $where = "AND (dtend >= ".qpg($report[$i]['start'])."::timestamp with time zone ";
          $where .= "OR calculate_later_timestamp(".qpg($report[$i]['start'])."::timestamp with time zone,dtend,rrule) >= ".qpg($report[$i]['start'])."::timestamp with time zone) ";
        }
        if ( isset( $report[$i]['end'] ) ) {
          $where .= "AND dtstart <= ".qpg($report[$i]['end'])."::timestamp with time zone ";
        }
        break;

      case 'CALENDAR-MULTIGET':
        $href_in = '';
        foreach( $report[$reportnum]['get_names'] AS $k => $v ) {
          dbg_error_log("REPORT", "Reporting on href '%s'", $v );
          $href_in .= ($href_in == '' ? '' : ', ');
          $href_in .= qpg($v);
        }
        if ( $href_in != "" ) {
          $where .= " AND caldav_data.dav_name IN ( $href_in ) ";
        }
        break;

      default:
        dbg_error_log("REPORT", "Unhandled report type of '%s'", $report[$i]['type'] );
    }

    $type_filters = '';
    if ( isset($report[$i]['filters']['VEVENT']) ) {
      $type_filters .= ($type_filters == '' ? '' : ', ');
      $type_filters .= qpg('VEVENT');
    }
    if ( isset($report[$i]['filters']['VTODO']) ) {
      $type_filters .= ($type_filters == '' ? '' : ', ');
      $type_filters .= qpg('VTODO');
    }
    if ( $type_filters != '' ) {
      $where .= " AND caldav_data.caldav_type IN ( $type_filters ) ";
    }
    if ( $where != '' ) {
      $where = preg_replace( '#^\s*(AND|OR) #i', ' WHERE ', $where);
    }

    $qry = new PgQuery( "SELECT * FROM caldav_data INNER JOIN calendar_item USING(user_no, dav_name)". $where );
    if ( $qry->Exec("REPORT",__LINE__,__FILE__) && $qry->rows > 0 ) {
      while( $calendar_object = $qry->Fetch() ) {
        $responses[] = calendar_to_xml( $report[$i]['properties'], $calendar_object );
      }
    }
  }
}
$multistatus = new XMLElement( "multistatus", $responses, array('xmlns'=>'DAV:') );

$xmldoc = $multistatus->Render();
$etag = md5($xmldoc);

header("HTTP/1.1 207 Multi-Status");
header("Content-type: text/xml;charset=UTF-8");
header("ETag: \"$etag\"");

echo'<?xml version="1.0" encoding="UTF-8" ?>'."\n";
echo $xmldoc;

?>