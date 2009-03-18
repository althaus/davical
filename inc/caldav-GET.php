<?php
/**
* CalDAV Server - handle GET method
*
* @package   davical
* @subpackage   caldav
* @author    Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Catalyst .Net Ltd, Morphoss Ltd <http://www.morphoss.com/>
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("get", "GET method handler");

require_once("iCalendar.php");

if ( ! $request->AllowedTo('read') ) {
  $request->DoResponse( 403, translate("You may not access that calendar") );
}

if ( $request->IsCollection() ) {
  /**
  * The CalDAV specification does not define GET on a collection, but typically this is
  * used as a .ics download for the whole collection, which is what we do also.
  *
  * @todo Change this to reference the collection_id of the collection at this location.
  */
  $order_clause = ( isset($c->strict_result_ordering) && $c->strict_result_ordering ? " ORDER BY dav_id" : "");
  $qry = new PgQuery( "SELECT caldav_data, class, caldav_type, calendar_item.user_no, logged_user FROM caldav_data INNER JOIN calendar_item USING ( dav_id ) WHERE caldav_data.user_no = ? AND caldav_data.dav_name ~ ? $order_clause", $request->user_no, $request->path.'[^/]+$');
}
else {
  $qry = new PgQuery( "SELECT caldav_data, caldav_data.dav_etag, class, caldav_type, calendar_item.user_no, logged_user FROM caldav_data INNER JOIN calendar_item USING ( dav_id ) WHERE caldav_data.user_no = ? AND caldav_data.dav_name = ? ;", $request->user_no, $request->path);
}

if ( !$qry->Exec("GET") ) {
  $request->DoResponse( 500, translate("Database Error") );
}
else if ( $qry->rows == 1 && ! $request->IsCollection() ) {
  $event = $qry->Fetch();

  /** Default deny... */
  $allowed = false;
  if ( $request->AllowedTo('all') || $session->user_no == $event->user_no || $session->user_no == $event->logged_user
        || ( $c->allow_get_email_visibility && $resource->IsAttendee($session->email) ) ) {
    /**
    * These people get to see all of the event, and they should always
    * get any alarms as well.
    */
    $allowed = true;
  }
  else if ( $event->class != 'PRIVATE' ) {
    if ( $event->class == 'CONFIDENTIAL' && ! $request->AllowedTo('modify') ) {
      // if the event is confidential we fake one that just says "Busy"
      $confidential = new iCalComponent();

      $ical = new iCalComponent( $event->caldav_data );
      $resources = $ical->GetComponents('VTIMEZONE',false);
      $first = $resources[0];

      $confidential->SetType($event->caldav_type);
      $confidential->AddProperty( 'SUMMARY', translate('Busy') );
      $confidential->AddProperty( 'CLASS', 'CONFIDENTIAL' );
      $confidential->SetProperties( $first->GetProperties('DTSTART'), 'DTSTART' );
      $confidential->SetProperties( $first->GetProperties('RRULE'), 'RRULE' );
      $confidential->SetProperties( $first->GetProperties('DURATION'), 'DURATION' );
      $confidential->SetProperties( $first->GetProperties('DTEND'), 'DTEND' );

      $vcal = new iCalComponent();
      $vcal->VCalendar();
      $vcal->AddComponent($confidential);
      $event->caldav_data = $vcal->Render();
      $allowed = true;
    }
    else {
      /**
      * We don't do the hide_alarm nonsense here.  It only really ever applied to Mozilla,
      * it's fixed in 0.8+ anyway and Mozilla doesn't do GET requests... :-)
      */
      $allowed = true;
    }
  }

  if ( ! $allowed ) {
    $request->DoResponse( 403, translate("Forbidden") );
  }

  header( "Etag: \"$event->dav_etag\"" );
  header( "Content-Length: ".strlen($event->caldav_data) );
  $request->DoResponse( 200, ($request->method == "HEAD" ? "" : $event->caldav_data), "text/calendar" );
}
else if ( $qry->rows < 1 && ! $request->IsCollection() ) {
  $request->DoResponse( 404, translate("Calendar Resource Not Found.") );
}
else {
  /**
  * Here we are constructing a whole calendar response for this collection, including
  * the timezones that are referred to by the events we have selected.
  */
  $vcal = new iCalComponent();
  $vcal->VCalendar( array("X-WR-CALNAME" => $request->collection->dav_displayname ) );

  $need_zones = array();
  $timezones = array();
  while( $event = $qry->Fetch() ) {
    $ical = new iCalComponent( $event->caldav_data );

    /** Save the timezone component(s) into a minimal set for inclusion later */
    $event_zones = $ical->GetComponents('VTIMEZONE',true);
    foreach( $event_zones AS $k => $tz ) {
      $tzid = $tz->GetPValue('TZID');
      if ( !isset($tzid) ) continue ;
      if ( $tzid != '' && !isset($timezones[$tzid]) ) {
        $timezones[$tzid] = $tz;
      }
    }

    /** Work out which ones are actually used here */
    $resources = $ical->GetComponents('VTIMEZONE',false);
    foreach( $resources AS $k => $resource ) {
      $tzid = $resource->GetPParamValue('DTSTART', 'TZID');      if ( isset($tzid) && !isset($need_zones[$tzid]) ) $need_zones[$tzid] = 1;
      $tzid = $resource->GetPParamValue('DUE',     'TZID');      if ( isset($tzid) && !isset($need_zones[$tzid]) ) $need_zones[$tzid] = 1;
      $tzid = $resource->GetPParamValue('DTEND',   'TZID');      if ( isset($tzid) && !isset($need_zones[$tzid]) ) $need_zones[$tzid] = 1;

      if ( $request->AllowedTo('all') || $session->user_no == $event->user_no || $session->user_no == $event->logged_user
            || ( $c->allow_get_email_visibility && $resource->IsAttendee($session->email) ) ) {
        /**
        * These people get to see all of the event, and they should always
        * get any alarms as well.
        */
        $vcal->AddComponent($resource);
        continue;
      }
      /** No visibility even of the existence of these events if they aren't admin/owner/attendee */
      if ( $event->class == 'PRIVATE' ) continue;

      // the user is not admin / owner of this calendarlooking at his calendar and can not admin the other cal
      if ( $event->class == 'CONFIDENTIAL' ) {
        // if the event is confidential we fake one that just says "Busy"
        $confidential = new iCalComponent();
        $confidential->SetType($resource->GetType());
        $confidential->AddProperty( 'SUMMARY', translate('Busy') );
        $confidential->AddProperty( 'CLASS', 'CONFIDENTIAL' );
        $confidential->SetProperties( $resource->GetProperties('DTSTART'), 'DTSTART' );
        $confidential->SetProperties( $resource->GetProperties('RRULE'), 'RRULE' );
        $confidential->SetProperties( $resource->GetProperties('DURATION'), 'DURATION' );
        $confidential->SetProperties( $resource->GetProperties('DTEND'), 'DTEND' );

        $vcal->AddComponent($confidential);
      }
      elseif ( isset($c->hide_alarm) && $c->hide_alarm ) {
        // Otherwise we hide the alarms (if configured to)
        $resource->ClearComponents('VALARM');
        $vcal->AddComponent($resource);
      }
      else {
        $vcal->AddComponent($resource);
      }
    }
  }

  /** Put the timezones on there that we need */
  foreach( $need_zones AS $tzid => $v ) {
    if ( isset($timezones[$tzid]) ) $vcal->AddComponent($timezones[$tzid]);
  }

  $response = $vcal->Render();
  header( "Content-Length: ".strlen($response) );
  header( 'Etag: "'.$request->collection->dav_etag.'"' );
  $request->DoResponse( 200, ($request->method == "HEAD" ? "" : $response), "text/calendar" );
}

