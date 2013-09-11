<?php
ini_set('display_errors', 'On');
error_reporting(E_ALL);

// for config and awl library - require_once('/home/milan/projects/awl/inc/AwlQuery.php'); require_once('../config/config.php');
require_once('../htdocs/always.php');
require_once('/home/milan/projects/awl/inc/vCalendar.php');

class MailHandler {
    public function __construct(){
        dbg_error_log('MailHandler - construct');
    }

    public function sendInvitation(){
        $sql = 'SELECT calendar_item.dav_id as dav_id, user_no, attendee, dtstamp, dtstart, dtend, summary, uid,'
                // extra parameters
                . ' location, transp, url, priority, class, description'
                . ' FROM calendar_item LEFT JOIN calendar_attendee ON calendar_item.dav_id = calendar_attendee.dav_id'
                // select calendar_items contained attendee who is remote, have email, and waiting for invitation (email_status=2)
                . ' WHERE attendee LIKE \'mailto:%\' AND is_remote=TRUE AND email_status=2';

        $qry = new AwlQuery($sql);
        $qry->Exec('calendar_items');
        $rows = $qry->rows();




        while(($row = $qry->Fetch())){
            $sqlattendee = 'SELECT email as attendee, CONCAT(\'CN=\',usr.fullname) as property FROM usr WHERE usr.user_no = :user_no'
                . ' UNION '
                . 'SELECT attendee, property FROM calendar_attendee WHERE calendar_attendee.dav_id = :dav_id';

            $qryattendee = new AwlQuery($sqlattendee);
            $qryattendee->Bind(':user_no', $row->user_no);
            $qryattendee->Bind(':dav_id', $row->dav_id);
            $qryattendee->Exec('attendess');

            $attendees = array();
            while(($rowattendee = $qryattendee->Fetch())){
                $attendees[] = $rowattendee;
            }

            $email = explode(':', $row->attendee)[1];
            dbg_error_log('mail for send invitation:' . $attendees);
            $ctext = $this->renderRowToInvitation($row, $attendees[0], array_shift($attendees));


            $headers = "From: Enrico <milan@sez.com>\n";
            $headers .= "MIME-Version: 1.0\n";
            $headers .= "Content-Type: text/calendar; method=REQUEST;\n";
            $headers .= '        charset="UTF-8"';
            $headers .= "\n";
            $headers .= "Content-Transfer-Encoding: 7bit";

            $result = mail("milan.medlik@gmail.com, milan@morphoss.com", 'invitation', $ctext, $headers);
            if($result){

            }

        }

    }

    /**
     * @param $row - array with :
     * // minimum for invitation http://www.ietf.org/rfc/rfc5546.txt [Page 20]
     *  SUMMARY
     *  DTSTAMP
     *  DTSTART
     *  DTEND
     *  UID
     *
     * // extra params:
     *
     *
     * @param $organizer - array of organizer contain email as attendee and fullname as property column
     * @param $attendees - array of arrays with attendees (attendee, property)
     * @return string
     */
    private function renderRowToInvitation($row, $organizer, $attendees, $status='TENTATIVE'){

        $calendar = new vCalendar();
        $calendar->AddProperty("METHOD", "REQUEST");


        $event = new vComponent();
        $event->SetType("VEVENT");



        $event->AddProperty("SUMMARY", $row->summary);
        $event->AddProperty("DTSTAMP", $row->dtstamp);
        $event->AddProperty("DTSTART", $row->dtstart);
        $event->AddProperty("DTEND", $row->dtend);
        $event->AddProperty("UID", $row->uid);



        $organizerproperty = null;
        if(isset($organizer->property) && $organizer->property != null) {
            $organizerproperty = explode(';', $organizer->property);
        }

        $event->AddProperty("ORGANIZER", $organizer->attendee, $organizerproperty);


        $event->AddProperty("STATUS", $status);

        foreach($attendees as $attendee){
            $property = null;
            if(isset($organizer->property) && $organizer->property != null) {
                $property = explode(';', $organizer->property);
            }

            $event->AddProperty("ATTENDEE", $attendee, $property);
        }


        $calendar->AddComponent($event);

        $result = $calendar->render();


        return $result;
    }
}


$mailHandler = new MailHandler();
$mailHandler->sendInvitation();




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
//if($result){
//
//}
