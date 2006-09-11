<?php
/**
* A Class for handling vEvent data
*
* @package rscds
* @subpackage iCalendar
* @author Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst IT Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/


/**
* A Class for handling Events on a calendar
*
* @package rscds
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
  * @var props array
  */
  var $properties;

  /**#@-*/

  /**
  * The constructor takes an array of args.  If there is an element called 'vevent'
  * then that will be parsed into the vEvent object.  Otherwise the array elements
  * are converted into properties of the vEvent object directly.
  */
  function vEvent( $args ) {
    global $c;

    // Probably a good idea to always have values for these things...
    $this->properties['tzid']     = $c->local_tzid;
    $this->properties['modified'] = iCalendar::EpochTS(time());
    $this->properties['sequence'] = 1;
    $this->properties['uid']      = sprintf( "%s@%s", time() * 1000 + rand(0,1000), $c->domain_name);
    $this->properties['guid']     = sprintf( "%s@%s", time() * 1000 + rand(0,1000), $c->domain_name);
    $this->properties['duration'] = "PT1H";
    $this->properties['status']   = "TENTATIVE";

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
    foreach( $properties AS $k => $v ) {

      switch( $state ) {
        case 0:
          if ( $v == 'BEGIN:VEVENT' )           $state = $v;
          else if ( $v == 'BEGIN:VTIMEZONE' )   $state = $v;
          break;

        case 'BEGIN:VEVENT':
          if ( $v == 'END:VEVENT' ) $state = 0;
          break;

        case 'BEGIN:VTIMEZONE':
          if ( $v == 'END:VTIMEZONE' ) {
            $state = 0;
            $vtimezone .= $v;
          }
          break;
      }

      if ( $state == 'BEGIN:VEVENT' && $state != $v ) {
        list( $parameter, $value ) = preg_split('/:/', $v );
        if ( preg_match('/^DT[A-Z]+;TZID=/', $parameter) ) {
          list( $parameter, $tzid ) = preg_split('/;/', $parameter );
          $properties['TZID'] = $tzid;
        }
        $properties[$parameter] = $value;
      }
      if ( $state == 'BEGIN:VTIMEZONE' ) {
        $vtimezone .= $v . "\n";
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
    $qry = new PgQuery( "SELECT pgtz FROM time_zones WHERE tzid = ?;", $this->properties['TZID'] );
    if ( $qry->Exec('vEvent') && $qry->rows == 1 ) {
    }
    else {
      $qry2 = new PgQuery( "INSERT INTO time_zones (tzid, location, tz_spec) VALUES( ?, ?, ?);", $this->properties['TZID'], $location, $this->properties['VTIMEZONE'] );
      $qry2->Exec("vEvent");
    }
  }

}

?>