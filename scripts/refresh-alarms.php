#!/usr/bin/env php
<?php
/**
* Script to refresh the pending alarm times for the next alarm instance.
*
* @package   davical
* @subpackage   alarms
* @author    Andrew McMillan <andrew@morphoss.com>
* @copyright Morphoss Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/
$script_file = __FILE__;

chdir(str_replace('/scripts/refresh-alarms.php','/htdocs',$script_file));
$_SERVER['SERVER_NAME'] = 'localhost';

/**
* Call with something like e.g.:
*
* scripts/refresh_alarms.php -p P1800D -f P1D
*
*/

$args = (object) array();
$args->debug = false;
$args->set_last = false;

$args->future = 'P400D';
$args->near_past = 'P1D';
$debugging = null;


function parse_arguments() {
  global $args;

  $opts = getopt( 'f:p:s:d:lh' );
  foreach( $opts AS $k => $v ) {
    switch( $k ) {
      case 'f':   $args->future = $v;  break;
      case 'p':   $args->near_past = $v;  break;
      case 's':   $_SERVER['SERVER_NAME'] = $v; break;
      case 'd':   $args->debug = true;  $debugging = explode(',',$v); break;
      case 'l':   $args->set_last = true; break;
      case 'h':   usage();  break;
      default:    $args->{$k} = $v;
    }
  }
}

function usage() {

  echo <<<USAGE
Usage:
   refresh-alarms.php [-s server.domain.tld] [other options]

  -s <server>      The servername to be used to identify the DAViCal configuration file.
  -p <duration>    Near past period to review for finding recently last instances: default 1 days ('P1D')
  -f <duration>    Future period to consider for finding future alarms: default ~5 years ('P2000D')

  -l               Try to set the 'last' alarm date in historical alarms

  -d xxx           Enable debugging where 'xxx' is a comma-separated list of debug subsystems

USAGE;
  exit(0);
}

parse_arguments();

if ( $args->debug && is_array($debugging )) {
  foreach( $debugging AS $v ) {
    $c->dbg[$v] = 1;
  }
}
$args->near_past = '-' .  $args->near_past;

require_once("./always.php");
require_once('AwlQuery.php');
require_once('RRule-v2.php');
require_once('vCalendar.php');


/**
* Essentially what we are doing is:
*
UPDATE calendar_alarm
  SET next_trigger = (SELECT rrule_event_instances_range(
                        dtstart + icalendar_interval_to_SQL(replace(trigger,'TRIGGER:','')),
                        rrule,
                        current_timestamp, current_timestamp + '2 days'::interval,
                        1)
                     LIMIT 1)
 FROM calendar_item
WHERE calendar_alarm.dav_id = calendar_item.dav_id
  AND next_trigger is null
  AND rrule IS NOT NULL

*/
$expand_range_start = new RepeatRuleDateTime(gmdate('Ymd\THis\Z'));
$expand_range_end   = new RepeatRuleDateTime(gmdate('Ymd\THis\Z'));
$expand_range_end->modify( $args->future );



$earliest   = clone($expand_range_start);
$earliest->modify( $args->near_past );

if ( $args->debug ) printf( "Looking for event instances between '%s' and '%s'\n", $earliest->UTC(), $expand_range_end->UTC() );

$sql = 'SELECT * FROM calendar_alarm JOIN calendar_item USING (dav_id) JOIN caldav_data USING (dav_id) WHERE rrule IS NOT NULL AND next_trigger IS NULL';
if ( $args->debug ) printf( "%s\n", $sql );
$qry = new AwlQuery( $sql );
if ( $qry->Exec() && $qry->rows() ) {
  while( $alarm = $qry->Fetch() ) {
    if ( $args->debug ) printf( "refresh: Processing alarm for '%s' based on '%s','%s', '%s'\n",
                          $alarm->dav_name, $alarm->dtstart, $alarm->rrule, $alarm->trigger );
    $ic = new vComponent( $alarm->caldav_data );
    $expanded = expand_event_instances( $ic, $earliest, $expand_range_end );
    $expanded->MaskComponents( array( 'VEVENT'=>1, 'VTODO'=>1, 'VJOURNAL'=>1 ) );
    $instances = $expanded->GetComponents();

    $trigger = new vProperty( $alarm->trigger );
    $related = $trigger->GetParameterValue('RELATED');

    $first = new RepeatRuleDateTime($alarm->dtstart);
    $first->modify( $trigger->Value() );
    $next = null;
    $last = null;
    foreach( $instances AS $k => $component ) {
      $when = new RepeatRuleDateTime( $component->GetPValue('DTSTART') ); // a UTC value
      if ( $args->debug ) printf( "refresh: Looking at event instance on '%s'\n", $when->UTC() );
      if ( $related == 'END' ) {
        $when->modify( $component->GetPValue('DURATION') );
      }
      $when->modify( $trigger->Value() );
      if ( $when > $expand_range_start && $when < $expand_range_end && (!isset($next) || $when < $next) ) {
        $next = clone($when);
      }
      if ( $args->set_last && (!isset($last) || $when > $last) ) {
        $last = clone($when);
      }
    }
    $trigger_type = $trigger->GetParameterValue('VALUE');
    if ( $trigger_type == 'DATE' || $trigger_type == 'DATE-TIME' || preg_match('{^\d{8}T\d{6}Z?$}', $trigger->Value()) ) {
      $first = new RepeatRuleDateTime($trigger);
      if ( $first > $expand_range_start && (empty($next) || $first < $next ) )
        $next = $first;
      else if ( empty($next) ) {
        if ( $args->set_last && (empty($last) || $first > $last) )
          $last = $first;
      }
    }
    if ( $args->set_last && !isset($last) && (!isset($next) || $next < $expand_range_Start) ) {
      $vc = new vCalendar( $alarm->caldav_data );
      $range = getVCalendarRange($vc);
      if ( isset($range->until) && $range->until < $earliest ) $last = $range->until;
    }
    
    if ( isset($next) && $next < $expand_range_end ) {
      if ( $args->debug ) printf( "refresh: Found next alarm instance on '%s'\n", $next->UTC() );
      $sql = 'UPDATE calendar_alarm SET next_trigger = :next WHERE dav_id = :id AND component = :component';
      $update = new AwlQuery( $sql, array( ':next' => $next->UTC(), ':id' => $alarm->dav_id, ':component' => $alarm->component ) );
      $update->Exec('refresh-alarms', __LINE__, __FILE__ );
    }
    else if ( $args->set_last && isset($last) && $last < $earliest ) {
      if ( $args->debug ) printf( "refresh: Found past final alarm instance on '%s'\n", $last->UTC() );
      $sql = 'UPDATE calendar_alarm SET next_trigger = :last WHERE dav_id = :id AND component = :component';
      $update = new AwlQuery( $sql, array( ':last' => $last->UTC(), ':id' => $alarm->dav_id, ':component' => $alarm->component ) );
      $update->Exec('refresh-alarms', __LINE__, __FILE__ );
    }
    else if ( $args->debug && isset($next) && $next < $expand_range_end ) {
      printf( "refresh: Found next alarm instance on '%s' after '%s'\n", $next->UTC(), $expand_range_end->UTC() );
    }
  }
}

