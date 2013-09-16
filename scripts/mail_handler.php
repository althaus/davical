<?php
ini_set('display_errors', 'On');
error_reporting(E_ALL);

// for config and awl library - require_once('/home/milan/projects/awl/inc/AwlQuery.php'); require_once('../config/config.php');
require_once('../htdocs/always.php');
require_once('/home/milan/projects/awl/inc/vCalendar.php');

require_once('../inc/Consts.php');

class MailHandler {



    public function __construct(){
        dbg_error_log('MailHandler - construct');
    }



    public function sendInvitation(){
        $sql = 'SELECT calendar_item.dav_id as dav_id, user_no, attendee, dtstamp, dtstart, dtend, summary, uid, email_status,'
                // extra parameters
                . ' location, transp, url, priority, class, description'
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

            $sqlattendee = 'SELECT email as attendee, usr.fullname as property, TRUE as creator FROM usr WHERE usr.user_no = :user_no'
                . ' UNION '
                . 'SELECT attendee, property, FALSE as creator FROM calendar_attendee WHERE calendar_attendee.dav_id = :dav_id'
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

        $result = mail($attendee, 'invitation', $renderInvitation, $headers);
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

        // url
        $event->AddProperty("URL", "http://127.0.0.1/public.php?XDEBUG_SESSION_START=14830");


        $organizerproperty = null;
        if(isset($organizer->property) && $organizer->property != null) {
            $organizerproperty = array( 'CN' => $organizer->property);
        }

        $event->AddProperty("ORGANIZER", $organizer->attendee, $organizerproperty);

        $event->AddProperty("STATUS", $status);

        foreach($attendees as $attendee){
            $property = $this->recoveryPropertyFromString($attendee->property);
            $event->AddProperty("ATTENDEE", $attendee->attendee);
        }


        $calendar->AddComponent($event);

        $result = $calendar->render();


        return $result;
    }

    private function recoveryPropertyFromString(&$attendeeproperty){
        $resultproperty = null;
        if(isset($attendeeproperty) && $attendeeproperty != null) {
            $propertyarray = explode(';', $attendeeproperty);
            $resultproperty = array();
            foreach($propertyarray as $property){
                $keyproperty = explode('=', $property);
                $key = $keyproperty[0];

                if(count($keyproperty) > 1){
                    $resultproperty[$key] = $keyproperty[1];
                } else {
                    $resultproperty[$key] = '';
                }

            }
        }

        return $resultproperty;
    }
}


$mailHandler = new MailHandler();
$mailHandler->sendInvitation();




