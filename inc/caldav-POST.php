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
include_once('caldav-PUT-functions.php');

if ( ! $request->AllowedTo("CALDAV:schedule-send-freebusy")
  && ! $request->AllowedTo("CALDAV:schedule-send-invite")
  && ! $request->AllowedTo("CALDAV:schedule-send-reply") ) {
  // $request->DoResponse(403);
  dbg_error_log( "WARN", ": POST: permissions not yet checked" );
}

if ( ! ini_get('open_basedir') && (isset($c->dbg['ALL']) || isset($c->dbg['post'])) ) {
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

  $fbq_start = $ic->GetPValue('DTSTART');
  $fbq_end   = $ic->GetPValue('DTEND');
  $attendees = $ic->GetProperties('ATTENDEE');
  if ( preg_match( '# iCal/\d#', $_SERVER['HTTP_USER_AGENT']) ) {
    dbg_error_log( "POST", "Non-compliant iCal request.  Using X-WR-ATTENDEE property" );
    $wr_attendees = $ic->GetProperties('X-WR-ATTENDEE');
    foreach( $wr_attendees AS $k => $v ) {
      $attendees[] = $v;
    }
  }
  dbg_error_log( "POST", "Responding with free/busy for %d attendees", count($attendees) );

  foreach( $attendees AS $k => $attendee ) {
    $attendee_email = preg_replace( '/^mailto:/', '', $attendee->Value() );
    dbg_error_log( "POST", "Calculating free/busy for %s", $attendee_email );
    if ( ! ( isset($fbq_start) || isset($fbq_end) ) ) {
      $request->DoResponse( 400, 'All valid freebusy requests MUST contain a DTSTART and a DTEND' );
    }

    /** @TODO: Refactor this so we only do one query here and loop through the results */
    $qry = new PgQuery("SELECT pprivs(?::int8,principal_id,?::int) AS p FROM usr JOIN principal USING(user_no) WHERE lower(usr.email) = lower(?)", $session->principal_id, $c->permission_scan_depth, $attendee_email );
    if ( !$qry->Exec("POST") ) $request->DoResponse( 501, 'Database error');
    if ( $qry->rows > 1 ) {
      // Unlikely, but if we get more than one result we'll do an exact match instead.
      $qry = new PgQuery("SELECT pprivs(?::int8,principal_id,?::int) AS p FROM usr JOIN principal USING(user_no) WHERE usr.email = ?", $session->principal_id, $c->permission_scan_depth, $attendee_email );
      if ( !$qry->Exec("POST") ) $request->DoResponse( 501, 'Database error');
    }

    $response = $reply->NewXMLElement("response", false, false, 'urn:ietf:params:xml:ns:caldav');
    $reply->CalDAVElement($response, "recipient", $reply->href($attendee->Value()) );

    if ( $qry->rows == 0 ) {
      $reply->CalDAVElement($response, "request-status", "3.7;Invalid Calendar User" );
      $reply->CalDAVElement($response, "calendar-data" );
      $responses[] = $response;
      continue;
    }
    if ( ! $userperms = $qry->Fetch() ) $request->DoResponse( 501, 'Database error');
    if ( (privilege_to_bits('schedule-query-freebusy') & bindec($userperms->p)) == 0 ) {
      $reply->CalDAVElement($response, "request-status", "3.8;No authority" );
      $reply->CalDAVElement($response, "calendar-data" );
      $responses[] = $response;
      continue;
    }

    // If we make it here, then it seems we are allowed to see their data...
    $where = " WHERE usr.email = ? AND collection.is_calendar ";
    if ( isset( $fbq_start ) || isset( $fbq_end ) ) {
      $where .= "AND rrule_event_overlaps( dtstart, dtend, rrule, ".qpg($fbq_start).", ".qpg($fbq_end)." ) ";
    }
    $where .= "AND caldav_data.caldav_type IN ( 'VEVENT', 'VFREEBUSY' ) ";
    $where .= "AND (calendar_item.transp != 'TRANSPARENT' OR calendar_item.transp IS NULL) ";
    $where .= "AND (calendar_item.status != 'CANCELLED' OR calendar_item.status IS NULL) ";

    /**
    * @todo Some significant permissions need to be added around the visibility of free/busy
    *       but lets get it working first...
    */
    $where .= "AND (calendar_item.class != 'PRIVATE' OR calendar_item.class IS NULL) ";

    $busy = array();
    $busy_tentative = array();
    /** @TODO prove this is correct */
    $sql = "SELECT caldav_data.caldav_data, calendar_item.rrule, calendar_item.transp, calendar_item.status, ";
    $sql .= "to_char(calendar_item.dtstart at time zone 'GMT',".iCalendar::SqlUTCFormat().") AS start, ";
    $sql .= "to_char(calendar_item.dtend at time zone 'GMT',".iCalendar::SqlUTCFormat().") AS finish ";
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

    $fb = new iCalComponent();
    $fb->AddProperty( 'DTSTAMP',   gmdate('Ymd\THis\Z') );
    $fb->AddProperty( 'DTSTART',   $fbq_start );
    $fb->AddProperty( 'DTEND',     $fbq_end );
    $fb->AddProperty( 'UID',       $ic->GetPValue('UID') );
    $fb->SetProperties( $ic->GetProperties('ORGANIZER'), 'ORGANIZER');
    $fb->SetType('VFREEBUSY');

    $fb->AddProperty( $attendee );
    $fbparams = array( "FBTYPE" => "BUSY-TENTATIVE" );
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
          $fb->AddProperty("FREEBUSY", sprintf("%s/%s", $date->RenderGMT(), $todate->RenderGMT() ), $fbparams);
        }
      }
      else {
        $finish = new iCalDate($v->finish);
        $fb->AddProperty("FREEBUSY", sprintf("%s/%s", $start->RenderGMT(), $finish->RenderGMT() ), $fbparams );
      }
    }

    $fbparams = array( "FBTYPE" => "BUSY" );
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
          $fb->AddProperty("FREEBUSY", sprintf("%s/%s", $date->RenderGMT(), $todate->RenderGMT() ), $fbparams );
        }
      }
      else {
        $finish = new iCalDate($v->finish);
        $fb->AddProperty("FREEBUSY", sprintf("%s/%s", $start->RenderGMT(), $finish->RenderGMT() ), $fbparams );
      }
    }

    $vcal = new iCalComponent();
    $vcal->VCalendar( array('METHOD' => 'REPLY') );
    $vcal->AddComponent( $fb );

    $response = $reply->NewXMLElement( "response", false, false, 'urn:ietf:params:xml:ns:caldav' );
    $reply->CalDAVElement($response, "recipient", $reply->href($attendee->Value()) );
    $reply->CalDAVElement($response, "request-status", "2.0;Success" );  // Cargo-cult setting
    $reply->CalDAVElement($response, "calendar-data", $vcal->Render() );
    $responses[] = $response;
  }

  $response = $reply->NewXMLElement( "schedule-response", $responses, $reply->GetXmlNsArray(), 'urn:ietf:params:xml:ns:caldav' );
  $request->XMLResponse( 200, $response );
}


function handle_cancel_request( $ic ) {
  global $c, $session, $request;

  $reply = new XMLDocument( array("DAV:" => "", "urn:ietf:params:xml:ns:caldav" => "C" ) );

  $responses[] = $reply->NewXMLElement( "response", false, false, 'urn:ietf:params:xml:ns:caldav' );
  $reply->CalDAVElement($response, "request-status", "2.0;Success" );  // Cargo-cult setting
  $response = $reply->NewXMLElement( "schedule-response", $responses, $reply->GetXmlNsArray() );
  $request->XMLResponse( 200, $response );
}

$ical = new iCalComponent( $request->raw_post );
$method =  $ical->GetPValue('METHOD');

$resources = $ical->GetComponents('VTIMEZONE',false);
$first = $resources[0];
switch ( $method ) {
  case 'REQUEST':
    dbg_error_log('POST', 'Handling iTIP "REQUEST" method with "%s" component.', $method, $first->GetType() );
    if ( $first->GetType() == 'VFREEBUSY' )
      handle_freebusy_request( $first );
    elseif ( $first->GetType() == 'VEVENT' ) {
      handle_schedule_request( $ical );
    }
    else {
      dbg_error_log('POST', 'Ignoring iTIP "REQUEST" with "%s" component.', $first->GetType() );
    }
    break;
  case 'REPLY':
    dbg_error_log('POST', 'Handling iTIP "REPLY" with "%s" component.', $first->GetType() );
    handle_schedule_reply ( $ical );
    break;

  case 'CANCEL':
    dbg_error_log("POST", "Handling iTIP 'CANCEL'  method.", $method );
    handle_cancel_request( $first );
    break;

  default:
    dbg_error_log("POST", "Unhandled '%s' method in request.", $method );
}
