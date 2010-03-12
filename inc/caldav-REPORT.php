<?php
/**
* CalDAV Server - handle REPORT method
*
* @package   davical
* @subpackage   caldav
* @author    Andrew McMillan <andrew@morphoss.com>
* @copyright Catalyst .Net Ltd, Morphoss Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("REPORT", "method handler");

require_once("XMLDocument.php");
require_once('DAVResource.php');

require_once('RRule-v2.php');

if ( ! ini_get('open_basedir') && (isset($c->dbg['ALL']) || (isset($c->dbg['report']) && $c->dbg['report'])) ) {
  $fh = fopen('/tmp/REPORT.txt','w');
  if ( $fh ) {
    fwrite($fh,$request->raw_post);
    fclose($fh);
  }
}

if ( ! ($request->AllowedTo('read') || $request->AllowedTo('freebusy')) ) {
  // The specification states that a lack of privileges MUST result in a 404. RFC4791, Section 7.10
  $request->DoResponse( 404 );
}

if ( !isset($request->xml_tags) ) {
  $request->DoResponse( 406, translate("REPORT body contains no XML data!") );
}
$position = 0;
$xmltree = BuildXMLTree( $request->xml_tags, $position);
if ( !is_object($xmltree) ) {
  $request->DoResponse( 406, translate("REPORT body is not valid XML data!") );
}

require_once("iCalendar.php");

$reportnum = -1;
$report = array();
$denied = array();
$unsupported = array();
if ( isset($prop_filter) ) unset($prop_filter);

if ( $xmltree->GetTag() == "urn:ietf:params:xml:ns:caldav:free-busy-query" ) {
  include("caldav-REPORT-freebusy.php");
  exit; // Not that the above include should return anyway
}

$reply = new XMLDocument( array( "DAV:" => "" ) );
switch( $xmltree->GetTag() ) {
  case 'DAV::principal-property-search':
    include("caldav-REPORT-principal.php");
    exit; // Not that it should return anyway.
  case 'DAV::principal-search-property-set':
    include("caldav-REPORT-pps-set.php");
    exit; // Not that it should return anyway.
  case 'DAV::sync-collection':
    include("caldav-REPORT-sync-collection.php");
    exit; // Not that it should return anyway.
  case 'DAV::expand-property':
    include("caldav-REPORT-expand-property.php");
    exit; // Not that it should return anyway.
}


if ( class_exists('RepeatRule') ) {
  /**
  * Expand the event instances for an RDATE or EXDATE property
  *
  * @param string $property RDATE or EXDATE, depending...
  * @param array $component An iCalComponent which is applies for these instances
  * @param array $range_end A date after which we care less about expansion
  *
  * @return array An array keyed on the UTC dates, referring to the component
  */
  function rdate_expand( $dtstart, $property, $component, $range_end = null ) {
    $timezone = $component->GetPParamValue($property, 'TZID');
    $rdate = $component->GetPValue($property);
    $rdates = explode( ',', $rdate );
    $expansion = array();
    foreach( $rdates AS $k => $v ) {
      $rdate = new RepeatRuleDateTime( $v, $timezone);
      $expansion[$rdate->UTC()] = $component;
      if ( $rdate > $range_end ) break;
    }
    return $expansion;
  }


  /**
  * Expand the event instances for an RRULE property
  *
  * @param object $dtstart A RepeatRuleDateTime which is the master dtstart
  * @param string $property RDATE or EXDATE, depending...
  * @param array $component An iCalComponent which is applies for these instances
  * @param array $range_end A date after which we care less about expansion
  *
  * @return array An array keyed on the UTC dates, referring to the component
  */
  function rrule_expand( $dtstart, $property, $component, $range_end ) {
    $expansion = array();

    $recur = $component->GetPValue($property);
    if ( !isset($recur) ) return $expansion;

    $this_start = $component->GetPValue('DTSTART');
    if ( isset($this_start) ) {
      $timezone = $component->GetPParamValue('DTSTART', 'TZID');
      $this_start = new RepeatRuleDateTime($this_start,$timezone);
    }
    else {
      $this_start = clone($dtstart);
    }

    $rule = new RepeatRule( $this_start, $recur );
    $i = 0;
    $result_limit = 1000;
    while( $date = $rule->next() ) {
      $expansion[$date->UTC()] = $component;
      if ( $i >= $result_limit || $date > $range_end ) break;
    }
    return $expansion;
  }


  /**
  * Expand the event instances for an iCalendar VEVENT (or VTODO)
  *
  * @param object $ics An iCalComponent which is the master VCALENDAR
  * @param object $range_start A RepeatRuleDateTime which is the beginning of the range for events
  * @param object $range_end A RepeatRuleDateTime which is the end of the range for events
  *
  * @return iCalComponent The original iCalComponent with expanded events in the range.
  */
  function expand_event_instances( $ics, $range_start = null, $range_end = null ) {
    $components = $ics->GetComponents();

    if ( !isset($range_start) ) { $range_start = new RepeatRuleDateTime(); $range_start->modify('-6 weeks'); }
    if ( !isset($range_end) )   { $range_end   = clone($range_start);      $range_end->modify('+6 months');  }

    $new_components = array();
    $result_limit = 1000;
    $instances = array();
    $expand = false;
    $dtstart = null;
    foreach( $components AS $k => $comp ) {
      if ( $comp->GetType() != 'VEVENT' && $comp->GetType() != 'VTODO' && $comp->GetType() != 'VJOURNAL' ) {
        $new_components[] = $comp;
        continue;
      }
      if ( !isset($dtstart) ) {
        $tzid = $comp->GetPParamValue('DTSTART', 'TZID');
        print( $tzid . "\n");
        $dtstart = new RepeatRuleDateTime( $comp->GetPValue('DTSTART'), $tzid );
        $instances[$dtstart->UTC()] = $comp;
      }
      $p = $comp->GetPValue('RECURRENCE-ID');
      if ( isset($p) && $p != '' ) {
        $range = $comp->GetPParamValue('RECURRENCE-ID', 'RANGE');
        $recur_tzid = $comp->GetPParamValue('RECURRENCE-ID', 'TZID');
        print( __LINE__ . ' - ' .$recur_tzid . "\n");
        $recur_utc = new RepeatRuleDateTime($p,$recur_tzid);
        $recur_utc = $recur_utc->UTC();
        if ( isset($range) && $range == 'THISANDFUTURE' ) {
          foreach( $instances AS $k => $v ) {
            if ( $k >= $recur_utc ) unset($instances[$k]);
          }
        }
        else {
          unset($instances[$recur_utc]);
        }
        $instances[] = $comp;
      }
      $instances = array_merge( $instances, rrule_expand($dtstart, 'RRULE', $comp, $range_end) );
      $instances = array_merge( $instances, rdate_expand($dtstart, 'RDATE', $comp, $range_end) );
      foreach ( rdate_expand($dtstart, 'EXDATE', $comp, $range_end) AS $k => $v ) {
        unset($instances[$k]);
      }
    }

    $last_duration = null;
    $in_range = false;
    $new_components = array();
    $start_utc = $range_start->UTC();
    $end_utc = $range_end->UTC();
    foreach( $instances AS $utc => $comp ) {
      if ( $utc > $end_utc ) break;

      $duration = $comp->GetPValue('DURATION');
      if ( !isset($duration) ) {
        $dtend = new RepeatRuleDateTime( $comp->GetPValue('DTEND'), $comp->GetPParamValue('DTEND', 'TZID'));
        $dtsrt = new RepeatRuleDateTime( $comp->GetPValue('DTSTART'), $comp->GetPParamValue('DTSTART', 'TZID'));
        $duration = sprintf( 'PT%dM', intval(($dtend->epoch() - $dtsrt->epoch()) / 60) );
      }

      if ( $utc < $start_utc ) {
        if ( isset($last_duration) && $duration == $last_duration) {
          if ( $utc < $early_start ) continue;
        }
        else {
          $latest_start = clone($range_start);
          $latest_start->modify('-'.$duration);
          $early_start = $latest_start->UTC();
          $last_duration = $duration;
          if ( $utc < $early_start ) continue;
        }
      }
      $component = clone($comp);
      $component->ClearProperties('DTSTART');
      $component->ClearProperties('DTEND');
      $component->AddProperty('DTSTART', $utc );
      $component->AddProperty('DURATION', $duration );
      $new_components[] = $component;
      $in_range = true;
    }

    if ( $in_range ) {
      $ics->SetComponents($new_components);
    }
    else {
      $ics->SetComponents(array());
    }

    return $ics;
  }
}

/**
* Return XML for a single calendar (or todo) entry from the DB
*
* @param array $properties The properties for this calendar
* @param string $item The calendar data for this calendar
*
* @return string An XML document which is the response for the calendar
*/
function calendar_to_xml( $properties, $item ) {
  global $session, $c, $request, $reply;

  dbg_error_log("REPORT","Building XML Response for item '%s'", $item->dav_name );

  $denied = array();
  $caldav_data = $item->caldav_data;
  $displayname = $item->summary;
  if ( isset($properties['calendar-data']) || isset($properties['displayname']) ) {
    if ( !$request->AllowedTo('all') && $session->user_no != $item->user_no ){
      // the user is not admin / owner of this calendarlooking at his calendar and can not admin the other cal
      /** @todo We should examine the ORGANIZER and ATTENDEE fields in the event.  If this person is there then they should see this */
      if ( $item->class == 'CONFIDENTIAL' || !$request->AllowedTo('read') ) {
        $ical = new iCalComponent( $caldav_data );
        $resources = $ical->GetComponents('VTIMEZONE',false);
        $first = $resources[0];

        // if the event is confidential we fake one that just says "Busy"
        $confidential = new iCalComponent();
        $confidential->SetType($first->GetType());
        $confidential->AddProperty( 'SUMMARY', translate('Busy') );
        $confidential->AddProperty( 'CLASS', 'CONFIDENTIAL' );
        $confidential->SetProperties( $first->GetProperties('DTSTART'), 'DTSTART' );
        $confidential->SetProperties( $first->GetProperties('RRULE'), 'RRULE' );
        $confidential->SetProperties( $first->GetProperties('DURATION'), 'DURATION' );
        $confidential->SetProperties( $first->GetProperties('DTEND'), 'DTEND' );
        $confidential->SetProperties( $first->GetProperties('UID'), 'UID' );
        $ical->SetComponents(array($confidential),$confidential->GetType());

        $caldav_data = $ical->Render();
        $displayname = translate('Busy');
      }
    }
  }

  $url = ConstructURL($item->dav_name);

  $prop = new XMLElement("prop");
  foreach( $properties AS $k => $v ) {
    switch( $k ) {
      case 'getcontentlength':
        $contentlength = strlen($caldav_data);
        $prop->NewElement($k, $contentlength );
        break;
      case 'calendar-data':
        $reply->CalDAVElement($prop, $k, $caldav_data );
        break;
      case 'getcontenttype':
        $prop->NewElement($k, "text/calendar" );
        break;
      case 'current-user-principal':
        $prop->NewElement("current-user-principal", $request->current_user_principal_xml);
        break;
      case 'displayname':
        $prop->NewElement($k, $displayname );
        break;
      case 'resourcetype':
        $prop->NewElement($k); // Just an empty resourcetype for a non-collection.
        break;
      case 'getetag':
        $prop->NewElement($k, '"'.$item->dav_etag.'"' );
        break;
      case '"current-user-privilege-set"':
        $prop->NewElement($k, privileges($request->permissions) );
        break;
      case 'SOME-DENIED-PROPERTY':  /** indicating the style for future expansion */
        $denied[] = $v;
        break;
      default:
        dbg_error_log( 'REPORT', "Request for unsupported property '%s' of calendar item.", $v );
        $unsupported[] = $v;
    }
  }
  $status = new XMLElement("status", "HTTP/1.1 200 OK" );

  $propstat = new XMLElement( "propstat", array( $prop, $status) );
  $href = new XMLElement("href", $url );
  $elements = array($href,$propstat);

  if ( count($denied) > 0 ) {
    $status = new XMLElement("status", "HTTP/1.1 403 Forbidden" );
    $noprop = new XMLElement("prop");
    foreach( $denied AS $k => $v ) {
      $noprop->NewElement( strtolower($v) );
    }
    $elements[] = new XMLElement( "propstat", array( $noprop, $status) );
  }

  $response = new XMLElement( "response", $elements );

  return $response;
}

if ( $xmltree->GetTag() == "urn:ietf:params:xml:ns:caldav:calendar-query" ) {
  $calquery = $xmltree->GetPath("/urn:ietf:params:xml:ns:caldav:calendar-query/*");
  include("caldav-REPORT-calquery.php");
}
elseif ( $xmltree->GetTag() == "urn:ietf:params:xml:ns:caldav:calendar-multiget" ) {
  $multiget = $xmltree->GetPath("/urn:ietf:params:xml:ns:caldav:calendar-multiget/*");
  include("caldav-REPORT-multiget.php");
}
else {
  $request->DoResponse( 501, "The XML is not a supported REPORT query document" );
}

