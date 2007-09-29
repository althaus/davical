<?php
/**
* CalDAV Server - handle GET method
*
* @package   rscds
* @subpackage   caldav
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("get", "GET method handler");

if ( ! $request->AllowedTo('read') ) {
  $request->DoResponse( 403, translate("You may not access that calendar") );
}
$privacy_clause = "";
if ( ! $request->AllowedTo('all') ) {
  $privacy_clause = "AND calendar_item.class != 'PRIVATE'";
}

if ( $request->IsCollection() ) {
  /**
  * The CalDAV specification does not define GET on a collection, but typically this is
  * used as a .ics download for the whole collection, which is what we do also.
  */
  $qry = new PgQuery( "SELECT caldav_data, class, caldav_type, calendar_item.user_no, get_permissions($session->user_no,caldav_data.user_no) as permissions FROM caldav_data LEFT JOIN calendar_item USING ( dav_name ) WHERE caldav_data.user_no = ? AND caldav_data.dav_name ~ ? $privacy_clause ORDER BY caldav_data.user_no, caldav_data.dav_name, caldav_data.created;", $request->user_no, $request->path.'[^/]+$');
}
else {
  $qry = new PgQuery( "SELECT caldav_data, caldav_data.dav_etag, class, caldav_type, calendar_item.user_no, get_permissions($session->user_no,caldav_data.user_no) as permissions FROM caldav_data LEFT JOIN calendar_item USING ( dav_name ) WHERE caldav_data.user_no = ? AND caldav_data.dav_name = ?  $privacy_clause;", $request->user_no, $request->path);
}
dbg_error_log("get", "%s", $qry->querystring );
if ( $qry->Exec("GET") && $qry->rows == 1 ) {
  $event = $qry->Fetch();
  header( "Etag: \"$event->dav_etag\"" );
  header( "Content-Length: ".strlen($event->caldav_data) );
  $request->DoResponse( 200, ($request->method == "HEAD" ? "" : $event->caldav_data), "text/calendar" );
}
else if ( $qry->rows < 1 ) {
  $request->DoResponse( 404, translate("Calendar Resource Not Found.") );
}
else if ( $qry->rows > 1 ) {
  /**
  * Here we are constructing a whole calendar response for this collection, including
  * the timezones that are referred to by the events we have selected.
  */
  include_once("iCalendar.php");
  $response = iCalendar::iCalHeader();
  $timezones = array();
  while( $event = $qry->Fetch() ) {
    $ical = new iCalendar( array( "icalendar" => $event->caldav_data ) );
    if ( isset($ical->tz_locn) && $ical->tz_locn != "" && isset($ical->vtimezone) && $ical->vtimezone != "" ) {
      $timezones[$ical->Get("TZID")] = $ical->vtimezone;
    }


    if ( !is_numeric(strpos($event->permissions,'A')) && $session->user_no != $event->user_no ){
      // the user is not admin / owner of this calendarlooking at his calendar and can not admin the other cal
      if ( $event->class == 'CONFIDENTIAL' ) {
        // if the event is confidential we fake one that just says "Busy"
        $displayname = translate("Busy");
        $ical->Put( 'SUMMARY', $displayname );
        $response .= $ical->Render(false, $event->caldav_type, $ical->DefaultPropertyList() );
      }
      elseif ( $c->hide_alarm ) {
        // Otherwise we hide the alarms (if configured to)
        $response .= $ical->Render(false, $event->caldav_type, $ical->DefaultPropertyList() );
      }
    } else
        $response .= $ical->JustThisBitPlease("VEVENT");
  }
  foreach( $timezones AS $tzid => $vtimezone ) {
    $response .= $vtimezone;
  }
  $response .= iCalendar::iCalFooter();
  header( "Content-Length: ".strlen($response) );
  $request->DoResponse( 200, ($request->method == "HEAD" ? "" : $response), "text/calendar" );
}
else {
  $request->DoResponse( 500, translate("Database Error") );
}

?>