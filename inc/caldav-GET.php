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

if ( $request->IsCollection() ) {
  /**
  * The CalDAV specification does not define GET on a collection, but typically this is
  * used as a .ics download for the whole collection, which is what we do also.
  */
  $qry = new PgQuery( "SELECT caldav_data FROM caldav_data WHERE user_no = ? AND dav_name ~ ? ;", $request->user_no, $request->path.'[^/]+$');
}
else {
  $qry = new PgQuery( "SELECT caldav_data FROM caldav_data WHERE user_no = ? AND dav_name = ? ;", $request->user_no, $request->path);
}
dbg_error_log("get", "%s", $qry->querystring );
if ( $qry->Exec("GET") && $qry->rows == 1 ) {
  $event = $qry->Fetch();
  header( "Content-length: ".strlen($event->caldav_data) );
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
      $timezones[$ical->tz_locn] = $ical->vtimezone;
    }
    $response .= $ical->JustThisBitPlease("VEVENT");
  }
  foreach( $timezones AS $tzid => $vtimezone ) {
    $response .= $vtimezone;
  }
  $response .= iCalendar::iCalFooter();
  header( "Content-length: ".strlen($response) );
  $request->DoResponse( 200, ($request->method == "HEAD" ? "" : $response), "text/calendar" );
}
else {
  $request->DoResponse( 500, translate("Database Error") );
}

?>