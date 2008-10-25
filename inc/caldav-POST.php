<?php
/**
* CalDAV Server - handle PUT method
*
* @package   davical
* @subpackage   caldav
* @author    Andrew McMillan <andrew@morphoss.com>
* @copyright Morphoss Ltd - http://www.morphoss.com/
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("POST", "method handler");

require_once("XMLDocument.php");
require_once("iCalendar.php");
include_once("RRule.php");

if ( ! $request->AllowedTo("CALDAV:schedule-send-freebusy")
  && ! $request->AllowedTo("CALDAV:schedule-send-invite")
  && ! $request->AllowedTo("CALDAV:schedule-send-reply") ) {
  // $request->DoResponse(403);
  dbg_error_log( "WARN", ": POST: permissions not yet checked" );
}

if ( ! ini_get('open_basedir') && (isset($c->dbg['ALL']) || $c->dbg['post']) ) {
  $fh = fopen('/tmp/POST.txt','w');
  if ( $fh ) {
    fwrite($fh,$request->raw_post);
    fclose($fh);
  }
}


function handle_freebusy_request( $ic ) {
  global $c, $session, $request;

  $reply = new XMLDocument( array("DAV:" => "", "urn:ietf:params:xml:ns:caldav" => "C" ) );
  $responses = array();

  $fbq_start = $ic->Get('DTSTART');
  $fbq_end   = $ic->Get('DTEND');
  $component =& $ic->component->FirstNonTimezone();
  $attendees = $component->GetProperties('ATTENDEE');

  $fb_response_template = new iCalendar( array( 'DTSTAMP'   => date('Ymd\THis\Z'),
                                                'DTSTART'   => $fbq_start,
                                                'DTEND'     => $fbq_end,
                                                'UID'       => $ic->Get('UID'),
                                                'ORGANIZER' => $ic->Get('ORGANIZER'),
                                                'type'    => 'VFREEBUSY' ) );
  $fb_response_template->component->AddProperty( new iCalProp("METHOD:REPLY") );

  foreach( $attendees AS $k => $attendee ) {
    $attendee_email = preg_replace( '/^mailto:/', '', $attendee->Value() );
    if ( ! ( isset($fbq_start) || isset($fbq_end) ) ) {
      $request->DoResponse( 400, 'All valid freebusy requests MUST contain a DTSTART and a DTEND' );
    }
    $where = " WHERE usr.email = ? AND collection.is_calendar ";
    if ( isset( $fbq_start ) ) {
      $where .= "AND (dtend >= ".qpg($fbq_start)."::timestamp with time zone ";
      $where .= "OR calculate_later_timestamp(".qpg($fbq_start)."::timestamp with time zone,dtend,rrule) >= ".qpg($fbq_start)."::timestamp with time zone) ";
    }
    if ( isset( $fbq_end ) ) {
      $where .= "AND dtstart <= ".qpg($fbq_end)."::timestamp with time zone ";
    }
    $where .= "AND caldav_data.caldav_type IN ( 'VEVENT', 'VFREEBUSY' ) ";
    $where .= "AND (calendar_item.transp != 'TRANSPARENT' OR calendar_item.transp IS NULL) ";
    $where .= "AND (calendar_item.status != 'CANCELLED' OR calendar_item.status IS NULL) ";

    /**
    * @TODO Some significant permissions need to be added around the visibility of free/busy
    *       but lets get it working first...
    */
    $where .= "AND (calendar_item.class != 'PRIVATE' OR calendar_item.class IS NULL) ";

    $busy = array();
    $busy_tentative = array();
    $sql = "SELECT caldav_data.caldav_data, calendar_item.rrule, calendar_item.transp, calendar_item.status, ";
    $sql .= "to_char(calendar_item.dtstart at time zone 'GMT',".iCalendar::SqlDateFormat().") AS start, ";
    $sql .= "to_char(calendar_item.dtend at time zone 'GMT',".iCalendar::SqlDateFormat().") AS finish ";
    $sql .= "FROM usr INNER JOIN collection USING (user_no) INNER JOIN caldav_data USING (collection_id) INNER JOIN calendar_item USING(dav_id)".$where;
    if ( isset($c->strict_result_ordering) && $c->strict_result_ordering ) $sql .= " ORDER BY dav_id";
    $qry = new PgQuery( $sql, $attendee_email );
    if ( $qry->Exec("POST",__LINE__,__FILE__) && $qry->rows > 0 ) {
      while( $calendar_object = $qry->Fetch() ) {
        if ( $calendar_object->transp != "TRANSPARENT" ) {
          switch ( $calendar_object->status ) {
            case "TENTATIVE":
              dbg_error_log( "POST", " FreeBusy: tentative appointment: %s, %s", $calendar_object->start, $calendar_object->finish );
              $busy_tentative[] = $calendar_object;
              break;

            case "CANCELLED":
              // Cancelled events are ignored
              break;

            default:
              dbg_error_log( "POST", " FreeBusy: Not transparent, tentative or cancelled: %s, %s", $calendar_object->start, $calendar_object->finish );
              $busy[] = $calendar_object;
              break;
          }
        }
      }
    }


    $i = 0;
    $fbparams = array( "FBTYPE" => "BUSY-TENTATIVE" );
    $fb = clone($fb_response_template);
    $fb->Add( $attendee->Name(), $attendee->Value(), $attendee->parameters );
    foreach( $busy_tentative AS $k => $v ) {
      $start = new iCalDate($v->start);
      $duration = $start->DateDifference($v->finish);
      if ( $v->rrule != "" ) {
        $rrule = new RRule( $start, $v->rrule );
        while ( $date = $rrule->GetNext() ) {
          if ( ! $date->GreaterThan($fbq_start) ) continue;
          if ( $date->GreaterThan($fbq_end) ) break;
          $todate = clone($date);
          $todate->AddDuration($duration);
          $fb->Add("FREEBUSY", sprintf("%s/%s", $date->Render('Ymd\THis'), $todate->Render('Ymd\THis') ), $fbparams );
        }
      }
      else {
        $fb->Add("FREEBUSY;FBTYPE=BUSY-TENTATIVE",sprintf("%s/%s", $start->Render('Ymd\THis'), $v->finish ), $fbparams );
      }
    }

    foreach( $busy AS $k => $v ) {
      $start = new iCalDate($v->start);
      $duration = $start->DateDifference($v->finish);
      if ( $v->rrule != "" ) {
        $rrule = new RRule( $start, $v->rrule );
        while ( $date = $rrule->GetNext() ) {
          if ( ! $date->GreaterThan($fbq_start) ) continue;
          if ( $date->GreaterThan($fbq_end) ) break;
          $todate = clone($date);
          $todate->AddDuration($duration);
          $fb->Add("FREEBUSY", sprintf("%s/%s", $date->Render('Ymd\THis'), $todate->Render('Ymd\THis') ) );
        }
      }
      else {
        $fb->Add("FREEBUSY", sprintf("%s/%s", $start->Render('Ymd\THis'), $v->finish ) );
      }
    }

    $response = new XMLElement( $reply->Caldav("response") );
    $response->NewElement( $reply->Caldav("recipient"), new XMLElement("href",$attendee->Value()) );
    $response->NewElement( $reply->Caldav("request-status"), "2.0;Success" );  // Cargo-cult setting
    $response->NewElement( $reply->Caldav("calendar-data"), $fb->Render() );
    $responses[] = $response;
  }

  $response = new XMLElement( "schedule-response", $responses, $reply->GetXmlNsArray() );
  $request->DoResponse( 200, $response->Render() );
}


$ical = new iCalendar( array('icalendar' => $request->raw_post) );
$calendar_properties = $ical->component->GetProperties('METHOD');
$method =  $calendar_properties[0]->Value();
switch ( $method ) {
  case 'REQUEST':
    dbg_error_log("POST", ": Handling iTIP 'REQUEST' method.", $method );
    handle_freebusy_request( $ical );
    break;

  default:
    dbg_error_log("POST", ": Unhandled '%s' method in request.", $method );
}
