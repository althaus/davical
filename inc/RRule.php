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


  /**
  * No of days in a month 0(Jan) - 11(Dec)
  */
  function DaysInMonth( $mo=false, $yy=false ) {
    if ( $mo === false ) $mo = $this->_mo;
    switch( $mo ) {
      0: // January
      2: // March
      4: // May
      6: // July
      7: // August
      9: // October
     11: // December
        return 31;
        break;

      3: // April
      5: // June
      8: // September
     10: // November
        return 30;
        break;

      1: // February
        if ( $yy === false ) $yy = $this->_yy;
        if ( (($yy % 4) == 0) && ((($yy % 100) != 0) || (($yy % 400) == 0) ) ) return 29;
        return 28;
        break;

      default:
        dbg_error_log( "ERROR"," Invalid month of '%s' passed to DaysInMonth" );
        break;

    }
  }


  /**
  * Add some number of months to a date
  */
  function AddMonths( $mm ) {
    $this->_mo += $mm;
    while ( $this->_mo < 0 ) {
      $this->_mo += 12;
      $this->_yy--;
    }
    while ( $this->_mo > 11 ) {
      $this->_mo -= 12;
      $this->_yy++;
    }

    if ( ($this->_dd > 28 && $this->_mo == 1) || $this->_dd > 30 ) {
      // Ensure the day of month is still reasonable
      $dim = $this->DaysInMonth();
      if ( $this->_dd > $dim ) {
        // We don't need to check for month > 12, since _dd can't be greater than 31 if it was previously valid
        $this->_mo++;
        $this->_dd -= $dim;
      }
    }
    $this->_EpochFromParts();
    $this->_TextFromEpoch();
  }


  /**
  * Add some integer number of days to a date
  */
  function AddDays( $dd ) {
    $this->_dd += $dd;
    while ( 1 > $this->_dd ) {
      $this->_mo--;
      $this->_dd += $this->DaysInMonth();
    }
    while ( ($dim = $this->DaysInMonth()) < $this->_dd ) {
      $this->_mo++;
      $this->_dd -= $dim;
    }
    $this->_EpochFromParts();
    $this->_TextFromEpoch();
  }


  /**
  * Add duration
  */
  function AddDuration( $duration ) {
    list( $sign, $days, $time ) = preg_split( '/[PT]/', $duration );
    $sign = ( $sign == "-" ? -1 : 1);
    if ( preg_match( '/(\d)+(D|W)/', $days, $matches ) ) {
      $days = intval($matches[1]);
      if ( $matches[2] == 'W' ) $days *= 7;
      $this->AddDays( $days * $sign );
    }
    if ( preg_match( '/(\d)+(H)/', $time, $matches ) )  $hh = $matches[1];
    if ( preg_match( '/(\d)+(M)/', $time, $matches ) )  $mi = $matches[1];
    if ( preg_match( '/(\d)+(S)/', $time, $matches ) )  $ss = $matches[1];

    $this->_hh += ($hh * $sign);
    $this->_mi += ($mi * $sign);
    $this->_ss += ($ss * $sign);

    if ( $this->_ss < 0 ) {  $this->_mi -= (intval(abs($this->ss/60))+1); $this->_ss += ((intval(abs($this->mi/60))+1) * 60); }
    if ( $this->_ss > 59) {  $this->_mi += (intval(abs($this->ss/60))+1); $this->_ss -= ((intval(abs($this->mi/60))+1) * 60); }
    if ( $this->_mi < 0 ) {  $this->_hh -= (intval(abs($this->mi/60))+1); $this->_mi += ((intval(abs($this->mi/60))+1) * 60); }
    if ( $this->_mi > 59) {  $this->_hh += (intval(abs($this->mi/60))+1); $this->_mi -= ((intval(abs($this->mi/60))+1) * 60); }
    if ( $this->_hh < 0 ) {  $this->AddDays( -1 * (intval(abs($this->_hh/24))+1) );  $this->_hh += ((intval(abs($this->_hh/24))+1)*24);  }
    if ( $this->_hh > 23) {  $this->AddDays( (intval(abs($this->_hh/24))+1) );       $this->_hh -= ((intval(abs($this->_hh/24))+1)*24);  }

    $this->_EpochFromParts();
    $this->_TextFromEpoch();
  }


  /**
  * Produce an iCalendar format DURATION for the difference between this an another iCalDate
  *
  * @param date $from The start of the period
  * @return string The date difference, as an iCalendar duration format
  */
  function DateDifference( $from ) {
    if ( !is_object($from) ) {
      $from = new iCalDate($from);
    }
    if ( $from->_epoch < $this->_epoch ) {
      /** One way to simplify is to always go for positive differences */
      return( "-". $from->DateDifference( &$self ) );
    }
//    if ( $from->_yy == $this->_yy && $from->_mo == $this->_mo ) {
      /** Also somewhat simpler if we can use seconds */
      $diff = $from->_epoch - $this->_epoch;
      $result = "";
      if ( $diff > 86400) {
        $result = intval($diff / 86400);
        $diff = $diff % 86400;
        if ( $diff == 0 && (($result % 7) == 0) ) {
          $result .= intval($result / 7) . "W";
          return $result;
        }
        $result .= "D";
      }
      $result = "P".$result."T";
      if ( $diff > 3600) {
        $result .= intval($diff / 3600) . "H";
        $diff = $diff % 3600;
      }
      if ( $diff > 60) {
        $result .= intval($diff / 60) . "M";
        $diff = $diff % 60;
      }
      if ( $diff > 0) {
        $result .= intval($diff . "S";
      }
      return $result;
//    }

/**
* From an intense reading of RFC2445 it appears that durations which are not expressible
* in Weeks/Days/Hours/Minutes/Seconds are invalid.
*  ==> This code is not needed then :-)
    $yy = $from->_yy - $this->_yy;
    $mo = $from->_mo - $this->_mo;
    $dd = $from->_dd - $this->_dd;
    $hh = $from->_hh - $this->_hh;
    $mi = $from->_mi - $this->_mi;
    $ss = $from->_ss - $this->_ss;

    if ( $ss < 0 ) {  $mi -= 1;   $ss += 60;  }
    if ( $mi < 0 ) {  $hh -= 1;   $mi += 60;  }
    if ( $hh < 0 ) {  $dd -= 1;   $hh += 24;  }
    if ( $dd < 0 ) {  $mo -= 1;   $dd += $this->DaysInMonth();  } // Which will use $this->_(mo|yy) - seemingly sensible
    if ( $mo < 0 ) {  $yy -= 1;   $mo += 12;  }

    $result = "";
    if ( $yy > 0) {    $result .= $yy."Y";   }
    if ( $mo > 0) {    $result .= $mo."M";   }
    if ( $dd > 0) {    $result .= $dd."D";   }
    $result .= "T";
    if ( $hh > 0) {    $result .= $hh."H";   }
    if ( $mi > 0) {    $result .= $mi."M";   }
    if ( $ss > 0) {    $result .= $ss."S";   }
    return $result;
*/
  }

}


/**
* A Class for handling Events on a calendar which repeat
*
* Here's the spec, from RFC2445:
*
     recur      = "FREQ"=freq *(

                ; either UNTIL or COUNT may appear in a 'recur',
                ; but UNTIL and COUNT MUST NOT occur in the same 'recur'

                ( ";" "UNTIL" "=" enddate ) /
                ( ";" "COUNT" "=" 1*DIGIT ) /

                ; the rest of these keywords are optional,
                ; but MUST NOT occur more than once

                ( ";" "INTERVAL" "=" 1*DIGIT )          /
                ( ";" "BYSECOND" "=" byseclist )        /
                ( ";" "BYMINUTE" "=" byminlist )        /
                ( ";" "BYHOUR" "=" byhrlist )           /
                ( ";" "BYDAY" "=" bywdaylist )          /
                ( ";" "BYMONTHDAY" "=" bymodaylist )    /
                ( ";" "BYYEARDAY" "=" byyrdaylist )     /
                ( ";" "BYWEEKNO" "=" bywknolist )       /
                ( ";" "BYMONTH" "=" bymolist )          /
                ( ";" "BYSETPOS" "=" bysplist )         /
                ( ";" "WKST" "=" weekday )              /
                ( ";" x-name "=" text )
                )

     freq       = "SECONDLY" / "MINUTELY" / "HOURLY" / "DAILY"
                / "WEEKLY" / "MONTHLY" / "YEARLY"

     enddate    = date
     enddate    =/ date-time            ;An UTC value

     byseclist  = seconds / ( seconds *("," seconds) )

     seconds    = 1DIGIT / 2DIGIT       ;0 to 59

     byminlist  = minutes / ( minutes *("," minutes) )

     minutes    = 1DIGIT / 2DIGIT       ;0 to 59

     byhrlist   = hour / ( hour *("," hour) )

     hour       = 1DIGIT / 2DIGIT       ;0 to 23

     bywdaylist = weekdaynum / ( weekdaynum *("," weekdaynum) )

     weekdaynum = [([plus] ordwk / minus ordwk)] weekday

     plus       = "+"

     minus      = "-"

     ordwk      = 1DIGIT / 2DIGIT       ;1 to 53

     weekday    = "SU" / "MO" / "TU" / "WE" / "TH" / "FR" / "SA"
     ;Corresponding to SUNDAY, MONDAY, TUESDAY, WEDNESDAY, THURSDAY,
     ;FRIDAY, SATURDAY and SUNDAY days of the week.

     bymodaylist = monthdaynum / ( monthdaynum *("," monthdaynum) )

     monthdaynum = ([plus] ordmoday) / (minus ordmoday)

     ordmoday   = 1DIGIT / 2DIGIT       ;1 to 31

     byyrdaylist = yeardaynum / ( yeardaynum *("," yeardaynum) )

     yeardaynum = ([plus] ordyrday) / (minus ordyrday)

     ordyrday   = 1DIGIT / 2DIGIT / 3DIGIT      ;1 to 366

     bywknolist = weeknum / ( weeknum *("," weeknum) )

     weeknum    = ([plus] ordwk) / (minus ordwk)

     bymolist   = monthnum / ( monthnum *("," monthnum) )

     monthnum   = 1DIGIT / 2DIGIT       ;1 to 12

     bysplist   = setposday / ( setposday *("," setposday) )

     setposday  = yeardaynum
*
* At this point we are going to restrict ourselves to parts of the RRULE specification
* seen in the wild.  And by "in the wild" I don't include within people's timezone
* definitions.  We always convert time zones to canonical names and assume the lower
* level libraries can do a better job with them than we can.
*
* We will concentrate on:
*  FREQ=(YEARLY|MONTHLY|WEEKLY|DAILY)
*  UNTIL=
*  COUNT=
*  INTERVAL=
*  BYDAY=
*  BYMONTHDAY=
*  BYSETPOS=
*  WKST=
*  BYYEARDAY=
*  BYWEEKNO=
*  BYMONTH=
*
*
* @package awl
*/
class RRule {
  /**#@+
  * @access private
  */

  /** The first instance */
  var $_first;

  /** The rule, in all it's glory */
  var $_rule;

  /** The rule, in all it's parts */
  var $_part;

  /**#@-*/

  /**
  * The constructor takes a start & end date and an RRULE definition.  All of these
  * follow the iCalendar standard.
  */
  function RRule( $start, $rrule ) {
    $this->_first = new iCalDate($start);

    $this->_rule = $preg_replace( '/\s/m', '', $rrule);
    if ( substr($this->_rule, 0, 6) = 'RRULE:' ) {
      $this->_rule = substr($this->_rule, 6);
    }
    $parts = split(';',$this->_rule);
    $this->_part = array();
    foreach( $parts AS $k => $v ) {
      list( $type, $value ) = split( '=', $value, 2);
      $this->_part[$type] = $value;
    }

    // A little bit of validation
    if ( !isset($this->_part['freq']) ) {
      dbg_error_log( "ERROR", " RRULE MUST have FREQ=value (%s)", $rrule );
    }
    if ( isset($this->_part['count']) && isset($this->_part['until'])  ) {
      dbg_error_log( "ERROR", " RRULE MUST NOT have both COUNT=value and UNTIL=value (%s)", $rrule );
    }
    if ( isset($this->_part['count']) && intval($this->_part['count']) < 1 ) {
      dbg_error_log( "ERROR", " RRULE MUST NOT have both COUNT=value and UNTIL=value (%s)", $rrule );
    }
    if ( !preg_match( '/(YEAR|MONTH|WEEK|DAI)LY/', $this->_part['freq']) ) {
      dbg_error_log( "ERROR", " RRULE Only FREQ=(YEARLY|MONTHLY|WEEKLY|DAILY) are supported at present (%s)", $rrule );
    }
  }


  function GetNext( $after = false ) {
  }

}
?>