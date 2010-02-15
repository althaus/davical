#!/usr/bin/php
<?php

if ( @file_exists('../../awl/inc/AWLUtilities.php') ) {
  set_include_path('../inc:../../awl/inc');
}
else {
  set_include_path('../inc:/usr/share/awl/inc');
}
require_once("always.php");
$c->dbg = array();
require_once("RRule-v2.php");
require_once('AwlQuery.php');

header("Content-type: text/plain");

echo <<<EOTXT
Testing the RRule v2 Library

EOTXT;

class RRuleTest {
  var $dtstart;
  var $recur;
  var $description;
  var $result_description;
  var $PHP_time;
  var $SQL_time;

  function __construct( $description, $start, $recur, $result_description = null ) {
    $this->description = $description;
    $this->dtstart = $start;
    $this->recur = $recur;
    $this->result_description = $result_description;
    $this->result_limit = 30;
  }

  function PHPTest() {
    $result = '';
    $start = microtime(true);
    $rule = new RepeatRule( $this->dtstart, $this->recur );
    $i = 0;
    while( $date = $rule->next() ) {
      if ( ($i++ % 4) == 0 ) $result .= "\n";
      $result .= "   " . $date->format('Y-m-d H:i:s');
      if ( $i >= $this->result_limit ) break;
    }
    $this->PHP_time = microtime(true) - $start;
    return $result;
  }

  function SQLTest() {
    $result = '';
    $sql = "SELECT event_instances::timestamp AS event_date FROM event_instances(?,?) LIMIT ".$this->result_limit;
    $start = microtime(true);
    $qry = new AwlQuery($sql, $this->dtstart, $this->recur);
    // printf( "%s\n", $qry->querystring);
    if ( $qry->Exec("test") && $qry->rows() > 0 ) {
      $i = 0;
      while( $row = $qry->Fetch() ) {
        if ( ($i++ % 4) == 0 ) $result .= "\n";
        $result .= "   " . $row->event_date;
      }
    }
    $this->SQL_time = microtime(true) - $start;
    return $result;
  }
}


$tests = array(
    new RRuleTest( "Daily for 7 days", "20061103T073000", "RRULE:FREQ=DAILY;COUNT=7" )
  , new RRuleTest( "Weekly for 26 weeks", "20061102T100000", "RRULE:FREQ=WEEKLY;COUNT=26;INTERVAL=1;BYDAY=TH" )
  , new RRuleTest( "Fortnightly for 4 events", "20061103T160000", "RRULE:FREQ=WEEKLY;INTERVAL=2;COUNT=4" )
  , new RRuleTest( "Fortnightly for 28 events", "20061103T160000", "RRULE:FREQ=WEEKLY;INTERVAL=2;UNTIL=20071122T235900" )
  , new RRuleTest( "3/wk for 5 weeks", "20081101T160000", "RRULE:FREQ=WEEKLY;COUNT=15;INTERVAL=1;BYDAY=MO,WE,FR" )
  , new RRuleTest( "Monthly forever", "20061104T073000", "RRULE:FREQ=MONTHLY" )
  , new RRuleTest( "Monthly, on the 1st monday, 2nd wednesday, 3rd friday and last sunday, forever", "20061117T073000", "RRULE:FREQ=MONTHLY;BYDAY=1MO,2WE,3FR,-1SU" )
  , new RRuleTest( "The working days of each month", "20061107T113000", "RRULE:FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;UNTIL=20070101T000000" )
  , new RRuleTest( "The last working day of each month", "20061107T113000", "RRULE:FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1;COUNT=30" )
  , new RRuleTest( "Every working day", "20081020T103000", "RRULE:FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;COUNT=30" )
  , new RRuleTest( "Every working day", "20081020T110000", "RRULE:FREQ=DAILY;INTERVAL=1;BYDAY=MO,TU,WE,TH,FR;COUNT=30" )
//**SQL is wrong  , new RRuleTest( "1st Tuesday, 2nd Wednesday, 3rd Thursday & 4th Friday, every March, June, September, October and December", "20081001T133000", "RRULE:FREQ=MONTHLY;INTERVAL=1;BYDAY=1TU,2WE,3TH,4FR;BYMONTH=3,6,9,10,12" )
  , new RRuleTest( "Every tuesday and friday", "20081017T084500", "RRULE:FREQ=MONTHLY;INTERVAL=1;BYDAY=TU,FR;COUNT=30" )
  , new RRuleTest( "Every tuesday and friday", "20081017T084500", "RRULE:FREQ=WEEKLY;INTERVAL=1;BYDAY=TU,FR;COUNT=30" )
  , new RRuleTest( "Every tuesday and friday", "20081017T084500", "RRULE:FREQ=DAILY;INTERVAL=1;BYDAY=TU,FR;COUNT=30" )
  , new RRuleTest( "Time zone 1", "19700315T030000", "FREQ=YEARLY;INTERVAL=1;BYDAY=3SU;BYMONTH=3" )
  , new RRuleTest( "Time zone 2", "19700927T020000", "FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=9" )
  , new RRuleTest( "Time zone 3", "19810329T030000", "FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU" )
  , new RRuleTest( "Time zone 4", "20000404T010000", "FREQ=YEARLY;BYDAY=1SU;BYMONTH=4;COUNT=15" )
);

foreach( $tests AS $k => $test ) {
  echo "=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=~=\n";
  echo "$test->dtstart - $test->recur\n";
  echo "$test->description\n";
  $php_result = $test->PHPTest();
  $sql_result = $test->SQLTest();
  if ( $php_result == $sql_result ) {
    printf( 'PHP & SQL results are identical (-: P: %6.4lf  & S: %6.4lf'."\n", $test->PHP_time, $test->SQL_time);
  }
  else {
    printf( 'PHP & SQL results differ :-( P: %6.4lf  & S: %6.4lf'."\n", $test->PHP_time, $test->SQL_time);
    echo "PHP Result:\n$php_result\n\n";
    echo "SQL Result:\n$sql_result\n\n";  // Still under development
  }
}


exit(0);
