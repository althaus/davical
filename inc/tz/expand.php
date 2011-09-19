<?php
/**
* DAViCal Timezone Service handler - capabilitis
*
* @package   davical
* @subpackage   tzservice
* @author    Andrew McMillan <andrew@morphoss.com>
* @copyright Morphoss Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

require_once('vCalendar.php');
require_once('RRule-v2.php');

if ( empty($format) ) $format = 'text/calendar';
if ( $format != 'text/calendar' ) {
  $request->PreconditionFailed(403, 'supported-format', 'This server currently only supports text/calendar format.');
}

if ( empty($start) ) $start = sprintf( '%04d-01-01', date('Y'));
if ( empty($end) )   $end   = sprintf( '%04d-12-31', date('Y') + 10);

$sql = 'SELECT our_tzno, tzid, active, olson_name, vtimezone, etag, ';
$sql .= 'to_char(last_modified AT TIME ZONE \'UTC\',\'Dy, DD Mon IYYY HH24:MI:SS "GMT"\') AS last_modified ';
$sql .= 'FROM timezones WHERE tzid=:tzid';
$params = array( ':tzid' => $tzid );
$qry = new AwlQuery($sql,$params);
if ( !$qry->Exec() ) exit(1);
if ( $qry->rows() < 1 ) {
  $sql = 'SELECT our_tzno, tzid, active, olson_name, vtimezone, etag, ';
  $sql .= 'to_char(last_modified AT TIME ZONE \'UTC\',\'Dy, DD Mon IYYY HH24:MI:SS "GMT"\') AS last_modified ';
  $sql .= 'FROM timezones JOIN tz_aliases USING(our_tzno) WHERE tzalias=:tzid';
  if ( !$qry->Exec() ) exit(1);
  if ( $qry->rows() < 1 ) $request->DoResponse(404);
}

$tz = $qry->Fetch();

// define( 'DEBUG_EXPAND', true);
define( 'DEBUG_EXPAND', false );
 

/**
* Expand the instances for a STANDARD or DAYLIGHT component of a VTIMEZONE 
*
* @param object $vResource is a VCALENDAR with a VTIMEZONE containing components needing expansion
* @param object $range_start A RepeatRuleDateTime which is the beginning of the range for events.
* @param object $range_end A RepeatRuleDateTime which is the end of the range for events.
*
* @return array of onset datetimes with UTC from/to offsets
*/
function expand_timezone_onsets( vCalendar $vResource, RepeatRuleDateTime $range_start, RepeatRuleDateTime $range_end ) {
  global $c;
  $vtimezones = $vResource->GetComponents();
  $vtz = $vtimezones[0];
  $components = $vtz->GetComponents();

  $instances = array();
  $dtstart = null;
  $is_date = false;
  $has_repeats = false;
  $zone_tz = $vtz->GetPValue('TZID');
  $range_start->setTimeZone($zone_tz);
  $range_end->setTimeZone($zone_tz);
  
  foreach( $components AS $k => $comp ) {
    if ( DEBUG_EXPAND ) {
      printf( "Starting TZ expansion for component '%s' in timezone '%s'\n", $comp->GetType(), $zone_tz);
      foreach( $instances AS $k => $v ) {
        print ' : '.$k;
      }
      print "\n";
    }
    $dtstart_prop = $comp->GetProperty('DTSTART');
    if ( !isset($dtstart_prop) ) continue;
    $dtstart_prop->SetParameterValue('TZID',$zone_tz);
    $dtstart = new RepeatRuleDateTime( $dtstart_prop );
    $is_date = $dtstart->isDate();
    $instances[$dtstart->FloatOrUTC()] = $comp;
    $rrule = $comp->GetProperty('RRULE');
    $has_repeats = isset($rrule);
    if ( !$has_repeats ) continue;

    $instances += rrule_expand($dtstart, 'RRULE', $comp, $range_end);
    if ( DEBUG_EXPAND ) {
      print( "After rrule_expand");
      foreach( $instances AS $k => $v ) {
        print ' : '.$k;
      }
      print "\n";
    }
    $instances += rdate_expand($dtstart, 'RDATE', $comp, $range_end);
    if ( DEBUG_EXPAND ) {
      print( "After rdate_expand");
      foreach( $instances AS $k => $v ) {
        print ' : '.$k;
      }
      print "\n";
    }
  }

  ksort($instances);

  $onsets = array();
  $start_utc = $range_start->FloatOrUTC();
  $end_utc = $range_end->FloatOrUTC();
  foreach( $instances AS $utc => $comp ) {
    if ( $utc > $end_utc ) {
      if ( DEBUG_EXPAND ) printf( "We're done: $utc is out of the range.\n");
      break;
    }

    if ( $utc < $start_utc ) {
      continue;
    }
    $onsets[$utc] = array( 
      'from' => $comp->GetPValue('TZOFFSETFROM'),
      'to' => $comp->GetPValue('TZOFFSETTO'),
      'name' => $comp->GetPValue('TZNAME'),
      'type' => $comp->GetType()
    );
  }

  return $onsets;
}

header( 'Etag: "'.$tz->etag.'"' );
header( 'Last-Modified', $tz->last_modified );
header('Content-Type: application/xml; charset="utf-8"');

$vtz = new vCalendar($tz->vtimezone);

$response = new XMLDocument(array("urn:ietf:params:xml:ns:timezone-service" => ""));
$timezones = $response->NewXMLElement('urn:ietf:params:xml:ns:timezone-service:timezones');
$timezones->NewElement('dtstamp', gmdate('Ymd\THis\Z'));

$from = new RepeatRuleDateTime($start);
$until = new RepeatRuleDateTime($end);

$observances = expand_timezone_onsets($vtz, $from, $until);
$tzdata = array();
$tzdata[] = new XMLElement( 'tzid', $tzid );
$tzdata[] = new XMLElement( 'calscale', 'Gregorian' );

foreach( $observances AS $onset => $details ) {
  $tzdata[] = new XMLElement( 'observance', array(
    new XMLElement('name', (empty($details['name']) ? $details['type'] : $details['name'] ) ),
    new XMLElement('onset', $onset ),
    new XMLElement('utc-offset-from', substr($details['from'],0,-2).':'.substr($details['from'],-2) ),
    new XMLElement('utc-offset-to', substr($details['to'],0,-2).':'.substr($details['to'],-2) )
  )); 
}

$timezones->NewElement('tzdata', $tzdata );
echo $response->Render($timezones);

exit(0);