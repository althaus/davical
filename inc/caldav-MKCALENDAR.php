<?php
/**
* CalDAV Server - handle MKCALENDAR method
*
* @package   rscds
* @subpackage   caldav
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("MKCALENDAR", "method handler");

if ( ! $request->AllowedTo('write') ) {
  $request->DoResponse( 403, translate("You may not create a calendar there.") );
}

$displayname = $request->path;
$parent_container = '/';
if ( preg_match( '#^(.*/)([^/]+)(/)?$#', $request->path, $matches ) ) {
  $parent_container = $matches[1];
  $displayname = $matches[2];
}
$sql = "SELECT * FROM collection WHERE user_no = ? AND dav_name = ?;";
$qry = new PgQuery( $sql, $request->user_no, $request->path );
if ( ! $qry->Exec("MKCALENDAR") ) {
  $request->DoResponse( 500, translate("Error querying database.") );
}
if ( $qry->rows != 0 ) {
  $request->DoResponse( 412, translate("A collection already exists at that location.") );
}

$sql = "INSERT INTO collection ( user_no, parent_container, dav_name, dav_etag, dav_displayname, is_calendar, created, modified ) VALUES( ?, ?, ?, ?, ?, ?, current_timestamp, current_timestamp );";
$qry = new PgQuery( $sql, $request->user_no, $parent_container, $request->path, md5($request->user_no. $request->path), $displayname, ($request->method == 'MKCALENDAR') );

if ( $qry->Exec("MKCALENDAR",__LINE__,__FILE__) ) {
  dbg_error_log( "MKCALENDAR", "New calendar '%s' created named '%s' for user '%d' in parent '%s'", $request->path, $displayname, $session->user_no, $parent_container);
  $request->DoResponse( 201, "" );
}
else {
  $request->DoResponse( 500, translate("Error writing calendar details to database.") );
}

/**
* FIXME: We could also respond to the request...
*
*   <?xml version="1.0" encoding="utf-8" ?>
*   <C:mkcalendar xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
*     <D:set>
*       <D:prop>
*         <D:displayname>Lisa's Events</D:displayname>
*         <C:calendar-description xml:lang="en">Calendar restricted to events.</C:calendar-description>
*         <C:supported-calendar-component-set>
*           <C:comp name="VEVENT"/>
*         </C:supported-calendar-component-set>
*         <C:calendar-timezone><![CDATA[BEGIN:VCALENDAR
*   PRODID:-//Example Corp.//CalDAV Client//EN
*   VERSION:2.0
*   BEGIN:VTIMEZONE
*   TZID:US-Eastern
*   LAST-MODIFIED:19870101T000000Z
*   BEGIN:STANDARD
*   DTSTART:19671029T020000
*   RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10
*   TZOFFSETFROM:-0400
*   TZOFFSETTO:-0500
*   TZNAME:Eastern Standard Time (US & Canada)
*   END:STANDARD
*   BEGIN:DAYLIGHT
*   DTSTART:19870405T020000
*   RRULE:FREQ=YEARLY;BYDAY=1SU;BYMONTH=4
*   TZOFFSETFROM:-0500
*   TZOFFSETTO:-0400
*   TZNAME:Eastern Daylight Time (US & Canada)
*   END:DAYLIGHT
*   END:VTIMEZONE
*   END:VCALENDAR
*   ]]></C:calendar-timezone>
*       </D:prop>
*     </D:set>
*   </C:mkcalendar>
*
*/

?>