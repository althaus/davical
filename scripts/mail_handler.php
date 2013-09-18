<?php
ini_set('display_errors', 'On');
error_reporting(E_ALL);

// for config and awl library - require_once('/home/milan/projects/awl/inc/AwlQuery.php'); require_once('../config/config.php');
require_once('../htdocs/always.php');
require_once('/home/milan/projects/awl/inc/vCalendar.php');
require_once('../inc/PlancakeEmailParser.php');
require_once('../inc/Consts.php');

// inspired by :
// http://php.net/manual/en/features.commandline.php
function options ( $args )
{
    array_shift( $args );
    $endofoptions = false;

    $ret = array
    (
        'options' => array(),
    );

    while ( $arg = array_shift($args) )
    {

        // if we have reached end of options,
        //we cast all remaining argvs as arguments
        if ($endofoptions)
        {
            $ret['arguments'][] = $arg;
            continue;
        }

        // Is it a command? (prefixed with --)
        if ( substr( $arg, 0, 2 ) === '--' )
        {

            // is it the end of options flag?
            if (!isset ($arg[3]))
            {
                $endofoptions = true;; // end of options;
                continue;
            }

            $value = "";
            $com   = substr( $arg, 2 );

            // is it the syntax '--option=argument'?
            if (strpos($com,'=')){
                list($com, $value) = explode("=", $com, 2);
            }

            $ret['options'][$com] = !empty($value) ? $value : true;
            continue;
        }

        continue;
    }

    return $ret['options'];
}


class MailInviteHandler {

    public function __construct(){
        dbg_error_log('MailHandler - construct');
    }

    public function sendInvitationToAll(){
        $sql = 'SELECT calendar_item.dav_id as dav_id, user_no, attendee, dtstamp, dtstart, dtend, summary, uid, email_status,'
                // extra parameters
                . ' location, transp, url, priority, class, description, calendar_attendee.partstat as partstat'
                . ' FROM calendar_item LEFT JOIN calendar_attendee ON calendar_item.dav_id = calendar_attendee.dav_id'
                // select calendar_items contained attendee who is remote, have email, and waiting for invitation (email_status=2)
                . ' WHERE attendee LIKE \'mailto:%\' AND is_remote=TRUE AND email_status IN ('
                . EMAIL_STATUS::WAITING_FOR_INVITATION_EMAIL . ','
                . EMAIL_STATUS::WAITING_FOR_SCHEDULE_CHANGE_EMAIL . ')';

        $qry = new AwlQuery($sql);
        $qry->Exec('calendar_items');
        //$rows = $qry->rows();

        while(($row = $qry->Fetch())){
            $currentAttendee = $row->attendee;
            $currentDavID = $row->dav_id;
            //$partstat = $row->partstat;


            $sqlattendee = 'SELECT email as attendee, usr.fullname as property, NULL as partstat, TRUE as creator FROM usr WHERE usr.user_no = :user_no'
                . ' UNION '
                . 'SELECT attendee, property, partstat, FALSE as creator FROM calendar_attendee WHERE calendar_attendee.dav_id = :dav_id'
                . ' ORDER BY creator DESC';

            $qryattendee = new AwlQuery($sqlattendee);
            $qryattendee->Bind(':user_no', $row->user_no);
            $qryattendee->Bind(':dav_id', $currentDavID);
            $qryattendee->Exec('attendess');

            $attendees = array();
            while(($rowattendee = $qryattendee->Fetch())){
                $attendees[] = $rowattendee;
            }


            //dbg_error_log('mail for send invitation:' . $attendees);

            $creator = $attendees[0];
            array_shift($attendees);

            $ctext = $this->renderRowToInvitation($row, $creator, $attendees);


            $sent = $this->sendInvitationEmail($currentAttendee, $creator, $ctext);

            if($sent){
                // waiting mail already sent
                if($row->email_status == EMAIL_STATUS::WAITING_FOR_INVITATION_EMAIL){
                    $new_status = EMAIL_STATUS::INVITATION_EMAIL_ALREADY_SENT; // invitation mail already sent
                } else if($row->email_status == EMAIL_STATUS::WAITING_FOR_SCHEDULE_CHANGE_EMAIL){
                    $new_status = EMAIL_STATUS::SCHEDULE_CHANGE_EMAIL_ALREADY_SENT; // invitation mail already sent
                }


                //$this->changeRemoteAttendeeStatrusTo($currentAttendee, $currentDavID, $new_status);
            }
        }

    }

    private function changeRemoteAttendeeStatrusTo($attendee, $dav_id, $statusTo){
        $qry = new AwlQuery('UPDATE calendar_attendee SET email_status=:statusTo WHERE attendee=:attendee AND dav_id=:dav_id');
        $qry->Bind(':statusTo', $statusTo);
        $qry->Bind(':attendee', $attendee);
        $qry->Bind(':dav_id', $dav_id);
        $qry->Exec('changeStatusTo');

        return true;
    }

    private function sendInvitationEmail($attendee, $creator, $renderInvitation){


        $headers = sprintf("From: %s <%s>\n", $creator->property, $creator->attendee);
        $headers .= "MIME-Version: 1.0\n";
        $headers .= "Content-Type: text/calendar; method=REQUEST;\n";
        $headers .= '        charset="UTF-8"';
        $headers .= "\n";
        $headers .= "Content-Transfer-Encoding: 7bit";

        $attendeeWithoutMailTo = explode('mailto:', $attendee);
        if(count($attendeeWithoutMailTo) > 1){
            $attendee = $attendeeWithoutMailTo[1];
        }

//        $result = mail($attendee, 'invitation', $renderInvitation, $headers);
//            if($result){
//
//            }

        return true;
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
    private function renderRowToInvitation($row, $organizer, $attendees){

        $status='TENTATIVE';

        $calendar = new vCalendar();
        $calendar->AddProperty("METHOD", "REQUEST");


        $event = new vComponent();
        $event->SetType("VEVENT");



        $event->AddProperty("SUMMARY", $row->summary);
        $event->AddProperty("DTSTAMP", $row->dtstamp);
        $event->AddProperty("DTSTART", $row->dtstart);
        $event->AddProperty("DTEND", $row->dtend);
        $event->AddProperty("UID", $row->uid);

        // url
        //$event->AddProperty("URL", "http://127.0.0.1/public.php?XDEBUG_SESSION_START=14830");



        $organizerproperty = null;
        if(isset($organizer->property) && $organizer->property != null) {
            $organizerproperty = array( 'CN' => $organizer->property);
        }

        $event->AddProperty("ORGANIZER", $organizer->attendee, $organizerproperty);

        $event->AddProperty("STATUS", $status);


        foreach($attendees as $attendee){
            $partstat = $attendee->partstat;

            $attendeePropertyArray = $this->extractParametersToArrayFromProperty($attendee->property);
            // add partstat from DB
            $attendeePropertyArray['PARTSTAT'] = $partstat;

            $event->AddProperty("ATTENDEE", $attendee->attendee, $attendeePropertyArray );
        }


        $calendar->AddComponent($event);

        $result = $calendar->render();


        return $result;
    }

    /**
     * @param $attendeeproperty - is not iCalendar property in string with param and name
     * @return array|null
     */
    private function extractParametersToArrayFromProperty(&$attendeeproperty){
        $parameters = null;
        if(isset($attendeeproperty) && $attendeeproperty != null) {
            // after symbol ":" -> value what we are not interest
            $superProp = explode(":", $attendeeproperty);

            if(count($superProp) > 0){
                $superProp = $superProp[0];
            } else {
                $superProp = &$attendeeproperty;
            }

            // explore parameters dividet by ";"
            $propertyInArray = explode(';', $superProp);

            // first is name of property -> no params
            if(count($propertyInArray) > 1){
                array_shift($propertyInArray);

                $parameters = array();
                foreach($propertyInArray as $property){
                    $keyproperty = explode('=', $property);
                    $key = strtoupper($keyproperty[0]);

                    if(count($keyproperty) > 1){
                        $parameters[$key] = $keyproperty[1];
                    } else {
                        $parameters[$key] = '';
                    }

                }
            }

        }

        return $parameters;
    }

    public function handleIncomingMail($emailBuffer){
        $pep = new PlancakeEmailParser($emailBuffer);

        $body = $pep->getBody();
        if(empty($body) || !$body){
            $body = $pep->getHtmlBody();
        }

        $vcalendarStart = strpos($body, "BEGIN:VCALENDAR");
        $vcalendarEnd = strpos($body, "END:VCALENDAR", $vcalendarStart);

        $vcalendarBody = substr($body, $vcalendarStart, $vcalendarEnd - $vcalendarStart);


        echo "subject: " . $pep->getSubject() . "\r\n";
        echo "to:" . $pep->getTo()[0] . "\r\n";
        echo "body: " . $body . "\r\n";
        echo "vcalendarBody: " . $vcalendarBody . "\r\n";

        $ical = new vCalendar($vcalendarBody);
        $this->handle_remote_attendee_reply($ical);
    }

    function handle_remote_attendee_reply(vCalendar $ical){
        $attendees = $ical->GetAttendees();

        // attendee reply have just one attendee
        if(count($attendees) != 1){
            return;
        }

        $attendee = $attendees[0];
        $uidparam =  $ical->GetPropertiesByPath("VCALENDAR/*/UID");
        $uid = $uidparam[0]->Value();

        $parameters = $attendee->Parameters();

        $propertyText = $attendee->Name() ;

        foreach($parameters as $key => $param){
            $propertyText .= ';' . $key . '=' . $param;
        }

        $propertyText .= ':' . $attendee->Value();

        $qry = new AwlQuery('UPDATE calendar_attendee SET email_status=:statusTo, partstat=:partstat, property=:property WHERE attendee=:attendee AND dav_id = (SELECT dav_id FROM calendar_item WHERE uid = :uid)');
        // user accepted
        $qry->Bind(':statusTo', EMAIL_STATUS::NORMAL);
        $qry->Bind(':attendee', $attendee->Value());
        $qry->Bind(':uid', $uid);
        $qry->Bind(':property', $propertyText);
        $qry->Bind(':partstat', $parameters['PARTSTAT']);

        $qry->Exec('changeStatusTo');

        return true;
    }
}


$options = options($argv);
//var_dump($options);


if(count($options) > 0){
    $mailHandler = new MailInviteHandler();

    if( isset($options['fmail'])){
        // is presed fmail option?
        // eg: --fmail=/home/email/invitation_reply_1.eml
        $file = fopen($options['fmail'], 'r');
        $mailHandler->handleIncomingMail(stream_get_contents($file));
        fclose($file);
    } else if(isset($options['stdin']) && $options['stdin']) {
        // or presed stdin flag eg: --stdin or --stdin=true
        $mailHandler->handleIncomingMail(stream_get_contents(STDIN));
    }

    // or presed stdin flag eg: --stdin or --stdin=true
    if(isset($options['invite-all']) && $options['invite-all']){
        $mailHandler->sendInvitationToAll();
    }

}






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
