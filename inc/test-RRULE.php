<?php
require_once("../inc/always.php");
require_once("RRule.php");

header("Content-type: text/plain");

echo <<<EOTXT
Testing the RRule Library

EOTXT;

$tests = array(
    "20061103T073000" => "RRULE:FREQ=DAILY;COUNT=7"
  , "20061102T100000" => "RRULE:FREQ=WEEKLY;COUNT=26;INTERVAL=1;BYDAY=TH"
  , "20061103T160000" => "RRULE:FREQ=WEEKLY;INTERVAL=2;UNTIL=20071222T235900"
  , "20061101T160000" => "RRULE:FREQ=WEEKLY;COUNT=15;INTERVAL=1;BYDAY=MO,WE,FR"
  , "20061104T073000" => "RRULE:FREQ=MONTHLY"
  , "20061117T073000" => "RRULE:FREQ=MONTHLY;BYDAY=1MO,2WE,3FR,-1SU"
  , "20061107T103000" => "RRULE:FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR"
  , "20061107T113000" => "RRULE:FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1"
  , "20081020T110000" => "RRULE:FREQ=DAILY;INTERVAL=1;BYDAY=MO,TU,WE,TH,FR"
);

foreach( $tests AS $start => $rrule ) {
  echo "$start - $rrule\n";

  $rule = new RRule( new iCalDate($start), $rrule );
  $i = 0;
  do {
    if ( ($i % 10) == 0 ) echo "\n";
    $date = $rule->GetNext();
    if ( isset($date) ) {
      echo "   " . $date->Render();
    }
  }
  while( isset($date) && $i++ < 30 );

  echo "\n\n\n";
}

exit(0);
