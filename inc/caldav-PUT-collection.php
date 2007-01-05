<?php

// According to RFC2445 we should always end with CRLF, but the CalDAV spec says
// that normalising XML parses often muck with it and may remove the CR.
$icalendar = preg_replace('/\r?\n /', '', $request->raw_post );

$lines = preg_split('/\r?\n/', $icalendar );

$events = array();
$timezones = array();

$current = "";
$state = 0;
$tzid = 'unknown';
foreach( $lines AS $lno => $line ) {
  if ( $state == 0 ) {
    if ( preg_match( '/^BEGIN:(VEVENT|VTIMEZONE)$/', $line, $matches ) {
      $current .= $line."\n";
      $state = $matches[1];
    }
  }
  else {
    $current .= $line."\n";
    if ( $line == "END:$state" ) {
      switch ( $state ) {
        case 'VEVENT':
          $events[] = array( 'data' => $current, 'tzid' => $tzid );
          break;
        case 'VTIMEZONE':
          $timezones[$tzid] = $current;
          break;
      }
      $state = 0;
      $current = "";
      $tzid = 'unknown';
    }
    else if ( preg_match( 'TZID=([^:]+)(:|$)', $line, $matches ) ) {
      $tzid = $matches[1];
    }
  }
}

function rollback_on_error() {
  $qry = new PgQuery("ROLLBACK;"); $qry->Exec("PUT");
}

$qry = new PgQuery("BEGIN;");
if ( !$qry->Exec("PUT") ) rollback_on_error();

foreach( $events AS $k => $event ) {
  $icalendar = iCalendar::iCalHeader() . $event['data'] . $timezones[$event['tzid']] . iCalendar::iCalFooter();
  $ical = new iCalendar( array( 'icalendar' => $icalendar ) );
  $qry = new PgQuery( "SELECT count(1) FROM caldav_data WHERE user_no=? AND dav_name=?", $request->user_no, $request->path );
  if ( !$qry->Exec("PUT") ) rollback_on_error();
  $count = $qry->Fetch();
  $etag = md5($icalendar);
  if ( $count->count > 0 ) {
    $qry = new PgQuery( "INSERT INTO caldav_data ( user_no, dav_name, dav_etag, caldav_data, caldav_type, logged_user, created, modified ) VALUES( ?, ?, ?, ?, ?, ?, current_timestamp, current_timestamp )",
                        $request->user_no, $request->path, $etag, $icalendar, $ic->type, $session->user_no );
  }
  else {
    $qry = new PgQuery( "UPDATE caldav_data SET caldav_data=?, dav_etag=?, caldav_type=?, logged_user=?, modified=current_timestamp WHERE user_no=? AND dav_name=?",
                        $icalendar, $etag, $ic->type, $session->user_no, $request->user_no, $request->path );
  }
  if ( !$qry->Exec("PUT") ) rollback_on_error();

  $sql = ( $ic->tz_locn == '' ? '' : "SET TIMEZONE TO ".qpg($ic->tz_locn).";" );

  $dtstart = $ic->Get('dtstart');
  if ( (!isset($dtstart) || $dtstart == "") && $ic->Get('due') != "" ) {
    $dtstart = $ic->Get('due');
  }

  $dtend = $ic->Get('dtend');
  if ( (!isset($dtend) || "$dtend" == "") && $ic->Get('duration') != "" AND $dtstart != "" ) {
    $duration = preg_replace( '#[PT]#', ' ', $ic->Get('duration') );
    $dtend = '('.qpg($dtstart).'::timestamp with time zone + '.qpg($duration).'::interval)';
  }
  else {
    dbg_error_log( "PUT", " DTEND: '%s', DTSTART: '%s', DURATION: '%s'", $dtend, $dtstart, $ic->Get('duration') );
    $dtend = qpg($dtend);
  }

  $last_modified = $ic->Get("last-modified");
  if ( !isset($last_modified) || $last_modified == '' ) {
    $last_modified = gmdate( 'Ymd\THis\Z' );
  }

  $dtstamp = $ic->Get("dtstamp");
  if ( !isset($dtstamp) || $dtstamp == '' ) {
    $dtstamp = $last_modified;
  }

  if ( $put_action_type != 'INSERT' ) {
    $sql .= "DELETE FROM calendar_item WHERE user_no=$request->user_no AND dav_name=".qpg($request->path).";";
  }
  $sql .= <<<EOSQL
INSERT INTO calendar_item (user_no, dav_name, dav_etag, uid, dtstamp, dtstart, dtend, summary, location, class, transp,
                    description, rrule, tz_id, last_modified, url, priority, created, due, percent_complete )
                 VALUES ( ?, ?, ?, ?, ?, ?, $dtend, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
EOSQL;

  $qry = new PgQuery( $sql, $request->user_no, $request->path, $etag, $ic->Get('uid'), $dtstamp,
                            $ic->Get('dtstart'), $ic->Get('summary'), $ic->Get('location'),
                            $ic->Get('class'), $ic->Get('transp'), $ic->Get('description'), $ic->Get('rrule'), $ic->Get('tz_id'),
                            $last_modified, $ic->Get('url'), $ic->Get('priority'), $ic->Get('created'),
                            $ic->Get('due'), $ic->Get('percent-complete')
                      );
  if ( !$qry->Exec("PUT") ) rollback_on_error();
}

$qry = new PgQuery("COMMIT;");
if ( !$qry->Exec("PUT") ) rollback_on_error();

?>