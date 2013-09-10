<?php
ini_set('display_errors', 'On');
error_reporting(E_ALL);
require_once('/home/milan/projects/awl/inc/vCalendar.php');


$headers = "From: Enrico <milan@sez.com>\n";
$headers .= "MIME-Version: 1.0\n";
$headers .= "Content-Type: text/calendar; method=REQUEST;\n";
$headers .= '        charset="UTF-8"';
$headers .= "\n";
$headers .= "Content-Transfer-Encoding: 7bit";


$subject = "hello";

$message = <<<ENDMESSAGE
BEGIN:VCALENDAR
VERSION:2.0
CALSCALE:GREGORIAN
METHOD:REQUEST
BEGIN:VEVENT
DTSTART:20130916T080000Z
DTEND:20130916T090000Z
DTSTAMP:20130916T075116Z
ORGANIZER;CN=Enrico Simonetti:mailto:enrico@test.com
UID:12345678
ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP= TRUE;CN=Sample:mailto:sample@test.com
DESCRIPTION:Complete event on http://www.sample.com/get_event.php?id=12345678
LOCATION: Sydney
SEQUENCE:0
STATUS:CONFIRMED
SUMMARY:Test iCalendar
TRANSP:OPAQUE
END:VEVENT
END:VCALENDAR
ENDMESSAGE;

//$result = mail("milan.medlik@gmail.com, milan@morphoss.com", $subject, $message, $headers);
if($result){

}
