<?php

// According to RFC2445 we should always end with CRLF, but the CalDAV spec says
// that normalising XML parses often muck with it and may remove the CR.
$icalendar = preg_replace('/\r?\n /', '', $request->raw_post );
$fh = fopen('/tmp/PUT-2.txt','w');
fwrite($fh,$icalendar);
fclose($fh);

$lines = preg_split('/\r?\n/', $icalendar );

$events = array();
$timezones = array();

$current = "";
$state = "";
$tzid = 'unknown';
foreach( $lines AS $lno => $line ) {
  dbg_error_log( "PUT", "CalendarLine[%04d] - %s: %s", $lno, $state, $line );
  if ( $state == "" ) {
    if ( preg_match( '/^BEGIN:(VEVENT|VTIMEZONE|VTODO)$/', $line, $matches ) ) {
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
        case 'VTODO':
          $events[] = array( 'data' => $current, 'tzid' => $tzid );
          break;
        case 'VTIMEZONE':
          $timezones[$tzid] = $current;
          break;
      }
      $state = "";
      $current = "";
      $tzid = 'unknown';
    }
    else if ( preg_match( '/TZID=([^:]+)(:|$)/', $line, $matches ) ) {
      $tzid = $matches[1];
    }
  }
}

function rollback_on_error() {
  global $request;
  $qry = new PgQuery("ROLLBACK;"); $qry->Exec("PUT");
  $request->DoResponse( 500, translate("Database error") );
}

$qry = new PgQuery("BEGIN; DELETE FROM calendar_item WHERE user_no=? AND dav_name ~ ?; DELETE FROM caldav_data WHERE user_no=? AND dav_name ~ ?;", $request->user_no, $request->path.'[^/]+$', $request->user_no, $request->path.'[^/]+$');
if ( !$qry->Exec("PUT") ) rollback_on_error();

foreach( $events AS $k => $event ) {
  dbg_error_log( "PUT", "Putting event %d with data: %s", $k, $event['data'] );
  $icalendar = iCalendar::iCalHeader() . $event['data'] . $timezones[$event['tzid']] . iCalendar::iCalFooter();
  $ic = new iCalendar( array( 'icalendar' => $icalendar ) );
  $etag = md5($icalendar);
  $event_path = sprintf( "%s%d.ics", $request->path, $k);
  $qry = new PgQuery( "INSERT INTO caldav_data ( user_no, dav_name, dav_etag, caldav_data, caldav_type, logged_user, created, modified ) VALUES( ?, ?, ?, ?, ?, ?, current_timestamp, current_timestamp )",
                        $request->user_no, $event_path, $etag, $icalendar, $ic->type, $session->user_no );
  if ( !$qry->Exec("PUT") ) rollback_on_error();

  $sql = "";
  if ( preg_match(':^(Africa|America|Antarctica|Arctic|Asia|Atlantic|Australia|Brazil|Canada|Chile|Etc|Europe|Indian|Mexico|Mideast|Pacific|US)/[a-z]+$:i', $ic->tz_locn ) ) {
    // We only set the timezone if it looks reasonable enough for us
    $sql = ( $ic->tz_locn == '' ? '' : "SET TIMEZONE TO ".qpg($ic->tz_locn).";" );
  }

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

  $sql .= <<<EOSQL
INSERT INTO calendar_item (user_no, dav_name, dav_etag, uid, dtstamp, dtstart, dtend, summary, location, class, transp,
                    description, rrule, tz_id, last_modified, url, priority, created, due, percent_complete )
                 VALUES ( ?, ?, ?, ?, ?, ?, $dtend, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
EOSQL;

  $qry = new PgQuery( $sql, $request->user_no, $event_path, $etag, $ic->Get('uid'), $dtstamp,
                            $ic->Get('dtstart'), $ic->Get('summary'), $ic->Get('location'),
                            $ic->Get('class'), $ic->Get('transp'), $ic->Get('description'), $ic->Get('rrule'), $ic->Get('tz_id'),
                            $last_modified, $ic->Get('url'), $ic->Get('priority'), $ic->Get('created'),
                            $ic->Get('due'), $ic->Get('percent-complete')
                      );
  if ( !$qry->Exec("PUT") ) rollback_on_error();
}

$qry = new PgQuery("COMMIT;");
if ( !$qry->Exec("PUT") ) rollback_on_error();

$request->DoResponse( 200 );

?>