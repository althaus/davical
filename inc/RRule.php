<?php
/**
* Class for parsing RRule and getting us the dates
*
* @package   rscds
* @subpackage   caldav
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

/**
* A Class for handling dates in iCalendar format.  We do make the simplifying assumption
* that all date handling in here is normalised to GMT.  One day we might provide some
* functions to do that, but for now it is done externally.
*
* @package awl
*/
class iCalDate {
  /**#@+
  * @access private
  */

  /** Text version */
  var $_text;

  /** Epoch version */
  var $_epoch;

  /** Fragmented parts */
  var $_yy;
  var $_mm;
  var $_dd;
  var $_hh;
  var $_mm;
  var $_ss;

  /**#@-*/

  /**
  * The constructor takes either a text string formatted as an iCalendar date, or
  * epoch seconds.
  */
  function iCalDate( $input ) {
    if ( preg_match( '/^\d{8}T\d{6}Z/', $input ) ) {
      $this->SetICalDate($input);
    }
    else if ( intval($input) == 0 ) {
      return;
    }
    else {
      $this->SetEpochDate($input);
    }
  }


  /**
  * Set the date from a text string
  */
  function SetICalDate( $input ) {
    $this->_text = $input;
    $this->_PartsFromText();
    $this->_EpochFromParts();
  }


  /**
  * Set the date from an epoch
  */
  function SetEpochDate( $input ) {
    $this->_epoch = intval($input);
    $this->_TextFromEpoch();
    $this->_PartsFromText();
  }


  /**
  * Given an epoch date, convert it to text
  */
  function _TextFromEpoch() {
    $this->_text = gmdate('Ymd\THis\Z', $this->_epoch );
  }


  /**
  * Given an epoch date, convert it to text
  */
  function _PartsFromText() {
    $this->_yy = substr($this->_text,0,4);
    $this->_mo = substr($this->_text,4,2);
    $this->_dd = substr($this->_text,6,2);
    $this->_hh = substr($this->_text,9,2);
    $this->_mi = substr($this->_text,11,2);
    $this->_ss = substr($this->_text,13,2);
  }


  /**
  * Given a text date, convert it to an epoch
  */
  function _EpochFromParts() {
    $this->_epoch = gmmktime ( $this->_hh, $this->_mi, $this->_ss, $this->_mo, $this->_dd, $this->_yy );
  }


}


/**
* A Class for handling Events on a calendar
*
* @package awl
*/
class RRule {
  /**#@+
  * @access private
  */

  /** The first instance */
  var $_first;

  /**#@-*/

  /**
  * The constructor takes a start & end date and an RRULE definition.  All of these
  * follow the iCalendar standard.
  */
  function RRule( $start, $end, $rrule ) {
  }

  function GetNext( $after = false ) {
  }

}
?>