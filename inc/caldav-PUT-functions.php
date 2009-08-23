<?php
/**
* CalDAV Server - handle PUT method
*
* @package   davical
* @subpackage   caldav
* @author    Andrew McMillan <andrew@morphoss.com>
* @copyright Morphoss Ltd - http://www.morphoss.com/
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2 or later version
*/

/**
* Check if the user wants to put just one VEVENT/VTODO or a whole calendar
* if the collection = calendar = $request_container doesn't exist then create it
* return true if it's a whole calendar
*/

include_once("iCalendar.php");

/**
* A regex which will match most reasonable timezones acceptable to PostgreSQL.
*/
$tz_regex = ':^(Africa|America|Antarctica|Arctic|Asia|Atlantic|Australia|Brazil|Canada|Chile|Etc|Europe|Indian|Mexico|Mideast|Pacific|US)/[a-z_]+$:i';

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
      if ( preg_match( '#^(.*/)([^/]+)/$#', $request_container, $matches ) ) {//(
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
* Create a scheduling request in the schedule inbox for the
* @param iCalComponent $resource The VEVENT/VTODO/... resource we are scheduling
* @param iCalProp $attendee The attendee we are scheduling
* @return float The result of the scheduling request, per caldav-sched #3.5.4
*/
function write_scheduling_request( &$resource, $attendee ) {
  return '5.4';
}

/**
* Create scheduling requests in the schedule inbox for the
* @param iCalComponent $resource The VEVENT/VTODO/... resource we are scheduling
*/
function create_scheduling_requests( &$resource ) {
  if ( ! is_object($resource) ) {
    dbg_error_log( "PUT", "create_scheduling_requests called with non-object parameter (%s)", gettype($resource) );
    return;
  }

  $attendees = $resource->GetPropertiesByPath('/VCALENDAR/*/ATTENDEE');
  if ( preg_match( '# iCal/\d#', $_SERVER['HTTP_USER_AGENT']) ) {
    dbg_error_log( "POST", "Non-compliant iCal request.  Using X-WR-ATTENDEE property" );
    $wr_attendees = $resource->GetPropertiesByPath('/VCALENDAR/*/X-WR-ATTENDEE');
    foreach( $wr_attendees AS $k => $v ) {
      $attendees[] = $v;
    }
  }
  if ( count($attendees) == 0 ) {
    dbg_error_log( "PUT", "Event has no attendees - no scheduling required.", count($attendees) );
    return;
  }

  dbg_error_log( "PUT", "Adding to scheduling inbox %d attendees", count($attendees) );
  foreach( $attendees AS $attendee ) {
    $attendee->SetParameterValue( 'SCHEDULE-STATUS', write_scheduling_request( $resource, $attendee->Value() ) );
  }
}


/**
* Update scheduling requests in the schedule inbox for the
* @param iCalComponent $resource The VEVENT/VTODO/... resource we are scheduling
*/
function update_scheduling_requests( &$resource ) {
  if ( ! is_object($resource) ) {
    dbg_error_log( "PUT", "update_scheduling_requests called with non-object parameter (%s)", gettype($resource) );
    return;
  }

  $attendees = $resource->GetPropertiesByPath('/VCALENDAR/*/ATTENDEE');
  if ( preg_match( '# iCal/\d#', $_SERVER['HTTP_USER_AGENT']) ) {
    dbg_error_log( "POST", "Non-compliant iCal request.  Using X-WR-ATTENDEE property" );
    $wr_attendees = $resource->GetPropertiesByPath('/VCALENDAR/*/X-WR-ATTENDEE');
    foreach( $wr_attendees AS $k => $v ) {
      $attendees[] = $v;
    }
  }
  if ( count($attendees) == 0 ) {
    dbg_error_log( "PUT", "Event has no attendees - no scheduling required.", count($attendees) );
    return;
  }

  dbg_error_log( "PUT", "Adding to scheduling inbox %d attendees", count($attendees) );
  foreach( $attendees AS $attendee ) {
    $attendee->SetParameterValue( 'SCHEDULE-STATUS', write_scheduling_request( $resource, $attendee->Value() ) );
  }
}


/**
* This function will import a whole calendar
* @param string $ics_content the ics file to import
* @param int $user_no the user wich will receive this ics file
* @param string $path the $path where it will be store such as /user_foo/home/
* @param boolean $caldav_context Whether we are responding via CalDAV or interactively
*
* Any VEVENTs with the same UID will be concatenated together
*/
function import_collection( $ics_content, $user_no, $path, $caldav_context ) {
  global $c, $session, $tz_regex;

  if ( ! ini_get('open_basedir') && (isset($c->dbg['ALL']) || isset($c->dbg['put'])) ) {
    $fh = fopen('/tmp/PUT-2.txt','w');
    if ( $fh ) {
      fwrite($fh,$ics_content);
      fclose($fh);
    }
  }

  $calendar = new iCalComponent($ics_content);
  $timezones = $calendar->GetComponents('VTIMEZONE',true);
  $components = $calendar->GetComponents('VTIMEZONE',false);

  $tz_ids    = array();
  foreach( $timezones AS $k => $tz ) {
    $tz_ids[$tz->GetPValue('TZID')] = $k;
  }

  /** Build an array of resources.  Each resource is an array of iCalComponent */
  $resources = array();
  foreach( $components AS $k => $comp ) {
    $uid = $comp->GetPValue('UID');
    if ( !isset($resources[$uid]) ) $resources[$uid] = array();
    $resources[$uid][] = $comp;

    /** Ensure we have the timezone component for this in our array as well */
    $tzid = $comp->GetPParamValue('DTSTART', 'TZID');
    if ( !isset($tzid) || $tzid == "" ) $tzid = $comp->GetPParamValue('DUE','TZID');
    if ( !isset($resources[$uid][$tzid]) && isset($tz_ids[$tzid]) ) {
      $resources[$uid][$tzid] = $timezones[$tz_ids[$tzid]];
    }
  }


  $sql = "SELECT * FROM collection WHERE user_no = ? AND dav_name = ?;";
  $qry = new PgQuery( $sql, $user_no, $path );
  if ( ! $qry->Exec("PUT") ) rollback_on_error( $caldav_context, $user_no, $path );
  if ( ! $qry->rows == 1 ) {
    dbg_error_log( "ERROR", " PUT: Collection does not exist at '%s' for user %d", $path, $user_no );
    rollback_on_error( $caldav_context, $user_no, $path );
  }
  $collection = $qry->Fetch();

  $qry = new PgQuery("BEGIN; DELETE FROM calendar_item WHERE user_no=? AND collection_id = ?; DELETE FROM caldav_data WHERE user_no=? AND collection_id = ?;", $user_no, $collection->collection_id, $user_no, $collection->collection_id);
  if ( !$qry->Exec("PUT") ) rollback_on_error( $caldav_context, $user_no, $collection->collection_id );

  $last_tz_locn = '';
  foreach( $resources AS $uid => $resource ) {
    /** Construct the VCALENDAR data */
    $vcal = new iCalComponent();
    $vcal->VCalendar();
    $vcal->SetComponents($resource);
    create_scheduling_requests($vcal);
    $icalendar = $vcal->Render();

    /** As ever, we mostly deal with the first resource component */
    $first = $resource[0];

    $sql = '';
    $etag = md5($icalendar);
    $type = $first->GetType();
    $resource_path = sprintf( "%s%s.ics", $path, $uid );
    $qry = new PgQuery( "INSERT INTO caldav_data ( user_no, dav_name, dav_etag, caldav_data, caldav_type, logged_user, created, modified, collection_id ) VALUES( ?, ?, ?, ?, ?, ?, current_timestamp, current_timestamp, ? )",
                          $user_no, $resource_path, $etag, $icalendar, $type, $session->user_no, $collection->collection_id );
    if ( !$qry->Exec("PUT") ) rollback_on_error( $caldav_context, $user_no, $path );

    $dtstart = $first->GetPValue('DTSTART');
    if ( (!isset($dtstart) || $dtstart == "") && $first->GetPValue('DUE') != "" ) {
      $dtstart = $first->GetPValue('DUE');
    }

    $dtend = $first->GetPValue('DTEND');
    if ( (!isset($dtend) || "$dtend" == "") ) {
      if ( $first->GetPValue('DURATION') != "" AND $dtstart != "" ) {
        $duration = preg_replace( '#[PT]#', ' ', $first->GetPValue('DURATION') );
        $dtend = '('.qpg($dtstart).'::timestamp with time zone + '.qpg($duration).'::interval)';
      }
      elseif ( $first->GetType() == 'VEVENT' ) {
        /**
        * From RFC2445 4.6.1:
        * For cases where a "VEVENT" calendar component specifies a "DTSTART"
        * property with a DATE data type but no "DTEND" property, the events
        * non-inclusive end is the end of the calendar date specified by the
        * "DTSTART" property. For cases where a "VEVENT" calendar component specifies
        * a "DTSTART" property with a DATE-TIME data type but no "DTEND" property,
        * the event ends on the same calendar date and time of day specified by the
        * "DTSTART" property.
        *
        * So we're looking for 'VALUE=DATE', to identify the duration, effectively.
        *
        */
        $value_type = $first->GetPParamValue('DTSTART','VALUE');
        dbg_error_log("PUT","DTSTART without DTEND. DTSTART value type is %s", $value_type );
        if ( isset($value_type) && $value_type == 'DATE' )
          $dtend = '('.qpg($dtstart)."::timestamp with time zone::date + '1 day'::interval)";
        else
          $dtend = qpg($dtstart);

      }
      if ( $dtend == "" ) $dtend = 'NULL';
    }
    else {
      dbg_error_log( "PUT", " DTEND: '%s', DTSTART: '%s', DURATION: '%s'", $dtend, $dtstart, $first->GetPValue('DURATION') );
      $dtend = qpg($dtend);
    }

    $last_modified = $first->GetPValue("LAST-MODIFIED");
    if ( !isset($last_modified) || $last_modified == '' ) $last_modified = gmdate( 'Ymd\THis\Z' );

    $dtstamp = $first->GetPValue("DTSTAMP");
    if ( !isset($dtstamp) || $dtstamp == '' ) $dtstamp = $last_modified;

    /** RFC2445, 4.8.1.3: Default is PUBLIC, or also if overridden by the collection settings */
    $class = ($collection->public_events_only == 't' ? 'PUBLIC' : $first->GetPValue("CLASS") );
    if ( !isset($class) || $class == '' ) $class = 'PUBLIC';


    /** Calculate what timezone to set, first, if possible */
    $tzid = $first->GetPParamValue('DTSTART','TZID');
    if ( !isset($tzid) || $tzid == "" ) $tzid = $first->GetPParamValue('DUE','TZID');
    if ( isset($tzid) && $tzid != "" ) {
      if ( isset($resource[$tzid]) ) {
        $tz = $resource[$tzid];
        $tz_locn = $tz->GetPValue('X-LIC-LOCATION');
      }
      else {
        unset($tz);
        unset($tz_locn);
      }
      if ( ! isset($tz_locn) || ! preg_match( $tz_regex, $tz_locn ) ) {
        if ( preg_match( '#([^/]+/[^/]+)$#', $tzid, $matches ) ) {
          $tz_locn = $matches[1];
        }
      }
      dbg_error_log( "PUT", " Using TZID[%s] and location of [%s]", $tzid, (isset($tz_locn) ? $tz_locn : '') );
      if ( isset($tz_locn) && ($tz_locn != $last_tz_locn) && preg_match( $tz_regex, $tz_locn ) ) {
        dbg_error_log( "PUT", " Setting timezone to %s", $tz_locn );
        $sql .= ( $tz_locn == '' ? '' : "SET TIMEZONE TO ".qpg($tz_locn).";" );
        $last_tz_locn = $tz_locn;
      }
      $qry = new PgQuery("SELECT tz_locn FROM time_zone WHERE tz_id = ?", $tzid );
      if ( $qry->Exec() && $qry->rows == 0 ) {
        $qry = new PgQuery("INSERT INTO time_zone (tz_id, tz_locn, tz_spec) VALUES(?,?,?)", $tzid, $tz_locn, (isset($tz) ? $tz->Render() : null) );
        $qry->Exec();
      }
      if ( !isset($tz_locn) || $tz_locn == "" ) $tz_locn = $tzid;
    }
    else {
      $tzid = null;
    }

    $sql .= <<<EOSQL
    INSERT INTO calendar_item (user_no, dav_name, dav_etag, uid, dtstamp, dtstart, dtend, summary, location, class, transp,
                      description, rrule, tz_id, last_modified, url, priority, created, due, percent_complete, status, collection_id )
                   VALUES ( ?, ?, ?, ?, ?, ?, $dtend, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
EOSQL;

    $qry = new PgQuery( $sql, $user_no, $resource_path, $etag, $first->GetPValue('UID'), $dtstamp,
                              $first->GetPValue('DTSTART'), $first->GetPValue('SUMMARY'), $first->GetPValue('LOCATION'),
                              $class, $first->GetPValue('TRANSP'), $first->GetPValue('DESCRIPTION'), $first->GetPValue('RRULE'), $tzid,
                              $last_modified, $first->GetPValue('URL'), $first->GetPValue('PRIORITY'), $first->GetPValue('CREATED'),
                              $first->GetPValue('DUE'), $first->GetPValue('PERCENT-COMPLETE'), $first->GetPValue('STATUS'), $collection->collection_id
                        );
    if ( !$qry->Exec("PUT") ) rollback_on_error( $caldav_context, $user_no, $path);

    create_scheduling_requests( $vcal );
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
  global $tz_regex;

  $etag = md5($request->raw_post);
  $ic = new iCalComponent( $request->raw_post );

  dbg_log_array( "PUT", 'EVENT', $ic->components, true );

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

  $resources = $ic->GetComponents('VTIMEZONE',false); // Not matching VTIMEZONE
  $first = $resources[0];

  if ( $put_action_type == 'INSERT' ) {
    create_scheduling_requests($vcal);
    $qry = new PgQuery( "BEGIN; INSERT INTO caldav_data ( user_no, dav_name, dav_etag, caldav_data, caldav_type, logged_user, created, modified, collection_id ) VALUES( ?, ?, ?, ?, ?, ?, current_timestamp, current_timestamp, ? )",
                           $request->user_no, $request->path, $etag, $request->raw_post, $first->GetType(), $author, $request->collection_id );
    if ( !$qry->Exec("PUT") ) rollback_on_error( $caldav_context, $request->user_no, $request->path);
  }
  else {
    update_scheduling_requests($vcal);
    $qry = new PgQuery( "BEGIN;UPDATE caldav_data SET caldav_data=?, dav_etag=?, caldav_type=?, logged_user=?, modified=current_timestamp WHERE user_no=? AND dav_name=?",
                           $request->raw_post, $etag, $first->GetType(), $author, $request->user_no, $request->path );
    if ( !$qry->Exec("PUT") ) rollback_on_error( $caldav_context, $request->user_no, $request->path);
  }

  $dtstart = $first->GetPValue('DTSTART');
  if ( (!isset($dtstart) || $dtstart == "") && $first->GetPValue('DUE') != "" ) {
    $dtstart = $first->GetPValue('DUE');
  }

  $dtend = $first->GetPValue('DTEND');
  if ( (!isset($dtend) || "$dtend" == "") ) {
    if ( $first->GetPValue('DURATION') != "" AND $dtstart != "" ) {
      $duration = preg_replace( '#[PT]#', ' ', $first->GetPValue('DURATION') );
      $dtend = '('.qpg($dtstart).'::timestamp with time zone + '.qpg($duration).'::interval)';
    }
    elseif ( $first->GetType() == 'VEVENT' ) {
      /**
      * From RFC2445 4.6.1:
      * For cases where a "VEVENT" calendar component specifies a "DTSTART"
      * property with a DATE data type but no "DTEND" property, the events
      * non-inclusive end is the end of the calendar date specified by the
      * "DTSTART" property. For cases where a "VEVENT" calendar component specifies
      * a "DTSTART" property with a DATE-TIME data type but no "DTEND" property,
      * the event ends on the same calendar date and time of day specified by the
      * "DTSTART" property.
      *
      * So we're looking for 'VALUE=DATE', to identify the duration, effectively.
      *
      */
      $value_type = $first->GetPParamValue('DTSTART','VALUE');
      dbg_error_log("PUT","DTSTART without DTEND. DTSTART value type is %s", $value_type );
      if ( isset($value_type) && $value_type == 'DATE' )
        $dtend = '('.qpg($dtstart)."::timestamp with time zone::date + '1 day'::interval)";
      else
        $dtend = qpg($dtstart);

    }
    if ( $dtend == "" ) $dtend = 'NULL';
  }
  else {
    dbg_error_log( "PUT", " DTEND: '%s', DTSTART: '%s', DURATION: '%s'", $dtend, $dtstart, $first->GetPValue('DURATION') );
    $dtend = qpg($dtend);
  }

  $last_modified = $first->GetPValue("LAST-MODIFIED");
  if ( !isset($last_modified) || $last_modified == '' ) {
    $last_modified = gmdate( 'Ymd\THis\Z' );
  }

  $dtstamp = $first->GetPValue("DTSTAMP");
  if ( !isset($dtstamp) || $dtstamp == '' ) {
    $dtstamp = $last_modified;
  }

  $class = $first->GetPValue("CLASS");
  /* Check and see if we should over ride the class. */
  if ( public_events_only($request->user_no, $request->path) ) {
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


  /**
  * Build the SQL for inserting/updating the calendar_item record
  */
  $sql = '';

  /** Calculate what timezone to set, first, if possible */
  $tzid = $first->GetPParamValue('DTSTART','TZID');
  if ( !isset($tzid) || $tzid == "" ) $tzid = $first->GetPParamValue('DUE','TZID');
  $timezones = $ic->GetComponents('VTIMEZONE');
  foreach( $timezones AS $k => $tz ) {
    if ( $tz->GetPValue('TZID') != $tzid ) {
      /**
      * We'll pretend they didn't forget to give us a TZID and that they
      * really hope the server is running in the timezone they supplied... but be noisy about it.
      */
      dbg_error_log( "ERROR", " Event includes TZID[%s] but users TZID[%s]!", $tz->GetPValue('TZID'), $tzid );
      $tzid = $tz->GetPValue('TZID');
    }
    // This is the one
    $tz_locn = $tz->GetPValue('X-LIC-LOCATION');
    if ( ! isset($tz_locn) ) {
      if ( preg_match( '#([^/]+/[^/]+)$#', $tzid, $matches ) )
        $tz_locn = $matches[1];
      else {
        dbg_error_log( "ERROR", " Couldn't guess Olsen TZ from TZID[%s].  This may end in tears...", $tzid );
      }
    }
    else {
      if ( ! preg_match( $tz_regex, $tz_locn ) ) {
        if ( preg_match( '#([^/]+/[^/]+)$#', $tzid, $matches ) ) $tz_locn = $matches[1];
      }
    }
    dbg_error_log( "PUT", " Using TZID[%s] and location of [%s]", $tzid, $tz_locn );
    if ( isset($tz_locn) && preg_match( $tz_regex, $tz_locn ) ) {
      dbg_error_log( "PUT", " Setting timezone to %s", $tz_locn );
      $sql = ( $tz_locn == '' ? '' : "SET TIMEZONE TO ".qpg($tz_locn).";" );
    }
    $qry = new PgQuery("SELECT tz_locn FROM time_zone WHERE tz_id = ?", $tzid );
    if ( $qry->Exec() && $qry->rows == 0 ) {
      $qry = new PgQuery("INSERT INTO time_zone (tz_id, tz_locn, tz_spec) VALUES(?,?,?)", $tzid, $tz_locn, $tz->Render() );
      $qry->Exec();
    }
    if ( !isset($tz_locn) || $tz_locn == "" ) {
      $tz_locn = $tzid;
    }
  }

  if ( $put_action_type != 'INSERT' ) {
    $sql .= "DELETE FROM calendar_item WHERE user_no=$request->user_no AND dav_name=".qpg($request->path).";";
  }
  $sql .= <<<EOSQL
  INSERT INTO calendar_item (user_no, dav_name, dav_etag, uid, dtstamp, dtstart, dtend, summary, location, class, transp,
                      description, rrule, tz_id, last_modified, url, priority, created, due, percent_complete, status, collection_id )
                   VALUES ( ?, ?, ?, ?, ?, ?, $dtend, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
  COMMIT;
EOSQL;

  if ( function_exists('log_caldav_action') ) {
    log_caldav_action( $put_action_type, $first->GetPValue('UID'), $request->user_no, $request->collection_id, $request->path );
  }

  $qry = new PgQuery( $sql, $request->user_no, $request->path, $etag, $first->GetPValue('UID'), $dtstamp,
                            $first->GetPValue('DTSTART'), $first->GetPValue('SUMMARY'), $first->GetPValue('LOCATION'),
                            $class, $first->GetPValue('TRANSP'), $first->GetPValue('DESCRIPTION'), $first->GetPValue('RRULE'), $tzid,
                            $last_modified, $first->GetPValue('URL'), $first->GetPValue('PRIORITY'), $first->GetPValue('CREATED'),
                            $first->GetPValue('DUE'), $first->GetPValue('PERCENT-COMPLETE'), $first->GetPValue('STATUS'), $request->collection_id
                      );
  if ( !$qry->Exec("PUT") ) rollback_on_error( $caldav_context, $request->user_no, $request->path);
  dbg_error_log( "PUT", "User: %d, ETag: %s, Path: %s", $author, $etag, $request->path);

  header(sprintf('ETag: "%s"', (isset($bogus_etag) ? $bogus_etag : $etag) ) );

  return $put_action_type;
}

