<?php

/**
* Check if the user wants to put just one EVENT/TODO or a whole calendar
* if the collection = calendar = $request_container doesn't exist then create it
* return true if it's a whole calendar
*/

include_once("iCalendar.php");

/**
* This function launches an error
* @param boolean $caldav_context Whether we are responding via CalDAV or interactively
* @param int $user_no the user wich will receive this ics file
* @param string $path the $path where the PUT failed to store such as /user_foo/home/
* @param string $message An optional error message to return to the client
* @param int $error_no An optional value for the HTTP error code
*/
function rollback_on_error( $caldav_context, $user_no, $path, $message='', $error_no=500 ) {
  if ( !$message ) $message = translate("Database error");
  $qry = new PgQuery("ROLLBACK;"); $qry->Exec("PUT-collection");
  if ( $caldav_context ) {
    global $request;
    $request->DoResponse( $error_no, $message );
  }
  else {
    global $c;
    $c->messages[] = sprintf("Status: %d, Message: %s, User: %d, Path: %s", $error_no, $message, $user_no, $path);
  }
}



/**
* Work out the location we are doing the PUT to, and check that we have the rights to
* do the needful.
* @param string $username The name of the destination user
* @param int $user_no The user making the change
* @param string $path The DAV path the resource is bing PUT to
* @param boolean $caldav_context Whether we are responding via CalDAV or interactively
*/
function controlRequestContainer( $username, $user_no, $path, $caldav_context ) {

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
      rollback_on_error( $caldav_context, $user_no, $path );
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
* Check if this collection should force all events to be PUBLIC.
* @param string $user_no the user that owns the collection
* @param string $dav_name the collection to check
* @return boolean Return true if public events only are allowed.
*/
function public_events_only( $user_no, $dav_name ) {
  global $c;
  // Not supported until DB versions from 1.001.010
  if ( $c->schema_version < 1001.010 ) return false;

  $sql = "SELECT public_events_only ";
  $sql .= "FROM collection ";
  $sql .= "WHERE user_no=? AND dav_name=?";

  $qry = new PgQuery($sql, $user_no, $dav_name);

  if( $qry->Exec('PUT') && $qry->rows == 1 ) {
    $collection = $qry->Fetch();

    if ($collection->public_events_only == 't') {
      return true;
    }
  }

  // Something went wrong, must be false.
  return false;
}


/**
* This function will import a whole calendar
* @param string $ics_content the ics file to import
* @param int $user_no the user wich will receive this ics file
* @param string $path the $path where it will be store such as /user_foo/home/
* @param boolean $caldav_context Whether we are responding via CalDAV or interactively
*/
function import_collection( $ics_content, $user_no, $path, $caldav_context ) {
  global $c;
  // According to RFC2445 we should always end with CRLF, but the CalDAV spec says
  // that normalising XML parses often muck with it and may remove the CR.
  $icalendar = preg_replace('/\r?\n /', '', $ics_content );
  if ( ! ini_get('open_basedir') && (isset($c->dbg['ALL']) || isset($c->dbg['put'])) ) {
    $fh = fopen('/tmp/PUT-2.txt','w');
    if ( $fh ) {
      fwrite($fh,$icalendar);
      fclose($fh);
    }
  }

  $lines = preg_split('/\r?\n/', $icalendar );

  $events = array();
  $timezones = array();

  $current = "";
  $state = "";
  $tzid = 'unknown';
  foreach( $lines AS $lno => $line ) {
    if ( $state == "" ) {
      if ( preg_match( '/^BEGIN:(VEVENT|VTIMEZONE|VTODO|VJOURNAL)$/', $line, $matches ) ) {
        $current .= $line."\n";
        $state = $matches[1];
        dbg_error_log( "PUT", "CalendarLine[%04d] - %s: %s", $lno, $state, $line );
      }
    }
    else {
      $current .= $line."\n";
      if ( $line == "END:$state" ) {
        switch ( $state ) {
          case 'VTIMEZONE':
            $timezones[$tzid] = $current;
            dbg_error_log( "PUT", " Ended VTIMEZONE for TZID '%s' ", $tzid );
            break;
          case 'VEVENT':
          case 'VTODO':
          case 'VJOURNAL':
          default:
            $events[] = array( 'data' => $current, 'tzid' => $tzid );
            dbg_error_log( "PUT", " Ended %s with TZID '%s' ", $state, $tzid );
            break;
        }
        $state = "";
        $current = "";
        $tzid = 'unknown';
      }
      else if ( preg_match( '/TZID[:=]([^:]+)(:|$)/', $line, $matches ) ) {
        $tzid = $matches[1];
        dbg_error_log( "PUT", " Found TZID of '%s' in '%s'", $tzid, $line );
      }
    }
  }
  dbg_error_log( "PUT", " Finished input after $lno lines" );

  $qry = new PgQuery("BEGIN; DELETE FROM calendar_item WHERE user_no=? AND dav_name ~ ?; DELETE FROM caldav_data WHERE user_no=? AND dav_name ~ ?;", $user_no, $path.'[^/]+$', $user_no, $path.'[^/]+$');
  if ( !$qry->Exec("PUT") ) rollback_on_error( $caldav_context, $user_no, $path );

  foreach( $events AS $k => $event ) {
    dbg_error_log( "PUT", "Putting event %d with data: %s", $k, $event['data'] );
    $icalendar = iCalendar::iCalHeader() . $event['data'] . $timezones[$event['tzid']] . iCalendar::iCalFooter();
    $ic = new iCalendar( array( 'icalendar' => $icalendar ) );
    $etag = md5($icalendar);
    $event_path = sprintf( "%s%d.ics", $path, $k);
    $qry = new PgQuery( "INSERT INTO caldav_data ( user_no, dav_name, dav_etag, caldav_data, caldav_type, logged_user, created, modified ) VALUES( ?, ?, ?, ?, ?, ?, current_timestamp, current_timestamp )",
                          $user_no, $event_path, $etag, $icalendar, $ic->type, $session->user_no );
    if ( !$qry->Exec("PUT") ) rollback_on_error( $caldav_context, $user_no, $path );

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

    $class = $ic->Get("class");
    /* Check and see if we should over ride the class. */
    if ( public_events_only($user_no, $path) ) {
      $class = 'PUBLIC';
    }

    /*
     * It seems that some calendar clients don't set a class...
     * RFC2445, 4.8.1.3:
     * Default is PUBLIC
     */
    if ( !isset($class) || $class == '' ) {
      $class = 'PUBLIC';
    }

    $sql .= <<<EOSQL
  INSERT INTO calendar_item (user_no, dav_name, dav_etag, uid, dtstamp, dtstart, dtend, summary, location, class, transp,
                      description, rrule, tz_id, last_modified, url, priority, created, due, percent_complete )
                   VALUES ( ?, ?, ?, ?, ?, ?, $dtend, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
EOSQL;

    $qry = new PgQuery( $sql, $user_no, $event_path, $etag, $ic->Get('uid'), $dtstamp,
                              $ic->Get('dtstart'), $ic->Get('summary'), $ic->Get('location'),
                              $class, $ic->Get('transp'), $ic->Get('description'), $ic->Get('rrule'), $ic->Get('tz_id'),
                              $last_modified, $ic->Get('url'), $ic->Get('priority'), $ic->Get('created'),
                              $ic->Get('due'), $ic->Get('percent-complete')
                        );
    if ( !$qry->Exec("PUT") ) rollback_on_error( $caldav_context, $user_no, $path);
  }

  $qry = new PgQuery("COMMIT;");
  if ( !$qry->Exec("PUT") ) rollback_on_error( $caldav_context, $user_no, $path);
}


/**
* Put the resource from this request
* @param object $request A reference to the request object
* @param int $author The user_no who wants to put this resource on the server
* @param boolean $caldav_context Whether we are responding via CalDAV or interactively
* @return string Either 'INSERT' or 'UPDATE': the type of action that the PUT resulted in
*/
function putCalendarResource( &$request, $author, $caldav_context ) {
  $etag = md5($request->raw_post);
  $ic = new iCalendar(array( 'icalendar' => $request->raw_post ));

  dbg_log_array( "PUT", 'EVENT', $ic->properties['VCALENDAR'][0], true );

  /**
  * We read any existing object so we can check the ETag.
  */
  unset($put_action_type);
  $qry = new PgQuery( "SELECT * FROM caldav_data WHERE user_no=? AND dav_name=?", $request->user_no, $request->path );
  if ( !$qry->Exec("PUT") || $qry->rows > 1 ) {
    rollback_on_error( $caldav_context, $request->user_no, $request->path );
  }
  elseif ( $qry->rows < 1 ) {
    if ( isset($request->etag_if_match) && $request->etag_if_match != '' ) {
      /**
      * RFC2068, 14.25:
      * If none of the entity tags match, or if "*" is given and no current
      * entity exists, the server MUST NOT perform the requested method, and
      * MUST return a 412 (Precondition Failed) response.
      */
      rollback_on_error( $caldav_context, $request->user_no, $request->path, 412, translate("Resource changed on server - not changed.") );
    }

    $put_action_type = 'INSERT';

    if ( ! $request->AllowedTo("create") ) {
      rollback_on_error( $caldav_context, $request->user_no, $request->path, 403, translate("You may not add entries to this calendar.") );
    }
  }
  elseif ( $qry->rows == 1 ) {
    $icalendar = $qry->Fetch();

    if ( ( isset($request->etag_if_match) && $request->etag_if_match != '' && $request->etag_if_match != $icalendar->dav_etag )
         || ( isset($request->etag_none_match) && $request->etag_none_match != '' && ($request->etag_none_match == $icalendar->dav_etag || $request->etag_none_match == '*') ) ) {
      /**
      * RFC2068, 14.25:
      * If none of the entity tags match, or if "*" is given and no current
      * entity exists, the server MUST NOT perform the requested method, and
      * MUST return a 412 (Precondition Failed) response.
      *
      * RFC2068, 14.26:
      * If any of the entity tags match the entity tag of the entity that
      * would have been returned in the response to a similar GET request
      * (without the If-None-Match header) on that resource, or if "*" is
      * given and any current entity exists for that resource, then the
      * server MUST NOT perform the requested method.
      */
      if ( isset($request->etag_if_match) && $request->etag_if_match != $icalendar->dav_etag ) {
        $error = translate( "Existing resource does not match 'If-Match' header - not accepted.");
      }
      if ( isset($etag_none_match) && $etag_none_match != '' && ($etag_none_match == $icalendar->dav_etag || $etag_none_match == '*') ) {
        $error = translate( "Existing resource matches 'If-None-Match' header - not accepted.");
      }
      $request->DoResponse( 412, $error );
    }

    $put_action_type = 'UPDATE';

    if ( ! $request->AllowedTo("modify") ) {
      $request->DoResponse( 403, translate("You may not modify entries on this calendar.") );
    }
  }

  if ( $put_action_type == 'INSERT' ) {
    $qry = new PgQuery( "BEGIN; INSERT INTO caldav_data ( user_no, dav_name, dav_etag, caldav_data, caldav_type, logged_user, created, modified ) VALUES( ?, ?, ?, ?, ?, ?, current_timestamp, current_timestamp )",
                           $request->user_no, $request->path, $etag, $request->raw_post, $ic->type, $author );
    if ( !$qry->Exec("PUT") ) rollback_on_error( $caldav_context, $request->user_no, $request->path);
  }
  else {
    $qry = new PgQuery( "BEGIN;UPDATE caldav_data SET caldav_data=?, dav_etag=?, caldav_type=?, logged_user=?, modified=current_timestamp WHERE user_no=? AND dav_name=?",
                           $request->raw_post, $etag, $ic->type, $author, $request->user_no, $request->path );
    if ( !$qry->Exec("PUT") ) rollback_on_error( $caldav_context, $request->user_no, $request->path);
  }

  $sql = ( $ic->tz_locn == '' ? '' : "SET TIMEZONE TO ".qpg($ic->tz_locn).";" );

  $dtstart = $ic->Get('DTSTART');
  if ( (!isset($dtstart) || $dtstart == "") && $ic->Get('DUE') != "" ) {
    $dtstart = $ic->Get('DUE');
  }

  $dtend = $ic->Get('DTEND');
  if ( (!isset($dtend) || "$dtend" == "") && $ic->Get('DURATION') != "" AND $dtstart != "" ) {
    $duration = preg_replace( '#[PT]#', ' ', $ic->Get('DURATION') );
    $dtend = '('.qpg($dtstart).'::timestamp with time zone + '.qpg($duration).'::interval)';
  }
  else {
    dbg_error_log( "PUT", " DTEND: '%s', DTSTART: '%s', DURATION: '%s'", $dtend, $dtstart, $ic->Get('DURATION') );
    $dtend = qpg($dtend);
  }

  $last_modified = $ic->Get("LAST-MODIFIED");
  if ( !isset($last_modified) || $last_modified == '' ) {
    $last_modified = gmdate( 'Ymd\THis\Z' );
  }

  $dtstamp = $ic->Get("DTSTAMP");
  if ( !isset($dtstamp) || $dtstamp == '' ) {
    $dtstamp = $last_modified;
  }

  $class = $ic->Get("class");
  /* Check and see if we should over ride the class. */
  if ( public_events_only($user_no, $path) ) {
    $class = 'PUBLIC';
  }

  /*
   * It seems that some calendar clients don't set a class...
   * RFC2445, 4.8.1.3:
   * Default is PUBLIC
   */
  if ( !isset($class) || $class == '' ) {
    $class = 'PUBLIC';
  }


  if ( $put_action_type != 'INSERT' ) {
    $sql .= "DELETE FROM calendar_item WHERE user_no=$request->user_no AND dav_name=".qpg($request->path).";";
  }
  $sql .= <<<EOSQL
  INSERT INTO calendar_item (user_no, dav_name, dav_etag, uid, dtstamp, dtstart, dtend, summary, location, class, transp,
                      description, rrule, tz_id, last_modified, url, priority, created, due, percent_complete, status )
                   VALUES ( ?, ?, ?, ?, ?, ?, $dtend, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
  COMMIT;
EOSQL;

  $qry = new PgQuery( $sql, $request->user_no, $request->path, $etag, $ic->Get('UID'), $dtstamp,
                            $ic->Get('DTSTART'), $ic->Get('SUMMARY'), $ic->Get('LOCATION'),
                            $class, $ic->Get('TRANSP'), $ic->Get('DESCRIPTION'), $ic->Get('RRULE'), $ic->Get('TZ_ID'),
                            $last_modified, $ic->Get('URL'), $ic->Get('PRIORITY'), $ic->Get('CREATED'),
                            $ic->Get('DUE'), $ic->Get('PERCENT-COMPLETE'), $ic->Get('STATUS')
                      );
  if ( !$qry->Exec("PUT") ) rollback_on_error( $caldav_context, $request->user_no, $request->path);
  dbg_error_log( "PUT", "User: %d, ETag: %s, Path: %s", $author, $etag, $request->path);

  header(sprintf('ETag: "%s"', (isset($bogus_etag) ? $bogus_etag : $etag) ) );

  return $put_action_type;
}

