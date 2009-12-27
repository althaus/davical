<?php
require_once("iCalendar.php");
include_once("RRule.php");

if ( isset($request->by_email) ) {
  $where = "WHERE caldav_data.user_no = $request->user_no ";
}
else {
  $where = "WHERE caldav_data.user_no = $request->user_no AND caldav_data.dav_name ~ ".qpg("^".$request->path)." ";
}

$begindate = new iCalDate($fb_start);
if ( isset($fb_end) && $fb_end != '' ) {
  $enddate = new iCalDate($fb_end);
}
else {
  $enddate = clone( $begindate );
  $enddate->AddDuration( $fb_period );
}

if ( isset($fb_end) && $fb_end != '' ) {
  $sql_end = qpg($fb_end);
}
else {
  $sql_period = str_replace( 'P', '', $fb_period );
  $sql_period = str_replace( 'W', ' weeks ', $sql_period );
  $sql_period = str_replace( 'D', ' days ', $sql_period );
  $sql_period = str_replace( 'T', ' ', $sql_period );
  $sql_period = str_replace( 'H', ' hours ', $sql_period );
  $sql_period = str_replace( 'M', ' minutes ', $sql_period );
  $sql_period = str_replace( 'S', ' seconds ', $sql_period );
  $sql_end = "( TIMESTAMP " . qpg($fb_start) . " + ( INTERVAL ".qpg($sql_period).") )";
}

$where .= "AND rrule_event_overlaps( dtstart, dtend, rrule, ".qpg($fb_start).", $sql_end ) ";
$where .= "AND caldav_data.caldav_type IN ( 'VEVENT', 'VFREEBUSY' ) ";
$where .= "AND (calendar_item.transp != 'TRANSPARENT' OR calendar_item.transp IS NULL) ";
$where .= "AND (calendar_item.status != 'CANCELLED' OR calendar_item.status IS NULL) ";
if ( ! $request->AllowedTo('all') ) {
  $where .= "AND (calendar_item.class != 'PRIVATE' OR calendar_item.class IS NULL) ";
}

$busy = array();
$busy_tentative = array();
$sql = "SELECT caldav_data.caldav_data, calendar_item.rrule, calendar_item.transp, calendar_item.status, ";
$sql .= "to_char(calendar_item.dtstart at time zone 'GMT',".iCalendar::SqlUTCFormat().") AS start, ";
$sql .= "to_char(calendar_item.dtend at time zone 'GMT',".iCalendar::SqlUTCFormat().") AS finish ";
$sql .= "FROM caldav_data INNER JOIN calendar_item USING(dav_id,user_no,dav_name)".$where;
if ( isset($c->strict_result_ordering) && $c->strict_result_ordering ) $sql .= " ORDER BY dav_id";
// echo $sql. "\n";
$qry = new PgQuery( $sql );
if ( $qry->Exec("freebusy",__LINE__,__FILE__) && $qry->rows > 0 ) {
  while( $calendar_object = $qry->Fetch() ) {
    if ( $calendar_object->transp != "TRANSPARENT" ) {
      switch ( $calendar_object->status ) {
        case "TENTATIVE":
          dbg_error_log( "freebusy", " FreeBusy: tentative appointment: %s, %s", $calendar_object->start, $calendar_object->finish );
          $busy_tentative[] = $calendar_object;
          break;

        case "CANCELLED":
          // Cancelled events are ignored
          break;

        default:
          dbg_error_log( "freebusy", " FreeBusy: Not transparent, tentative or cancelled: %s, %s", $calendar_object->start, $calendar_object->finish );
          $busy[] = $calendar_object;
          break;
      }
    }
  }
}
$freebusy = iCalendar::iCalHeader();
$freebusy .= sprintf("BEGIN:VFREEBUSY\nDTSTAMP:%s\nDTSTART:%s\n%s:%s\n", date('Ymd\THis\Z'), $begindate->RenderGMT(), (isset($fb_period) ? "DURATION" : "DTEND"), (isset($fb_period) ? $fb_period : $enddate->RenderGMT()));

foreach( $busy_tentative AS $k => $v ) {
  $start = new iCalDate($v->start);
  $finish = new iCalDate($v->finish);
  $duration = $start->DateDifference($finish);
  if ( $v->rrule != "" ) {
    $rrule = new RRule( $start, $v->rrule );
    while ( $date = $rrule->GetNext() ) {
      if ( ! ($date->GreaterThan($begindate) ) ) continue;
      if ( $date->GreaterThan($enddate) ) break;
      $todate = clone($date);
      $todate->AddDuration($duration);
      $freebusy .= sprintf("FREEBUSY;FBTYPE=BUSY-TENTATIVE:%s/%s\n", $date->RenderGMT(), $todate->RenderGMT() );
    }
  }
  else {
    $freebusy .= sprintf("FREEBUSY;FBTYPE=BUSY-TENTATIVE:%s/%s\n", $start->RenderGMT(), $finish->RenderGMT() );
  }
}

foreach( $busy AS $k => $v ) {
  $start = new iCalDate($v->start);
  $finish = new iCalDate($v->finish);
  $duration = $start->DateDifference($finish);
  if ( $v->rrule != "" ) {
//debugging    $freebusy .= sprintf("--->%s -- %s vs %s", $v->rrule, $v->start, $start->RenderGMT());
    $rrule = new RRule( $start, $v->rrule );
    while ( $date = $rrule->GetNext() ) {
      if ( ! ($date->GreaterThan($begindate) ) ) continue;
      if ( $date->GreaterThan($enddate) ) break;
      $todate = clone($date);
      $todate->AddDuration($duration);
      $freebusy .= sprintf("FREEBUSY:%s/%s\n", $date->RenderGMT(), $todate->RenderGMT() );
    }
  }
  else {
    $freebusy .= sprintf("FREEBUSY:%s/%s\n", $start->RenderGMT(), $finish->RenderGMT() );
  }
}

$freebusy .= "END:VFREEBUSY\n";
$freebusy .= iCalendar::iCalFooter();
$request->DoResponse( 200, $freebusy, 'text/calendar' );
// Won't return from that
