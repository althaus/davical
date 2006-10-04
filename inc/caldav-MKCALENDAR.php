<?php

dbg_error_log("MKCALENDAR", "method handler");

dbg_log_array( "MKCOL", 'HEADERS', $raw_headers );
dbg_log_array( "MKCOL", '_SERVER', $_SERVER, true );
dbg_error_log( "MKCOL", "RAW: %s", str_replace("\n", "",str_replace("\r", "", $raw_post)) );

$make_path = $_SERVER['PATH_INFO'];

$displayname = $make_path;
$parent_container = '/';
if ( preg_match( '#^(.*/)([^/]+)(/)?$#', $make_path, $matches ) ) {
  $parent_container = $matches[1];
  $displayname = $matches[2];
}

$sql = "INSERT INTO collection ( user_no, parent_container, dav_name, dav_etag, dav_displayname, is_calendar, created, modified ) VALUES( ?, ?, ?, ?, ?, TRUE, current_timestamp, current_timestamp );";
$qry = new PgQuery( $sql, $session->user_no, $parent_container, $make_path, md5($session->user_no. $make_path), $displayname );

if ( $qry->Exec("MKCALENDAR",__LINE__,__FILE__) ) {
  header("HTTP/1.1 200 Created");
  dbg_error_log( "MKCALENDAR", "New calendar '%s' created named '%s' for user '%d' in parent '%s'", $make_path, $displayname, $session->user_no, $parent_container);
}
else {
  header("HTTP/1.1 500 Infernal Server Error");
  dbg_error_log( "ERROR", " MKCOL Failed for '%s' named '%s', user '%d' in parent '%s'", $make_path, $displayname, $session->user_no, $parent_container);
}

/**
* We could also respond to the request...
*
   <?xml version="1.0" encoding="utf-8" ?>
   <C:mkcalendar xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
     <D:set>
       <D:prop>
         <D:displayname>Lisa's Events</D:displayname>
         <C:calendar-description xml:lang="en">Calendar restricted to events.</C:calendar-description>
         <C:supported-calendar-component-set>
           <C:comp name="VEVENT"/>
         </C:supported-calendar-component-set>
         <C:calendar-timezone><![CDATA[BEGIN:VCALENDAR
   PRODID:-//Example Corp.//CalDAV Client//EN
   VERSION:2.0
   BEGIN:VTIMEZONE
   TZID:US-Eastern
   LAST-MODIFIED:19870101T000000Z
   BEGIN:STANDARD
   DTSTART:19671029T020000
   RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10
   TZOFFSETFROM:-0400
   TZOFFSETTO:-0500
   TZNAME:Eastern Standard Time (US & Canada)
   END:STANDARD
   BEGIN:DAYLIGHT
   DTSTART:19870405T020000
   RRULE:FREQ=YEARLY;BYDAY=1SU;BYMONTH=4
   TZOFFSETFROM:-0500
   TZOFFSETTO:-0400
   TZNAME:Eastern Daylight Time (US & Canada)
   END:DAYLIGHT
   END:VTIMEZONE
   END:VCALENDAR
   ]]></C:calendar-timezone>
       </D:prop>
     </D:set>
   </C:mkcalendar>

*/

?>