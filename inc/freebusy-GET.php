<?php
require_once('iCalendar.php');
include_once('RRule.php');

$params = array( ':user_no' => $request->user_no );
$where = 'WHERE caldav_data.user_no = :user_no ';
if ( ! isset($request->by_email) ) {
  $params[':path_match'] = "^".$request->path;
  $where .= 'AND caldav_data.dav_name ~ :path_match ';
}

$begindate = new iCalDate($fb_start);
if ( isset($fb_end) && $fb_end != '' ) {
  $enddate = new iCalDate($fb_end);
}
else {
  $enddate = clone( $begindate );
  $enddate->AddDuration( $fb_period );
}

$params[':range_start'] = $fb_start;
if ( isset($fb_end) && $fb_end != '' ) {
  $params[':range_end'] = $fb_end;
  $where .= ' AND rrule_event_overlaps( dtstart, dtend, rrule, :range_start, :range_end) ';
}
else {
  $sql_period = str_replace( 'P', '', $fb_period );
  $sql_period = str_replace( 'W', ' weeks ', $sql_period );
  $sql_period = str_replace( 'D', ' days ', $sql_period );
  $sql_period = str_replace( 'T', ' ', $sql_period );
  $sql_period = str_replace( 'H', ' hours ', $sql_period );
  $sql_period = str_replace( 'M', ' minutes ', $sql_period );
  $sql_period = str_replace( 'S', ' seconds ', $sql_period );
  $params[':sql_duration'] = $sql_period;
  $where .= ' AND rrule_event_overlaps( dtstart, dtend, rrule, :range_start::timestamp, :range_start::timestamp + :sql_duration::interval ) ';
}

$where .= "AND caldav_data.caldav_type IN ( 'VEVENT', 'VFREEBUSY' ) ";
$where .= "AND (calendar_item.transp != 'TRANSPARENT' OR calendar_item.transp IS NULL) ";
$where .= "AND (calendar_item.status != 'CANCELLED' OR calendar_item.status IS NULL) ";
if ( $request->Privileges() != privilege_to_bits('all') ) {
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
$qry = new AwlQuery( $sql, $params );
if ( $qry->Exec("freebusy",__LINE__,__FILE__) && $qry->rows() > 0 ) {
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

$freebusy = new iCalComponent();
$freebusy->SetType('VFREEBUSY');
$freebusy->AddProperty('DTSTAMP', date('Ymd\THis\Z'));
$freebusy->AddProperty('DTSTART', $begindate->RenderGMT());
if ( isset($fb_period) ) {
  $freebusy->AddProperty('DURATION', $fb_period);
}
else {
  $freebusy->AddProperty('DTEND', $enddate->RenderGMT());
}

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
      $freebusy->AddProperty( 'FREEBUSY', $date->RenderGMT().'/'.$todate->RenderGMT(), array('FBTYPE' => 'BUSY-TENTATIVE') );
    }
  }
  else {
    $freebusy->AddProperty( 'FREEBUSY', $start->RenderGMT().'/'.$finish->RenderGMT(), array('FBTYPE' => 'BUSY-TENTATIVE') );
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
      $freebusy->AddProperty( 'FREEBUSY', $date->RenderGMT().'/'.$todate->RenderGMT() );
    }
  }
  else {
    $freebusy->AddProperty( 'FREEBUSY', $start->RenderGMT().'/'.$finish->RenderGMT() );
  }
}

$result = new iCalComponent();
$result->VCalendar();
$result->AddComponent($freebusy);

$request->DoResponse( 200, $result->Render(), 'text/calendar' );
// Won't return from that
