<?php
/**
 * Handle the FREE-BUSY-QUERY variant of REPORT
 */
include_once("iCalendar.php");
include_once("RRule.php");

$fbq_content = $xmltree->GetContent('urn:ietf:params:xml:ns:caldav:free-busy-query');
$fbq_start = $fbq_content[0]->GetAttribute('start');
$fbq_end   = $fbq_content[0]->GetAttribute('end');

if ( ! ( isset($fbq_start) || isset($fbq_end) ) ) {
  $request->DoResponse( 400, 'All valid freebusy requests MUST contain a time-range filter' );
}
$params = array( ':path_match' => '^'.$request->path.$request->DepthRegexTail(), ':start' => $fbq_start, ':end' => $fbq_end );
$where = ' WHERE caldav_data.dav_name ~ :path_match ';
$where .= 'AND rrule_event_overlaps( dtstart, dtend, rrule, :start, :end) ';
$where .= "AND caldav_data.caldav_type IN ( 'VEVENT', 'VFREEBUSY' ) ";
$where .= "AND (calendar_item.transp != 'TRANSPARENT' OR calendar_item.transp IS NULL) ";
$where .= "AND (calendar_item.status != 'CANCELLED' OR calendar_item.status IS NULL) ";

if ( $request->Privileges() != privilege_to_bits('all') ) {
  $where .= "AND (calendar_item.class != 'PRIVATE' OR calendar_item.class IS NULL) ";
}

$busy = array();
$busy_tentative = array();
$sql = "SELECT caldav_data.caldav_data, calendar_item.rrule, calendar_item.transp, calendar_item.status, ";
$sql .= "to_char(calendar_item.dtstart at time zone 'GMT',".iCalendar::SqlDateFormat().") AS start, ";
$sql .= "to_char(calendar_item.dtend at time zone 'GMT',".iCalendar::SqlDateFormat().") AS finish ";
$sql .= "FROM caldav_data INNER JOIN calendar_item USING(dav_id,user_no,dav_name)".$where;
if ( isset($c->strict_result_ordering) && $c->strict_result_ordering ) $sql .= " ORDER BY dav_id";
$qry = new AwlQuery( $sql, $params );
if ( $qry->Exec("REPORT",__LINE__,__FILE__) && $qry->rows() > 0 ) {
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

$freebusy = new iCalComponent();
$freebusy->SetType('VFREEBUSY');
$freebusy->AddProperty('DTSTAMP', date('Ymd\THis\Z'));
$freebusy->AddProperty('DTSTART', $fbq_start);
$freebusy->AddProperty('DTEND', $fbq_end);

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
      $freebusy->AddProperty( 'FREEBUSY', $date->Render('Ymd\THis').'/'.$todate->Render('Ymd\THis'), array('FBTYPE' => 'BUSY-TENTATIVE') );
    }
  }
  else {
    $freebusy->AddProperty( 'FREEBUSY', $start->Render('Ymd\THis').'/'.$v->finish, array('FBTYPE' => 'BUSY-TENTATIVE') );
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
      $freebusy->AddProperty( 'FREEBUSY', $date->Render('Ymd\THis').'/'.$todate->Render('Ymd\THis') );
    }
  }
  else {
    $freebusy->AddProperty( 'FREEBUSY', $start->Render('Ymd\THis').'/'.$v->finish );
  }
}

$result = new iCalComponent();
$result->VCalendar();
$result->AddComponent($freebusy);

$request->DoResponse( 200, $result->Render(), 'text/calendar' );
// Won't return from that

