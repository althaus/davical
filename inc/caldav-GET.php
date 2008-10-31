<?php
/**
* CalDAV Server - handle GET method
*
* @package   davical
* @subpackage   caldav
* @author    Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Catalyst .Net Ltd, Morphoss Ltd <http://www.morphoss.com/>
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("get", "GET method handler");

if ( ! $request->AllowedTo('read') ) {
  $request->DoResponse( 403, translate("You may not access that calendar") );
}
$privacy_clause = "";
if ( ! $request->AllowedTo('all') ) {
  $privacy_clause = "AND (calendar_item.class != 'PRIVATE' OR calendar_item.class IS NULL) ";
}

if ( $request->IsCollection() ) {
  /**
  * The CalDAV specification does not define GET on a collection, but typically this is
  * used as a .ics download for the whole collection, which is what we do also.
  */
  $order_clause = ( isset($c->strict_result_ordering) && $c->strict_result_ordering ? " ORDER BY dav_id" : "");
  $qry = new PgQuery( "SELECT caldav_data, class, caldav_type, calendar_item.user_no FROM caldav_data INNER JOIN calendar_item USING ( dav_id ) WHERE caldav_data.user_no = ? AND caldav_data.dav_name ~ ? $privacy_clause $order_clause", $request->user_no, $request->path.'[^/]+$');
}
else {
  $qry = new PgQuery( "SELECT caldav_data, caldav_data.dav_etag, class, caldav_type, calendar_item.user_no FROM caldav_data INNER JOIN calendar_item USING ( dav_id ) WHERE caldav_data.user_no = ? AND caldav_data.dav_name = ?  $privacy_clause;", $request->user_no, $request->path);
}

if ( !$qry->Exec("GET") ) {
  $request->DoResponse( 500, translate("Database Error") );
}
else if ( $qry->rows == 1 ) {
  $event = $qry->Fetch();
  header( "Etag: \"$event->dav_etag\"" );
  header( "Content-Length: ".strlen($event->caldav_data) );
  $request->DoResponse( 200, ($request->method == "HEAD" ? "" : $event->caldav_data), "text/calendar" );
}
else if ( $qry->rows < 1 && ! $request->IsCollection() ) {
  $request->DoResponse( 404, translate("Calendar Resource Not Found.") );
}
else {
  /**
  * Here we are constructing a whole calendar response for this collection, including
  * the timezones that are referred to by the events we have selected.
  */
  include_once("iCalendar.php");
  $response = iCalendar::iCalHeader();

  /**
  * @TODO: CalDAVRequest should have read the collection record, so we should not have to reread it here
  * @TODO: This should be structured to not use the iCalHeader() and iCalFooter methods.  See caldav-POST.php for the bones of a better approach.
  */
  $collqry = new PgQuery( "SELECT * FROM collection WHERE collection.user_no = ? AND collection.dav_name = ?;", $request->user_no, $request->path);
  if ( $collqry->Exec("GET") && $collection = $collqry->Fetch() ) {
    $response .= "X-WR-CALNAME:$collection->dav_displayname\r\n";
  }
  $timezones = array();
  while( $event = $qry->Fetch() ) {
    $ical = new iCalendar( array( "icalendar" => $event->caldav_data ) );
    $timezones[$ical->Get("TZID")] = 1;

    if ( !$request->AllowedTo('all') && $session->user_no != $event->user_no ){
      // the user is not admin / owner of this calendarlooking at his calendar and can not admin the other cal
      if ( $event->class == 'CONFIDENTIAL' ) {
        // if the event is confidential we fake one that just says "Busy"
        $confidential = new iCalendar( array(
                              'SUMMARY' => translate('Busy'), 'CLASS' => 'CONFIDENTIAL',
                              'DTSTART'  => $ical->Get('DTSTART')
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
        $response .= $confidential->Render( false, $event->caldav_type );
      }
      elseif ( isset($c->hide_alarm) && $c->hide_alarm ) {
        // Otherwise we hide the alarms (if configured to)
        $ical->component->ClearComponents('VALARM');
        $response .= $ical->render(true, $event->caldav_type );
      }
      else {
        $response .= $ical->Render( false, $event->caldav_type );
      }
    }
    else {
      $response .= $ical->Render( false, $event->caldav_type );
    }
  }
  $tzid_in = "";
  foreach( $timezones AS $tzid => $v ) {
    $tzid_in .= ($tzid_in == '' ? '' : ', ');
    $tzid_in .= qpg($tzid);
  }
  if ( $tzid_in != "" ) {
    $qry = new PgQuery("SELECT tz_spec FROM time_zone WHERE tz_id IN ($tzid_in) ORDER BY tz_id;");
    if ( $qry->Exec("GET") ) {
      while( $tz = $qry->Fetch() ) {
        $response .= $tz->tz_spec;
      }
    }
  }
  $response .= iCalendar::iCalFooter();
  header( "Content-Length: ".strlen($response) );
  $request->DoResponse( 200, ($request->method == "HEAD" ? "" : $response), "text/calendar" );
}

