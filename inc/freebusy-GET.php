<?php

require_once("iCalendar.php");

  header("Content-type: text/plain");

  $where .= " WHERE caldav_data.dav_name ~ ".qpg("^".$request_path)." ";
  $qry = new PgQuery( "SELECT * FROM caldav_data INNER JOIN calendar_item USING(user_no, dav_name)". $where );
  if ( $qry->Exec("freebusy",__LINE__,__FILE__) && $qry->rows > 0 ) {
    echo iCalendar::iCalHeader();
    while( $calendar_object = $qry->Fetch() ) {
      $parsed = new iCalendar( array('icalendar' => $calendar_object->caldav_data ) );
      echo $parsed->RenderFreeBusy();
    }
    echo iCalendar::iCalFooter();
  }
?>