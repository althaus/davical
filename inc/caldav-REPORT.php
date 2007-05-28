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
  // The specification states that a lack of privileges MUST result in a 404. Caldav-15, Section 7.10, p66
  $request->DoResponse( 404 );
}


require_once("XMLElement.php");
require_once("iCalendar.php");
/**
* Free/Busy is different to the other responses (not XML) so we
* deal with it separately
*/
$free_busy_query = false;

$reportnum = -1;
$report = array();
foreach( $request->xml_tags AS $k => $v ) {

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

    case 'URN:IETF:PARAMS:XML:NS:CALDAV:FREE-BUSY-QUERY':
      dbg_error_log( "REPORT", ":Request: %s -> %s", $v['type'], $xmltag );
      $free_busy_query = true;
      if ( $v['type'] == "open" ) {
        $reportnum++;
        $report[$reportnum]['type'] = $xmltag;
        $report[$reportnum]['include_href'] = 1;
        $report[$reportnum]['include_data'] = 1;
      }
      break;

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
      $filter_name = $v['attributes']['NAME'];
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
        $caldav_data = $ical->render(true,$item->caldav_type, array( "uid", "dtstamp", "dtstart", "duration", "last-modified","class", "transp", "sequence", "due",'SUMMARY') );
        $prop->NewElement("calendar-data","$caldav_data" , array("xmlns" => "urn:ietf:params:xml:ns:caldav") );
      }
      elseif ( $c->hide_alarm ) {
        // Otherwise we hide the alarms (if configured to)
        $ical = new iCalendar( array( "icalendar" => $item->caldav_data) );
        $caldav_data = $ical->render(true,$item->caldav_type,$ical->get_default_properties() );
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

if ( $free_busy_query ) {
  include_once("iCalendar.php");
  include_once("RRule.php");

  if ( ! ( isset($report[0]['start']) || isset($report[0]['end']) ) ) {
    $request->DoResponse( 400, 'All valid freebusy requests MUST contain a time-range filter' );
  }
  $where = " WHERE caldav_data.dav_name ~ ? ";
  if ( isset( $report[0]['start'] ) ) {
    $where .= "AND (dtend >= ".qpg($report[0]['start'])."::timestamp with time zone ";
    $where .= "OR calculate_later_timestamp(".qpg($report[0]['start'])."::timestamp with time zone,dtend,rrule) >= ".qpg($report[0]['start'])."::timestamp with time zone) ";
  }
  if ( isset( $report[0]['end'] ) ) {
    $where .= "AND dtstart <= ".qpg($report[0]['end'])."::timestamp with time zone ";
  }
  $where .= "AND caldav_data.caldav_type IN ( 'VEVENT', 'VFREEBUSY' ) ";
  $where .= "AND (calendar_item.transp != 'TRANSPARENT' OR calendar_item.transp IS NULL) ";
  $where .= "AND (calendar_item.status != 'CANCELLED' OR calendar_item.status IS NULL) ";
  $where .= "AND (calendar_item.class != 'PRIVATE' OR calendar_item.class IS NULL OR get_permissions($session->user_no,caldav_data.user_no) ~ 'A') "; // Must have 'all' permissions to see confidential items

  $busy = array();
  $busy_tentative = array();
  $sql = "SELECT caldav_data.caldav_data, calendar_item.rrule, calendar_item.transp, calendar_item.status, ";
  $sql .= "to_char(calendar_item.dtstart at time zone 'GMT',".iCalendar::SqlDateFormat().") AS start, ";
  $sql .= "to_char(calendar_item.dtend at time zone 'GMT',".iCalendar::SqlDateFormat().") AS finish ";
  $sql .= "FROM caldav_data INNER JOIN calendar_item USING(user_no, dav_name)".$where." ORDER BY dtstart, dtend";
  $qry = new PgQuery( $sql, "^".$request->path.$request->DepthRegexTail() );
  if ( $qry->Exec("REPORT",__LINE__,__FILE__) && $qry->rows > 0 ) {
    while( $calendar_object = $qry->Fetch() ) {
      if ( $calendar_object->transp != "TRANSPARENT" ) {
        switch ( $calendar_object->status ) {
          case "TENTATIVE":
            dbg_error_log( "REPORT", " FreeBusy: tentative appointment: %s, %s", $calendar_object->start, $calendar_object->finish );
            $busy_tentative[] = $calendar_object;
            break;

          case "CANCELLED":
            // Cancelled events are ignored
            break;

          default:
            dbg_error_log( "REPORT", " FreeBusy: Not transparent, tentative or cancelled: %s, %s", $calendar_object->start, $calendar_object->finish );
            $busy[] = $calendar_object;
            break;
        }
      }
    }
  }
  $freebusy = iCalendar::iCalHeader();
  $freebusy .= sprintf("BEGIN:VFREEBUSY\nDTSTAMP:%s\nDTSTART:%s\nDTEND:%s\n", date('Ymd\THis\Z'), $report[0]['start'], $report[0]['end']);

  foreach( $busy_tentative AS $k => $v ) {
    $start = new iCalDate($v->start);
    $duration = $start->DateDifference($v->finish);
    if ( $v->rrule != "" ) {
      $rrule = new RRule( $start, $v->rrule );
      while ( $date = $rrule->GetNext() ) {
        if ( ! $date->GreaterThan($report[0]['start']) ) continue;
        if ( $date->GreaterThan($report[0]['end']) ) break;
        $freebusy .= sprintf("FREEBUSY;FBTYPE=BUSY-TENTATIVE:%s/%s\n", $date->Render('Ymd\THis'), $duration );
      }
    }
    else {
      $freebusy .= sprintf("FREEBUSY;FBTYPE=BUSY-TENTATIVE:%s/%s\n", $start->Render('Ymd\THis'), $duration );
    }
  }

  foreach( $busy AS $k => $v ) {
    $start = new iCalDate($v->start);
    $duration = $start->DateDifference($v->finish);
    if ( $v->rrule != "" ) {
      $rrule = new RRule( $start, $v->rrule );
      while ( $date = $rrule->GetNext() ) {
        if ( ! $date->GreaterThan($report[0]['start']) ) continue;
        if ( $date->GreaterThan($report[0]['end']) ) break;
        $freebusy .= sprintf("FREEBUSY:%s/%s\n", $date->Render('Ymd\THis'), $duration );
      }
    }
    else {
      $freebusy .= sprintf("FREEBUSY:%s/%s\n", $start->Render('Ymd\THis'), $duration );
    }
  }

  $freebusy .= "END:VFREEBUSY\n";
  $freebusy .= iCalendar::iCalFooter();
  $request->DoResponse( 200, $freebusy, 'text/calendar' );
  // Won't return from that
}
else {
  // Must have read privilege for other reports
  if ( ! ($request->AllowedTo('read') ) ) $request->DoResponse( 403, translate("You may not access that calendar") );
}

$request->UnsupportedRequest($unsupported); // Won't return if there was unsupported stuff.

/**
* Something that we can handle, at least roughly correctly.
*/

$responses = array();

for ( $i=0; $i <= $reportnum; $i++ ) {

  $where = " WHERE caldav_data.dav_name ~ ".qpg("^".$request->path)." ";
  switch( $report[$i]['type'] ) {
    case 'CALENDAR-QUERY':
      if ( ! ($request->AllowedTo('read') ) ) $request->DoResponse( 403, translate("You may not access that calendar") );
      if ( isset( $report[$i]['start'] ) ) {
        $where .= "AND (dtend >= ".qpg($report[$i]['start'])."::timestamp with time zone ";
        $where .= "OR calculate_later_timestamp(".qpg($report[$i]['start'])."::timestamp with time zone,dtend,rrule) >= ".qpg($report[$i]['start'])."::timestamp with time zone) ";
      }
      if ( isset( $report[$i]['end'] ) ) {
        $where .= "AND dtstart <= ".qpg($report[$i]['end'])."::timestamp with time zone ";
      }
      break;

    case 'CALENDAR-MULTIGET':
      if ( ! ($request->AllowedTo('read') ) ) $request->DoResponse( 403, translate("You may not access that calendar") );
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

  if ( isset( $report[$i]['filters'] ) ) {
    /**
    * Only report on the filtered types that were specified
    */
    $filters = "";
    foreach( $report[$i]['filters'] AS $k => $v ) {
      $filters .= ($filters == "" ? "" : ", ") . qpg($k);
    }
    if ( $filters != "" ) {
      $where .= "AND caldav_data.caldav_type IN ( $filters ) ";
    }
  }

  $where .= "AND (calendar_item.class != 'PRIVATE' OR calendar_item.class IS NULL OR get_permissions($session->user_no,caldav_data.user_no) ~ 'A') "; // Must have 'all' permissions to see confidential items
  $qry = new PgQuery( "SELECT * , get_permissions($session->user_no,caldav_data.user_no) as permissions FROM caldav_data INNER JOIN calendar_item USING(user_no, dav_name)". $where );
  if ( $qry->Exec("REPORT",__LINE__,__FILE__) && $qry->rows > 0 ) {
    while( $calendar_object = $qry->Fetch() ) {
      $responses[] = calendar_to_xml( $report[$i]['properties'], $calendar_object );
    }
  }
}
$multistatus = new XMLElement( "multistatus", $responses, array('xmlns'=>'DAV:') );

$xmldoc = $multistatus->Render(0,'<?xml version="1.0" encoding="utf-8" ?>');
$etag = md5($xmldoc);
header("ETag: \"$etag\"");
$request->DoResponse( 207, $xmldoc, 'text/xml; charset="utf-8"' );

?>