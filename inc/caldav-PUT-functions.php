<?php

/**
* Check if the user wants to put just one EVENT/TODO or a whole calendar
* if the collection = calendar = $request_container doesn't exist then create it
* return true if it's a whole calendar
*/

include_once("iCalendar.php");

function controlRequestContainer( $username, $user_no, $path, $context ) {

  // Check to see if the path is like /foo /foo/bar or /foo/bar/baz etc. (not ending with a '/', but contains at least one)
  if ( preg_match( '#^(.*/)([^/]+)$#', $path, $matches ) ) {//(
    $request_container = $matches[1];   // get everything up to the last '/'
    $is_collection = false;        // get after the last '/'
  }
  else {
    // In this case we must have a URL with a trailing '/', so it must be a collection.
    $request_container = $path;
    $is_collection = true;
  }

  /**
  * Before we write the event, we check the container exists, creating it if it doesn't
  */
  if ( $request_container == "/$username/" ) {
    /**
    * Well, it exists, and we support it, but it is against the CalDAV spec
    */
    dbg_error_log( "WARN", " Storing events directly in user's base folders is not recommended!");
  }
  else {
    $sql = "SELECT * FROM collection WHERE user_no = ? AND dav_name = ?;";
    $qry = new PgQuery( $sql, $user_no, $request_container );
    if ( ! $qry->Exec("PUT") ) {
      if($context){
        global $request;
        $request->DoResponse( 500, translate("Error querying database.") );
      }
      else {
        global $c;
        $c->messages[] = sprintf("Status: %d, Message: %s, User: %d, Path: %s", 500, translate("Error querying database."),$user_no,$path);
      }
    }
    if ( $qry->rows == 0 ) {
      if ( preg_match( '#^(.*/)([^/]+/)$#', $request_container, $matches ) ) {//(
        $parent_container = $matches[1];
        $displayname = $matches[2];
      }
      $sql = "INSERT INTO collection ( user_no, parent_container, dav_name, dav_etag, dav_displayname, is_calendar, created, modified ) VALUES( ?, ?, ?, ?, ?, TRUE, current_timestamp, current_timestamp );";
      $qry = new PgQuery( $sql, $user_no, $parent_container, $request_container, md5($user_no. $request_container), $displayname );
      $qry->Exec("PUT");
    }
  }
  //I don't think useful because there is already a $request->IsCollection() that does the job
  //Andrew If you think it's unuseful then remove $is_collection in this function
  return $is_collection;
}

/**
* This function launches an error
* @param int $user_no the user wich will receive this ics file
* @param string $path the $path where it will be store such as /user_foo/home/
* @param boolean $context is true if this function is called from a way where $request is defined
*/
function rollback_on_error($context,$user_no,$path) {
  $qry = new PgQuery("ROLLBACK;"); $qry->Exec("PUT-collection");
  if($context){
    global $request;
    $request->DoResponse( 500, translate("Database error") );
  }
  else {
    global $c;
    $c->messages[] = sprintf("Status: %d, Message: %s, User: %d, Path: %s", 500, translate("Database error"),$user_no,$path);
  }
}


/**
* This function will import a whole calendar
* @param string $ics_content the ics file to import
* @param int $user_no the user wich will receive this ics file
* @param string $path the $path where it will be store such as /user_foo/home/
* @param boolean $context is true if this function is called from a way where $request is defined
*/
function import_collection($ics_content, $user_no, $path,$context){
  // According to RFC2445 we should always end with CRLF, but the CalDAV spec says
  // that normalising XML parses often muck with it and may remove the CR.
  $icalendar = preg_replace('/\r?\n /', '', $ics_content );
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
  $qry = new PgQuery("BEGIN; DELETE FROM calendar_item WHERE user_no=? AND dav_name ~ ?; DELETE FROM caldav_data WHERE user_no=? AND dav_name ~ ?;", $user_no, $path.'[^/]+$', $user_no, $path.'[^/]+$');
  if ( !$qry->Exec("PUT") ) rollback_on_error($context,$user_no,$path);

  foreach( $events AS $k => $event ) {
    dbg_error_log( "PUT", "Putting event %d with data: %s", $k, $event['data'] );
    $icalendar = iCalendar::iCalHeader() . $event['data'] . $timezones[$event['tzid']] . iCalendar::iCalFooter();
    $ic = new iCalendar( array( 'icalendar' => $icalendar ) );
    $etag = md5($icalendar);
    $event_path = sprintf( "%s%d.ics", $path, $k);
    $qry = new PgQuery( "INSERT INTO caldav_data ( user_no, dav_name, dav_etag, caldav_data, caldav_type, logged_user, created, modified ) VALUES( ?, ?, ?, ?, ?, ?, current_timestamp, current_timestamp )",
                          $user_no, $event_path, $etag, $icalendar, $ic->type, $session->user_no );
    if ( !$qry->Exec("PUT") ) rollback_on_error($context,$user_no,$path);

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

    $qry = new PgQuery( $sql, $user_no, $event_path, $etag, $ic->Get('uid'), $dtstamp,
                              $ic->Get('dtstart'), $ic->Get('summary'), $ic->Get('location'),
                              $ic->Get('class'), $ic->Get('transp'), $ic->Get('description'), $ic->Get('rrule'), $ic->Get('tz_id'),
                              $last_modified, $ic->Get('url'), $ic->Get('priority'), $ic->Get('created'),
                              $ic->Get('due'), $ic->Get('percent-complete')
                        );
    if ( !$qry->Exec("PUT") ) rollback_on_error($context,$user_no,$path);
  }

  $qry = new PgQuery("COMMIT;");
  if ( !$qry->Exec("PUT") ) rollback_on_error($context,$user_no,$path);
}
?>