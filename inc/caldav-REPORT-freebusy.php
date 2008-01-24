<?php
/**
 * Handle the FREE-BUSY-QUERY variant of REPORT
 */
include_once("iCalendar.php");
include_once("RRule.php");

$fbq_content = $xmltree->GetContent('URN:IETF:PARAMS:XML:NS:CALDAV:FREE-BUSY-QUERY');
$fbq_start = $fbq_content[0]->GetAttribute('START');
$fbq_end   = $fbq_content[0]->GetAttribute('END');

if ( ! ( isset($fbq_start) || isset($fbq_end) ) ) {
  $request->DoResponse( 400, 'All valid freebusy requests MUST contain a time-range filter' );
}
$where = " WHERE caldav_data.dav_name ~ ? ";
if ( isset( $fbq_start ) ) {
  $where .= "AND (dtend >= ".qpg($fbq_start)."::timestamp with time zone ";
  $where .= "OR calculate_later_timestamp(".qpg($fbq_start)."::timestamp with time zone,dtend,rrule) >= ".qpg($fbq_start)."::timestamp with time zone) ";
}
if ( isset( $fbq_end ) ) {
  $where .= "AND dtstart <= ".qpg($fbq_end)."::timestamp with time zone ";
}
$where .= "AND caldav_data.caldav_type IN ( 'VEVENT', 'VFREEBUSY' ) ";
$where .= "AND (calendar_item.transp != 'TRANSPARENT' OR calendar_item.transp IS NULL) ";
$where .= "AND (calendar_item.status != 'CANCELLED' OR calendar_item.status IS NULL) ";

if ( ! $request->AllowedTo('all') ) {
  $where .= "AND (calendar_item.class != 'PRIVATE' OR calendar_item.class IS NULL) ";
}

$busy = array();
$busy_tentative = array();
$sql = "SELECT caldav_data.caldav_data, calendar_item.rrule, calendar_item.transp, calendar_item.status, ";
$sql .= "to_char(calendar_item.dtstart at time zone 'GMT',".iCalendar::SqlDateFormat().") AS start, ";
$sql .= "to_char(calendar_item.dtend at time zone 'GMT',".iCalendar::SqlDateFormat().") AS finish ";
$sql .= "FROM caldav_data INNER JOIN calendar_item USING(dav_id,user_no,dav_name)".$where;
if ( isset($c->strict_result_ordering) && $c->strict_result_ordering ) $sql .= " ORDER BY dav_id";
$qry = new PgQuery( $sql, "^".$request->path.$request->DepthRegexTail() );
if ( $qry->Exec("REPORT",__LINE__,__FILE__) && $qry->rows > 0 ) {
  while( $calendar_object = $qry->Fetch() ) {
    if ( $calendar_object->transp != "TRANSPARENT" ) {
      switch ( $calendar_object->status ) {
        case "TENTATIVE":
          dbg_error_log( "REPORT", " FreeBusy: tentative appointment: %s, %s", $calendar_object->start, $calendar_object->finish );
          $busy_tentative[] = $calendar_object;
          break;

        case "CANCELLED":
          // Cancelled events are ignored
          break;

        default:
          dbg_error_log( "REPORT", " FreeBusy: Not transparent, tentative or cancelled: %s, %s", $calendar_object->start, $calendar_object->finish );
          $busy[] = $calendar_object;
          break;
      }
    }
  }
}
$freebusy = iCalendar::iCalHeader();
$freebusy .= sprintf("BEGIN:VFREEBUSY\nDTSTAMP:%s\nDTSTART:%s\nDTEND:%s\n", date('Ymd\THis\Z'), $fbq_start, $fbq_end);

foreach( $busy_tentative AS $k => $v ) {
  $start = new iCalDate($v->start);
  $duration = $start->DateDifference($v->finish);
  if ( $v->rrule != "" ) {
    $rrule = new RRule( $start, $v->rrule );
    while ( $date = $rrule->GetNext() ) {
      if ( ! $date->GreaterThan($fbq_start) ) continue;
      if ( $date->GreaterThan($fbq_end) ) break;
      $todate = clone($date);
      $todate->AddDuration($duration);
      $freebusy .= sprintf("FREEBUSY;FBTYPE=BUSY-TENTATIVE:%s/%s\n", $date->Render('Ymd\THis'), $todate->Render('Ymd\THis') );
    }
  }
  else {
    $freebusy .= sprintf("FREEBUSY;FBTYPE=BUSY-TENTATIVE:%s/%s\n", $start->Render('Ymd\THis'), $v->finish );
  }
}

foreach( $busy AS $k => $v ) {
  $start = new iCalDate($v->start);
  $duration = $start->DateDifference($v->finish);
  if ( $v->rrule != "" ) {
    $rrule = new RRule( $start, $v->rrule );
    while ( $date = $rrule->GetNext() ) {
      if ( ! $date->GreaterThan($fbq_start) ) continue;
      if ( $date->GreaterThan($fbq_end) ) break;
      $todate = clone($date);
      $todate->AddDuration($duration);
      $freebusy .= sprintf("FREEBUSY:%s/%s\n", $date->Render('Ymd\THis'), $todate->Render('Ymd\THis') );
    }
  }
  else {
    $freebusy .= sprintf("FREEBUSY:%s/%s\n", $start->Render('Ymd\THis'), $v->finish );
  }
}

$freebusy .= "END:VFREEBUSY\n";
$freebusy .= iCalendar::iCalFooter();
$request->DoResponse( 200, $freebusy, 'text/calendar' );
// Won't return from that

