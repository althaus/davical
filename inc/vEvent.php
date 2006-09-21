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
  * @var properties array
  */
  var $properties;

  /**
  * The typical location name for the standard timezone such as "Pacific/Auckland"
  * @var tz_locn string
  */
  var $tz_locn;

  /**#@-*/

  /**
  * The constructor takes an array of args.  If there is an element called 'vevent'
  * then that will be parsed into the vEvent object.  Otherwise the array elements
  * are converted into properties of the vEvent object directly.
  */
  function vEvent( $args ) {
    global $c;

    // Probably a good idea to always have values for these things...
    $this->properties['tz_id']    = $c->local_tzid;
    $this->properties['modified'] = time();
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
    foreach( $lines AS $k => $v ) {
      dbg_error_log( "vEvent", "LINE %03d: >>>%s<<<", $k, $v );

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
    $qry = new PgQuery( "SELECT tz_locn FROM time_zone WHERE tz_id = ?;", $this->properties['TZID'] );
    if ( $qry->Exec('vEvent') && $qry->rows == 1 ) {
      $row = $qry->Fetch();
      $this->tz_locn = $row->tz_locn;
    }
    else {
      if ( !isset($this->tz_locn) ) {
        // In case there was no X-LIC-LOCATION defined, let's hope there is something in the TZID
        $this->tz_locn = preg_replace('/^.*([a-z]+\/[a-z]+)$/i','$1',$this->properties['TZID'] );
      }
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

}

?>