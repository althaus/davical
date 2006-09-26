<?php
/**
* A Class for handling vEvent data
*
* @package awl
* @subpackage iCalendar
* @author Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst IT Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/


/**
* A Class for handling Events on a calendar
*
* @package awl
*/
class vEvent {
  /**#@+
  * @access private
  */

  /**
  * List of participants in this event
  * @var participants array
  */
  var $participants = array();

  /**
  * An array of arbitrary properties
  * @var properties array
  */
  var $properties;

  /**
  * The typical location name for the standard timezone such as "Pacific/Auckland"
  * @var tz_locn string
  */
  var $tz_locn;

  /**
  * The type of iCalendar data VEVENT/VTODO
  * @var type string
  */
  var $type;

  /**#@-*/

  /**
  * The constructor takes an array of args.  If there is an element called 'vevent'
  * then that will be parsed into the vEvent object.  Otherwise the array elements
  * are converted into properties of the vEvent object directly.
  */
  function vEvent( $args ) {
    global $c;

    // Probably a good idea to always have values for these things...
    if ( isset($c->local_tzid ) ) $this->properties['tz_id']    = $c->local_tzid;
    $this->properties['dtstamp']  = date('Ymd\THis');
    $this->properties['sequence'] = 1;
    $this->properties['uid']      = sprintf( "%s@%s", time() * 1000 + rand(0,1000), $c->domain_name);

    if ( !isset($args) || !is_array($args) ) return;

    if ( isset($args['vevent']) ) {
      $this->BuildFromText($args['vevent']);
      $this->DealWithTimeZones();
      return;
    }

    foreach( $args AS $k => $v ) {
      $this->properties[strtoupper($k)] = $v;
    }
  }

  /**
  * Build the vEvent object from a text string which is a single VEVENT
  *
  * @var vevent string
  */
  function BuildFromText( $vevent ) {
    $vevent = preg_replace('/[\r\n]+ /', ' ', $vevent );
    $lines = preg_split('/[\r\n]+/', $vevent );
    $properties = array();

    $vtimezone = "";
    $state = 0;
    foreach( $lines AS $k => $v ) {
      dbg_error_log( "vEvent", "LINE %03d: >>>%s<<<", $k, $v );

      switch( $state ) {
        case 0:
          if ( $v == 'BEGIN:VEVENT' ) {
            $state = $v;
            $this->type = 'VEVENT';
          }
          else if ( $v == 'BEGIN:VTODO' ) {
            $state = $v;
            $this->type = 'VTODO';
          }
          else if ( $v == 'BEGIN:VTIMEZONE' )   $state = $v;
          break;

        case 'BEGIN:VEVENT':
          if ( $v == 'END:VEVENT' ) $state = 0;
          break;

        case 'BEGIN:VTODO':
          if ( $v == 'END:VTODO' ) $state = 0;
          break;

        case 'BEGIN:VTIMEZONE':
          if ( $v == 'END:VTIMEZONE' ) {
            $state = 0;
            $vtimezone .= $v;
          }
          break;
      }

      if ( ($state == 'BEGIN:VEVENT' || $state == 'BEGIN:VTODO') && $state != $v ) {
        list( $parameter, $value ) = preg_split('/:/', $v );
        if ( preg_match('/^DT[A-Z]+;TZID=/', $parameter) ) {
          list( $parameter, $tz_id ) = preg_split('/;/', $parameter );
          $properties['TZID'] = $tz_id;
        }
        $properties[strtoupper($parameter)] = $value;
      }
      if ( $state == 'BEGIN:VTIMEZONE' ) {
        $vtimezone .= $v . "\n";
        list( $parameter, $value ) = preg_split('/:/', $v );
        if ( !isset($this->tz_locn) && $parameter == 'X-LIC-LOCATION' ) {
          $this->tz_locn = $value;
        }
      }
    }

    if ( $vtimezone != "" ) {
      $properties['VTIMEZONE'] = $vtimezone;
    }

    $this->properties = &$properties;
  }


  /**
  * Do what must be done with time zones from on file.  Attempt to turn
  * them into something that PostgreSQL can understand...
  */
  function DealWithTimeZones() {
    if ( isset($c->save_time_zone_defs) ) {
      $qry = new PgQuery( "SELECT tz_locn FROM time_zone WHERE tz_id = ?;", $this->properties['TZID'] );
      if ( $qry->Exec('vEvent') && $qry->rows == 1 ) {
        $row = $qry->Fetch();
        $this->tz_locn = $row->tz_locn;
      }
    }

    if ( !isset($this->tz_locn) ) {
      // In case there was no X-LIC-LOCATION defined, let's hope there is something in the TZID
      $this->tz_locn = preg_replace('/^.*([a-z]+\/[a-z]+)$/i','$1',$this->properties['TZID'] );
    }

    if ( isset($c->save_time_zone_defs) && $qry->rows != 1 ) {
      $qry2 = new PgQuery( "INSERT INTO time_zone (tz_id, tz_locn, tz_spec) VALUES( ?, ?, ? );",
                                   $this->properties['TZID'], $this->tz_locn, $this->properties['VTIMEZONE'] );
      $qry2->Exec("vEvent");
    }
  }


  /**
  * Get the value of a property
  */
  function Get( $key ) {
    return $this->properties[strtoupper($key)];
  }


  /**
  * Put the value of a property
  */
  function Put( $key, $value ) {
    return $this->properties[strtoupper($key)] = $value;
  }


  /**
  * Returns a PostgreSQL Date Format string suitable for returning iCal dates
  */
  function SqlDateFormat() {
    return "'IYYYMMDD\"T\"HH24MISS'";
  }


  /**
  * Returns a PostgreSQL Date Format string suitable for returning iCal durations
  *  - this doesn't work for negative intervals, but events should not have such!
  */
  function SqlDurationFormat() {
    return "'\"PT\"HH24\"H\"MI\"M\"'";
  }


/*
BEGIN:VCALENDAR
CALSCALE:GREGORIAN
PRODID:-//Ximian//NONSGML Evolution Calendar//EN
VERSION:2.0
BEGIN:VEVENT
UID:20060918T005755Z-21151-1000-1-7@ubu
DTSTAMP:20060918T005755Z
DTSTART;TZID=/softwarestudio.org/Olson_20011030_5/Pacific/Auckland:
 20060918T153000
DTEND;TZID=/softwarestudio.org/Olson_20011030_5/Pacific/Auckland:
 20060918T160000
SUMMARY:Lunch
X-EVOLUTION-CALDAV-HREF:http:
 //andrew@mycaldav/caldav.php/andrew/20060918T005757Z.ics
BEGIN:VALARM
X-EVOLUTION-ALARM-UID:20060918T005755Z-21149-1000-1-12@ubu
ACTION:DISPLAY
TRIGGER;VALUE=DURATION;RELATED=START:-PT15M
DESCRIPTION:Lunch
END:VALARM
END:VEVENT
BEGIN:VTIMEZONE
TZID:/softwarestudio.org/Olson_20011030_5/Pacific/Auckland
X-LIC-LOCATION:Pacific/Auckland
BEGIN:STANDARD
TZOFFSETFROM:+1300
TZOFFSETTO:+1200
TZNAME:NZST
DTSTART:19700315T030000
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=3SU;BYMONTH=3
END:STANDARD
BEGIN:DAYLIGHT
TZOFFSETFROM:+1200
TZOFFSETTO:+1300
TZNAME:NZDT
DTSTART:19701004T020000
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=1SU;BYMONTH=10
END:DAYLIGHT
END:VTIMEZONE
END:VCALENDAR
*/
  /**
  * Render the vEvent object as a text string which is a single VEVENT
  */
  function Render( ) {
    $interesting = array( "uid", "dtstamp", "dtstart", "dtend", "duration", "summary",
                          "location", "description", "action", "class", "transp", "sequence");

    $result = <<<EOTXT
BEGIN:VCALENDAR
CALSCALE:GREGORIAN
PRODID:-//Catalyst.Net.NZ//NONSGML AWL Calendar//EN
VERSION:2.0
BEGIN:VEVENT

EOTXT;

    foreach( $interesting AS $k => $v ) {
      $v = strtoupper($v);
      if ( isset($this->properties[$v]) )
        $result .= sprintf("%s:%s\n", $v, $this->properties[$v]);
    }
    $result .= <<<EOTXT
END:VEVENT
END:VCALENDAR

EOTXT;

/*
    $result = sprintf( $format,
                         $this->properties['UID'],
                         $this->properties['DTSTART'],
                         $this->properties['DURATION'],
                         $this->properties['SUMMARY'],
                         $this->properties['LOCATION']
                     );
*/
    return $result;
  }
  

}

?>
