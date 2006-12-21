<?php
require_once("iCalendar.php");
include_once("RRule.php");

$start = date( "Ymd\THis", time() - (86400 * 30) );
$finish = date( "Ymd\THis", time() + (86400 * 200) );
if ( isset($request->by_email) ) {
  $where = "WHERE caldav_data.user_no = $request->user_no ";
}
else {
  $where = "WHERE caldav_data.user_no = $request->user_no AND caldav_data.dav_name ~ ".qpg("^".$request->path)." ";
}
$where .= "AND (dtend >= '$start'::timestamp with time zone OR calculate_later_timestamp('$start'::timestamp with time zone,dtend,rrule) >= '$start'::timestamp with time zone) ";
$where .= "AND dtstart <= '$finish'::timestamp with time zone ";
$where .= "AND caldav_data.caldav_type IN ( 'VEVENT', 'VFREEBUSY' ) ";

$busy = array();
$busy_tentative = array();
$sql = "SELECT caldav_data.caldav_data, calendar_item.rrule, ";
$sql .= "to_char(calendar_item.dtstart at time zone 'GMT',".iCalendar::SqlDateFormat().") AS start, ";
$sql .= "to_char(calendar_item.dtend at time zone 'GMT',".iCalendar::SqlDateFormat().") AS finish ";
$sql .= "FROM caldav_data INNER JOIN calendar_item USING(user_no, dav_name) $where ORDER BY dtstart, dtend";
// echo $sql. "\n";
$qry = new PgQuery( $sql );
if ( $qry->Exec("freebusy",__LINE__,__FILE__) && $qry->rows > 0 ) {
  while( $calendar_object = $qry->Fetch() ) {
    if ( ! preg_match( '/^TRANSP.*TRANSPARENT/im', $calendar_object->caldav_data ) ) {
      if ( preg_match( '/^STATUS.*:.*TENTATIVE/im', $calendar_object->caldav_data ) ) {
        $busy_tenantive[] = $calendar_object;
      }
      else if ( ! preg_match( '/STATUS.*:.*CANCELLED/m', $calendar_object->caldav_data ) ) {
        dbg_error_log( "freebusy", " FreeBusy: Not transparent, tentative or cancelled: %s, %s", $calendar_object->start, $calendar_object->finish );
        $busy[] = $calendar_object;
      }
    }
  }
}
$freebusy = iCalendar::iCalHeader();
$freebusy .= sprintf("BEGIN:VFREEBUSY\nDTSTAMP:%s\nDTSTART:%s\nDTEND:%s\n", date('Ymd\THis\Z'), $start, $finish);

foreach( $busy_tentative AS $k => $v ) {
  $start = new iCalDate($v->start);
  $duration = $start->DateDifference($v->finish);
  if ( $v->rrule != "" ) {
    $rrule = new RRule( $start, $v->rrule );
    while ( $date = $rrule->GetNext() ) {
      if ( ! $date->GreaterThan($start) ) continue;
      if ( $date->GreaterThan($finish) ) break;
      $freebusy .= sprintf("FREEBUSY;FBTYPE=BUSY-TENTATIVE:%s/%s\n", $date->Render('Ymd\THis'), $duration );
    }
  }
  else {
    $freebusy .= sprintf("FREEBUSY;FBTYPE=BUSY-TENTATIVE:%s/%s\n", $start->Render('Ymd\THis'), $duration );
  }
}

foreach( $busy AS $k => $v ) {
  $start = new iCalDate($v->start);
  $duration = $start->DateDifference($v->finish);
  if ( $v->rrule != "" ) {
    $rrule = new RRule( $start, $v->rrule );
    while ( $date = $rrule->GetNext() ) {
      if ( ! $date->GreaterThan($start) ) continue;
      if ( $date->GreaterThan($finish) ) break;
      $freebusy .= sprintf("FREEBUSY:%s/%s\n", $date->Render('Ymd\THis'), $duration );
    }
  }
  else {
    $freebusy .= sprintf("FREEBUSY:%s/%s\n", $start->Render('Ymd\THis'), $duration );
  }
}

$freebusy .= "END:VFREEBUSY\n";
$freebusy .= iCalendar::iCalFooter();
$request->DoResponse( 200, $freebusy, 'text/calendar' );
// Won't return from that


?>