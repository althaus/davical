<?php
/**
* CalDAV Server - handle GET method
*
* @package   davical
* @subpackage   caldav
* @author    Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Catalyst .Net Ltd, Morphoss Ltd <http://www.morphoss.com/>
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
*/
dbg_error_log("get", "GET method handler");

require_once("iCalendar.php");


$request->NeedPrivilege( array('urn:ietf:params:xml:ns:caldav:read-free-busy','DAV::read') );

if ( $request->IsCollection() ) {
  if ( $request->IsCalendar() ) {
	  /**
	  * The CalDAV specification does not define GET on a collection, but typically this is
	  * used as a .ics download for the whole collection, which is what we do also.
	  *
	  * @todo Change this to reference the collection_id of the collection at this location.
	  */
    $sql = 'SELECT caldav_data, class, caldav_type, calendar_item.user_no, logged_user FROM caldav_data INNER JOIN calendar_item USING ( dav_id ) WHERE caldav_data.dav_name ~ :dav_name ';
	  if ( isset($c->strict_result_ordering) && $c->strict_result_ordering ) $sql .= ' ORDER BY dav_id';
    $params = array( ':dav_name' => $request->path.'[^/]+$');
  }
  else {
    /** RFC2616 says we must send an Allow header if we send a 405 */
    header("Allow: PROPFIND,PROPPATCH,OPTIONS,MKCOL,REPORT,DELETE");
    $request->DoResponse( 405, translate("GET requests are only handled on calendar collections.") );
  }
}
else {
  $sql = 'SELECT caldav_data, caldav_data.dav_etag, class, caldav_type, calendar_item.user_no, logged_user FROM caldav_data INNER JOIN calendar_item USING ( dav_id ) WHERE caldav_data.dav_name = :dav_name ';
  $params = array( ':dav_name' => $request->path);
}

$qry = new AwlQuery( $sql, $params );
if ( !$qry->Exec("GET",__LINE__,__FILE__) ) {
  $request->DoResponse( 500, translate("Database Error") );
}
else if ( $qry->rows() == 1 && ! $request->IsCollection() ) {
  $event = $qry->Fetch();
  $resource = new iCalComponent( $event->caldav_data );

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
    $allowed = true; // but we may well obfuscate it below
    if ( ! $request->HavePrivilegeTo('DAV::read') || ( $event->class == 'CONFIDENTIAL' && ! $request->HavePrivilegeTo('DAV::write-content') ) ) {
      // The user is not admin / owner of this calendarlooking at his calendar and can not admin the other cal,
      // or maybe they don't have *read* access but they got here, so they must at least have free/busy access
      // so we will present an obfuscated version of the event that just says "Busy" (translated :-)
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
      $confidential->SetProperties( $first->GetProperties('UID'), 'UID' );
      $confidential->SetProperties( $first->GetProperties('CREATED'), 'CREATED' );

      $ical->SetComponents( array($confidential), $confidential->GetType() );
      $event->caldav_data = $ical->Render();
    }
  }
  // else $event->class == 'PRIVATE' and this person may not see it.

  if ( ! $allowed ) {
    $request->DoResponse( 403, translate("Forbidden") );
  }

  header( "Etag: \"$event->dav_etag\"" );
  header( "Content-Length: ".strlen($event->caldav_data) );
  $request->DoResponse( 200, ($request->method == "HEAD" ? "" : $event->caldav_data), "text/calendar; charset=\"utf-8\"" );
}
else if ( $qry->rows() < 1 && ! $request->IsCollection() ) {
  $request->DoResponse( 404, translate("Calendar Resource Not Found.") );
}
else {
  /**
  * Here we are constructing a whole calendar response for this collection, including
  * the timezones that are referred to by the events we have selected.
  */
  $vcal = new iCalComponent();
  if ( isset($request->collection->dav_displayname) ) {
    $vcal->VCalendar( array("X-WR-CALNAME" => $request->collection->dav_displayname) );
  }

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

      if ( ! $request->HavePrivilegeTo('DAV::read') || $event->class == 'CONFIDENTIAL' ) {
        // The user is not admin / owner of this calendar looking at his calendar and can not admin the other cal,
        // or maybe they don't have *read* access but they got here, so they must at least have free/busy access
        // so we will present an obfuscated version of the event that just says "Busy" (translated :-)
        $confidential = new iCalComponent();
        $confidential->SetType($resource->GetType());
        $confidential->AddProperty( 'SUMMARY', translate('Busy') );
        $confidential->AddProperty( 'CLASS', 'CONFIDENTIAL' );
        $confidential->SetProperties( $resource->GetProperties('DTSTART'), 'DTSTART' );
        $confidential->SetProperties( $resource->GetProperties('RRULE'), 'RRULE' );
        $confidential->SetProperties( $resource->GetProperties('DURATION'), 'DURATION' );
        $confidential->SetProperties( $resource->GetProperties('DTEND'), 'DTEND' );
        $confidential->SetProperties( $resource->GetProperties('UID'), 'UID' );
        $confidential->SetProperties( $resource->GetProperties('CREATED'), 'CREATED' );


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
  $request->DoResponse( 200, ($request->method == "HEAD" ? "" : $response), "text/calendar; charset=\"utf-8\"" );
}

