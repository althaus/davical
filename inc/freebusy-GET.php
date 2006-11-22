<?php

require_once("iCalendar.php");

  header("Content-type: text/plain");

  $sql = "SELECT caldav_data.caldav_type AS type, calendar_item.uid, calendar_item.rrule ";
  $sql .= ", to_char(dtstart at time zone 'UTC',".iCalendar::SqlUTCFormat().") AS dtstart ";
  $sql .= ", to_char(dtend at time zone 'UTC',".iCalendar::SqlUTCFormat().") AS dtend ";
  $sql .= ", to_char(due at time zone 'UTC',".iCalendar::SqlUTCFormat().") AS due ";
  $sql .= ", to_char(dtstamp,".iCalendar::SqlUTCFormat().")  AS dtstamp ";
  $sql .= ", to_char(last_modified,".iCalendar::SqlUTCFormat().")  AS \"last-modified\" ";
  $sql .= " FROM caldav_data INNER JOIN calendar_item USING(user_no, dav_name)";
  $sql .= " WHERE caldav_data.dav_name ~ ".qpg("^".$request_path)." ";
  $qry = new PgQuery( $sql );

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