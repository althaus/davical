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

if ( ! isset($permissions['write']) ) {
  header("HTTP/1.1 403 Forbidden");
  header("Content-type: text/plain");
  echo "You may not create a calendar there.";
  dbg_error_log("ERROR", "MKCALENDAR Access denied for User: %d, Path: %s", $session->user_no, $request_path);
  exit(0);
}

$displayname = $request_path;
$parent_container = '/';
if ( preg_match( '#^(.*/)([^/]+)(/)?$#', $request_path, $matches ) ) {
  $parent_container = $matches[1];
  $displayname = $matches[2];
}
$sql = "SELECT * FROM collection WHERE user_no = ? AND dav_name = ?;";
$qry = new PgQuery( $sql, $path_user_no, $request_path );
if ( ! $qry->Exec("MKCALENDAR") ) {
  header("HTTP/1.1 500 Infernal Server Error");
  dbg_error_log( "ERROR", " MKCALENDAR Failed (database error) for '%s' named '%s', user '%d' in parent '%s'", $request_path, $displayname, $session->user_no, $parent_container);
  exit(0);
}
if ( $qry->rows != 0 ) {
  header("HTTP/1.1 412 Calendar Already Exists");
  dbg_error_log( "ERROR", " MKCALENDAR Failed (already exists) for '%s' named '%s', user '%d' in parent '%s'", $request_path, $displayname, $session->user_no, $parent_container);
  exit(0);
}

$sql = "INSERT INTO collection ( user_no, parent_container, dav_name, dav_etag, dav_displayname, is_calendar, created, modified ) VALUES( ?, ?, ?, ?, ?, TRUE, current_timestamp, current_timestamp );";
$qry = new PgQuery( $sql, $path_user_no, $parent_container, $request_path, md5($path_user_no. $request_path), $displayname );

if ( $qry->Exec("MKCALENDAR",__LINE__,__FILE__) ) {
  header("HTTP/1.1 200 Created");
  dbg_error_log( "MKCALENDAR", "New calendar '%s' created named '%s' for user '%d' in parent '%s'", $request_path, $displayname, $session->user_no, $parent_container);
}
else {
  header("HTTP/1.1 500 Infernal Server Error");
  dbg_error_log( "ERROR", " MKCALENDAR Failed for '%s' named '%s', user '%d' in parent '%s'", $request_path, $displayname, $session->user_no, $parent_container);
  exit(0);
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