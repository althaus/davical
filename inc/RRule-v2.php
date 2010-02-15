<?php
/**
* Class for parsing RRule and getting us the dates
*
* @package   awl
* @subpackage   caldav
* @author    Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Morphoss Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
*/

$rrule_expand_limit = array(
  'YEARLY'  => array( 'bymonth' => 'expand', 'byweekno' => 'expand', 'byyearday' => 'expand', 'bymonthday' => 'expand',
                      'byday' => 'expand', 'byhour' => 'expand', 'byminute' => 'expand', 'bysecond' => 'expand' ),
  'MONTHLY' => array( 'bymonth' => 'limit', 'bymonthday' => 'expand',
                      'byday' => 'expand', 'byhour' => 'expand', 'byminute' => 'expand', 'bysecond' => 'expand' ),
  'WEEKLY'  => array( 'bymonth' => 'limit',
                      'byday' => 'expand', 'byhour' => 'expand', 'byminute' => 'expand', 'bysecond' => 'expand' ),
  'DAILY'   => array( 'bymonth' => 'limit', 'bymonthday' => 'limit',
                      'byday' => 'limit', 'byhour' => 'expand', 'byminute' => 'expand', 'bysecond' => 'expand' ),
  'HOURLY'  => array( 'bymonth' => 'limit', 'bymonthday' => 'limit',
                      'byday' => 'limit', 'byhour' => 'limit', 'byminute' => 'expand', 'bysecond' => 'expand' ),
  'MINUTELY'=> array( 'bymonth' => 'limit', 'bymonthday' => 'limit',
                      'byday' => 'limit', 'byhour' => 'limit', 'byminute' => 'limit', 'bysecond' => 'expand' ),
  'SECONDLY'=> array( 'bymonth' => 'limit', 'bymonthday' => 'limit',
                      'byday' => 'limit', 'byhour' => 'limit', 'byminute' => 'limit', 'bysecond' => 'limit' ),
);

$GLOBALS['debug_rrule'] = false;
// $GLOBALS['debug_rrule'] = true;

class RepeatRule {

  private $base;
  private $until;
  private $freq;
  private $count;
  private $interval;
  private $bysecond;
  private $byminute;
  private $byhour;
  private $bymonthday;
  private $byyearday;
  private $byweekno;
  private $byday;
  private $bymonth;
  private $bysetpos;
  private $wkst;

  private $instances;
  private $position;
  private $finished;
  private $current_base;


  public function __construct( $basedate, $rrule ) {
    $this->base = ( is_object($basedate) ? $basedate : new DateTime($basedate) );

    if ( preg_match('{FREQ=([A-Z]+)(;|$)}', $rrule, $m) ) $this->freq = $m[1];
    if ( preg_match('{UNTIL=([0-9TZ]+)(;|$)}', $rrule, $m) ) $this->until = new DateTime($m[1]);
    if ( preg_match('{COUNT=([0-9]+)(;|$)}', $rrule, $m) ) $this->count = $m[1];
    if ( preg_match('{INTERVAL=([0-9]+)(;|$)}', $rrule, $m) ) $this->interval = $m[1];
    if ( preg_match('{WKST=(MO|TU|WE|TH|FR|SA|SU)(;|$)}', $rrule, $m) ) $this->wkst = $m[1];

    if ( preg_match('{BYDAY=(([+-]?[0-9]{0,2}(MO|TU|WE|TH|FR|SA|SU),?)+)(;|$)}', $rrule, $m) )  $this->byday = explode(',',$m[1]);

    if ( preg_match('{BYYEARDAY=([0-9,+-]+)(;|$)}', $rrule, $m) ) $this->byyearday = explode(',',$m[1]);
    if ( preg_match('{BYWEEKNO=([0-9,+-]+)(;|$)}', $rrule, $m) ) $this->byweekno = explode(',',$m[1]);
    if ( preg_match('{BYMONTHDAY=([0-9,+-]+)(;|$)}', $rrule, $m) ) $this->bymonthday = explode(',',$m[1]);
    if ( preg_match('{BYMONTH=(([+-]?[0-1]?[0-9],?)+)(;|$)}', $rrule, $m) ) $this->bymonth = explode(',',$m[1]);
    if ( preg_match('{BYSETPOS=(([+-]?[0-9]{1,3},?)+)(;|$)}', $rrule, $m) ) $this->bysetpos = explode(',',$m[1]);

    if ( preg_match('{BYSECOND=([0-9,]+)(;|$)}', $rrule, $m) ) $this->bysecond = explode(',',$m[1]);
    if ( preg_match('{BYMINUTE=([0-9,]+)(;|$)}', $rrule, $m) ) $this->byminute = explode(',',$m[1]);
    if ( preg_match('{BYHOUR=([0-9,]+)(;|$)}', $rrule, $m) ) $this->byhour = explode(',',$m[1]);

    if ( !isset($this->interval) ) $this->interval = 1;
    switch( $this->freq ) {
      case 'SECONDLY': $this->freq_name = 'second'; break;
      case 'MINUTELY': $this->freq_name = 'minute'; break;
      case 'HOURLY':   $this->freq_name = 'hour';   break;
      case 'DAILY':    $this->freq_name = 'day';    break;
      case 'WEEKLY':   $this->freq_name = 'week';   break;
      case 'MONTHLY':  $this->freq_name = 'month';  break;
      case 'YEARLY':   $this->freq_name = 'year';   break;
      default:
        /** need to handle the error, but FREQ is mandatory so unlikely */
    }
    $this->frequency_string = sprintf('+%d %s', $this->interval, $this->freq_name );
    if ( $GLOBALS['debug_rrule'] ) printf( "Frequency modify string is: '%s', base is: '%s'\n", $this->frequency_string, $this->base->format('c') );
    $this->Start();
  }


  public function set_timezone( $tzstring ) {
    $this->base->setTimezone(new DateTimeZone($tzstring));
  }


  public function Start() {
    $this->instances = array();
    $this->GetMoreInstances();
    $this->rewind();
    $this->finished = false;
  }


  public function rewind() {
    $this->position = -1;
  }


  public function next() {
    $this->position++;
    return $this->current();
  }


  public function current() {
    if ( !$this->valid() ) return null;
    if ( !isset($this->instances[$this->position]) ) $this->GetMoreInstances();
    if ( !$this->valid() ) return null;
    if ( $GLOBALS['debug_rrule'] ) printf( "Returning date from position %d: %s\n", $this->position, $this->instances[$this->position]->format('c') );
    return $this->instances[$this->position];
  }


  public function key() {
    if ( !$this->valid() ) return null;
    if ( !isset($this->instances[$this->position]) ) $this->GetMoreInstances();
    if ( !isset($this->keys[$this->position]) ) {
      $this->keys[$this->position] = $this->instances[$this->position];
    }
    return $this->keys[$this->position];
  }


  public function valid() {
    if ( isset($this->instances[$this->position]) || !$this->finished ) return true;
    return false;
  }


  private function GetMoreInstances() {
    global $rrule_expand_limit;

    if ( $this->finished ) return;
    $got_more = false;
    while( !$this->finished && !$got_more ) {
      if ( !isset($this->current_base) ) {
        $this->current_base = clone($this->base);
      }
      else {
        $this->current_base->modify( $this->frequency_string );
      }
      if ( $GLOBALS['debug_rrule'] ) printf( "Getting more instances from: '%s' - %d\n", $this->current_base->format('c'), count($this->instances) );
      $this->current_set = array( clone($this->current_base) );
      foreach( $rrule_expand_limit[$this->freq] AS $bytype => $action ) {
        if ( isset($this->{$bytype}) ) $this->{$action.'_'.$bytype}();
      }
      sort($this->current_set);
      if ( isset($this->bysetpos) ) $this->limit_bysetpos();

      $position = count($this->instances) - 1;
      if ( $GLOBALS['debug_rrule'] ) printf( "Inserting %d from current_set into position %d\n", count($this->current_set), $position + 1 );
      foreach( $this->current_set AS $k => $instance ) {
        if ( $instance < $this->base ) continue;
        if ( isset($this->until) && $instance > $this->until ) {
          $this->finished = true;
          return;
        }
        if ( !isset($this->instances[$position]) || $instance != $this->instances[$position] ) {
          $got_more = true;
          $position++;
          $this->instances[$position] = $instance;
          if ( $GLOBALS['debug_rrule'] ) printf( "Added date %s into position %d in current set\n", $instance->format('c'), $position );
          if ( isset($this->count) && ($position + 1) >= $this->count ) $this->finished = true;
        }
      }
    }
  }


  static public function date_mask( $date, $y, $mo, $d, $h, $mi, $s ) {
    $date_parts = explode(',',$date->format('Y,m,d,H,i,s'));

    $tz = $date->getTimezone();
    if ( isset($y) || isset($mo) || isset($d) ) {
      if ( isset($y) ) $date_parts[0] = $y;
      if ( isset($mo) ) $date_parts[1] = $mo;
      if ( isset($d) ) $date_parts[2] = $d;
      $date->setDate( $date_parts[0], $date_parts[1], $date_parts[2] );
    }
    if ( isset($h) || isset($mi) || isset($s) ) {
      if ( isset($h) ) $date_parts[3] = $h;
      if ( isset($mi) ) $date_parts[4] = $mi;
      if ( isset($s) ) $date_parts[5] = $s;
      $date->setTime( $date_parts[3], $date_parts[4], $date_parts[5] );
    }
    return $date;
  }


  private function expand_bymonth() {
    $instances = $this->current_set;
    $this->current_set = array();
    foreach( $instances AS $k => $instance ) {
      foreach( $this->bymonth AS $k => $month ) {
        $this->current_set[] = $this->date_mask( clone($instance), null, $month, null, null, null, null);
      }
    }
  }

  private function expand_bymonthday() {
    $instances = $this->current_set;
    $this->current_set = array();
    foreach( $instances AS $k => $instance ) {
      foreach( $this->bymonth AS $k => $monthday ) {
        $this->current_set[] = $this->date_mask( clone($instance), null, null, $monthday, null, null, null);
      }
    }
  }

  private function expand_byday() {
    if ( $this->freq == 'MONTHLY' ) {
      if ( isset($this->bymonthday) ) {
        $this->limit_byday();  /** Per RFC5545 3.3.10 from note 1 to table */
        return;
      }
      $first_of_month = $this->date_mask( clone($this->current_set[0]), null, null, 1, null, null, null);
      $dow_of_first = $first_of_month->format('w'); // 0 == Sunday
      $days_in_month = cal_days_in_month(CAL_GREGORIAN, $first_of_month->format('m'), $first_of_month->format('Y'));
    }
    $instances = $this->current_set;
    $this->current_set = array();
    foreach( $instances AS $k => $instance ) {
      if ( $this->freq == 'MONTHLY' ) {
        foreach( $this->byday AS $k => $weekday ) {
          if ( preg_match('{([+-])?(\d)?(MO|TU|WE|TH|FR|SA|SU)}', $weekday, $matches ) ) {
            $dow = (strpos('**SUMOTUWETHFRSA', $matches[3]) / 2) - 1;
            $first_dom = 1 + $dow - $dow_of_first;  if ( $first_dom < 1 ) $first_dom +=7;  // e.g. 1st=WE, dow=MO => 1+1-3=-1 => MO is 6th, etc.
            $whichweek = intval($matches[2]);
            if ( $GLOBALS['debug_rrule'] ) printf( "Expanding MONTHLY $weekday from date %s\n", $instance->format('c') );
            if ( $whichweek > 0 ) {
              $whichweek--;
              $monthday = $first_dom;
              if ( $matches[1] == '-' ) {
                $monthday += 35;
                while( $monthday > $days_in_month ) $monthday -= 7;
                $monthday -= (7 * $whichweek);
              }
              else {
                $monthday += (7 * $whichweek);
              }
              if ( $monthday > 0 && $monthday <= $days_in_month ) {
                $expanded = $this->date_mask( clone($instance), null, null, $monthday, null, null, null);
                if ( $GLOBALS['debug_rrule'] ) printf( "Expanded MONTHLY $weekday now $monthday into date %s\n", $expanded->format('c') );
                $this->current_set[] = $expanded;
              }
            }
            else {
              for( $monthday = $first_dom; $monthday <= $days_in_month; $monthday += 7 ) {
                $expanded = $this->date_mask( clone($instance), null, null, $monthday, null, null, null);
                if ( $GLOBALS['debug_rrule'] ) printf( "Expanded MONTHLY $weekday now $monthday into date %s\n", $expanded->format('c') );
                $this->current_set[] = $expanded;
              }
            }
          }
        }
      }
      else if ( $this->freq == 'WEEKLY' ) {
        /**
        * @TODO: This should really allow for WKST, since if we start a series
        * on (eg.) TH and interval > 1, a MO, TU, FR repeat will not be in the
        * same week with this code.
        */
        $dow_of_instance = $instance->format('w'); // 0 == Sunday
        foreach( $this->byday AS $k => $weekday ) {
          $dow = (strpos('**SUMOTUWETHFRSA', $weekday) / 2) - 1;
          $offset = $dow - $dow_of_instance;
          if ( $offset < 0 ) $offset += 7;
          $this_expand = clone($instance);
          $this_expand->modify( sprintf('+%d day', $offset) );
          $this->current_set[] = $this_expand;
          if ( $GLOBALS['debug_rrule'] ) printf( "Expanded WEEKLY $weekday into date %s\n", $this_expand->format('c') );
        }
      }
      else { // YEARLY
      }

    }
  }

  private function expand_byhour() {
    $instances = $this->current_set;
    $this->current_set = array();
    foreach( $instances AS $k => $instance ) {
      foreach( $this->bymonth AS $k => $month ) {
        $this->current_set[] = $this->date_mask( clone($instance), null, null, null, $hour, null, null);
      }
    }
  }

  private function expand_byminute() {
    $instances = $this->current_set;
    $this->current_set = array();
    foreach( $instances AS $k => $instance ) {
      foreach( $this->bymonth AS $k => $month ) {
        $this->current_set[] = $this->date_mask( clone($instance), null, null, null, null, $minute, null);
      }
    }
  }

  private function expand_bysecond() {
    $instances = $this->current_set;
    $this->current_set = array();
    foreach( $instances AS $k => $instance ) {
      foreach( $this->bymonth AS $k => $second ) {
        $this->current_set[] = $this->date_mask( clone($instance), null, null, null, null, null, $second);
      }
    }
  }


  private function limit_generally( $fmt_char, $element_name ) {
    $instances = $this->current_set;
    $this->current_set = array();
    foreach( $instances AS $k => $instance ) {
      foreach( $this->{$element_name} AS $k => $element_value ) {
        /* if ( $GLOBALS['debug_rrule'] ) */ printf( "Limiting '$fmt_char' on '%s' => '%s' ?=? '%s' \n", $instance->format('c'), $instance->format($fmt_char), $element_value );
        if ( $instance->format($fmt_char) == $element_value ) $this->current_set[] = $instance;
      }
    }
  }

  private function limit_byday() {
    $fmt_char = 'w';
    $instances = $this->current_set;
    $this->current_set = array();
    foreach( $this->byday AS $k => $weekday ) {
      $dow = (strpos('**SUMOTUWETHFRSA', $weekday) / 2) - 1;
      foreach( $instances AS $k => $instance ) {
        if ( $GLOBALS['debug_rrule'] ) printf( "Limiting '$fmt_char' on '%s' => '%s' ?=? '%s' (%d) \n", $instance->format('c'), $instance->format($fmt_char), $weekday, $dow );
        if ( $instance->format($fmt_char) == $dow ) $this->current_set[] = $instance;
      }
    }
  }

  private function limit_bymonth()    {   $this->limit_generally( 'm', 'bymonth' );     }
  private function limit_byyearday()  {   $this->limit_generally( 'z', 'byyearday' );   }
  private function limit_bymonthday() {   $this->limit_generally( 'd', 'bymonthday' );  }
  private function limit_byhour()     {   $this->limit_generally( 'H', 'byhour' );      }
  private function limit_byminute()   {   $this->limit_generally( 'i', 'byminute' );    }
  private function limit_bysecond()   {   $this->limit_generally( 's', 'bysecond' );    }


  private function limit_bysetpos( ) {
    $instances = $this->current_set;
    $count = count($instances);
    $this->current_set = array();
    foreach( $this->bysetpos AS $k => $element_value ) {
      if ( $GLOBALS['debug_rrule'] ) printf( "Limiting bysetpos %s of %d instances\n", $element_value, $count );
      if ( $element_value > 0 ) {
        $this->current_set[] = $instances[$element_value - 1];
      }
      else if ( $element_value < 0 ) {
        $this->current_set[] = $instances[$count + $element_value];
      }
    }
  }


}