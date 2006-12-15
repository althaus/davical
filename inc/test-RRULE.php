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
  , "20061103T073000" => "RRULE:FREQ=MONTHLY"
);

foreach( $tests AS $start => $rrule ) {
  echo "$start - $rrule\n";

  $rule = new RRule( $start, $rrule );
  $i = 0;
  do {
    if ( ($i % 4) == 0 ) echo "\n";
    $date = $rule->GetNext();
    if ( isset($date) ) {
      echo "   " . $date->Render();
    }
  }
  while( isset($date) && $i++ < 50 );

  echo "\n\n\n";
}

exit(0);
?>
