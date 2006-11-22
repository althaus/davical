<?php
require_once("iCalendar.php");

  $sql = 'SELECT caldav_data.caldav_type AS type, calendar_item.uid, calendar_item.rrule ';
  $sql .= ', to_ical_utc(dtstart)  AS dtstart ';
  $sql .= ', to_ical_utc(dtend)    AS dtend ';
  $sql .= ', to_ical_utc(due)      AS due ';
  $sql .= ', to_ical_utc(dtstamp)  AS dtstamp ';
  $sql .= ', to_ical_utc(last_modified)  AS "last-modified" ';
  $sql .= ' FROM caldav_data INNER JOIN calendar_item USING(user_no, dav_name) ';
  $sql .= ' WHERE caldav_data.dav_name ~ '.qpg("^".$request_path);
  $qry = new PgQuery( $sql );

  header("Content-type: text/calendar");

  echo iCalendar::iCalHeader();

  $freebusy_properties = array( "uid", "dtstamp", "dtstart", "duration", "last-modified", "rrule" );

  if ( $qry->Exec("freebusy",__LINE__,__FILE__) && $qry->rows > 0 ) {
    while( $calendar_item = $qry->Fetch() ) {
      $event = new iCalendar( $calendar_item );
      echo $event->Render( false, 'VFREEBUSY', $freebusy_properties );
    }
  }
  echo iCalendar::iCalFooter();

?>