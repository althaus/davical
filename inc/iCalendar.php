<?php
/**
* A Class for handling iCalendar data
*
* @package caldav
* @subpackage iCalendar
* @author Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst IT Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

/**
* A Class for handling participants to events
*
* @package caldav
*/
class Participant {
  /**#@+
  * @access private
  */

  /**
  * Participant e-mail
  * @var email string
  */
  var $email;

  /**
  * Status of participant in relation to the event
  * @var status string
  */
  var $status;

  /**
  * Role of participant in relation to event
  * @var email string
  */
  var $role;
  /**#@-*/

  function Participant( $email, $status="NEEDS-ACTION", $role="ATTENDEE" ) {
    $this->email = $email;
    $this->status = $status;
    $this->role = $role;
  }

  function ToString() {
    $rv = sprintf( "ATTENDEE;PARTSTAT=%s%s:%s\n", $this->status, ($this-role == "ATTENDEE" ? "" : "ROLE=$this->role"), $this->email );
    return $rv;
  }
}

/**
* A Class for handling Evends on a calendar
*
* @package caldav
*/
class vEvent {
  /**#@+
  * @access private
  */

  /**
  * List of participants in this event
  * @var participants array
  */
  var $participants = array();

  /**
  * The start time for the event
  * @var start datetime
  */
  var $start;

  /**
  * The duration of the event
  * @var duration interval
  */
  var $duration;

  /**
  * The organizer of othe event
  * @var organizer string
  */
  var $organizer;

  /**
  * The status of the event
  * @var status string
  */
  var $status;

  /**
  * A summary description of the event
  * @var summary string
  */
  var $summary;

  /**
  * A last modified timestamp
  * @var modified int
  */
  var $modified;

  /**
  * A sequence for different revisions of the event
  * @var sequence integer
  */
  var $sequence;

  /**
  * A unique ID for the event
  * @var uid string
  */
  var $uid;

  /**
  * A GUID for the event
  * @var guid string
  */
  var $guid;
  /**#@-*/

  function vEvent( $start, $duration="PT1H", $organizer="", $status="TENTATIVE", $summary="" ) {
    global $c;

    $this->participants = array();
    $this->start = $start;
    $this->duration = $duration;
    $this->organizer = $organizer;
    $this->status = $status;
    $this->summary = $summary;
    $this->modified = iCalendar::EpochTS(time());
    $this->sequence = 1;
    $this->uid = sprintf( "%s@%s", time() * 1000 + rand(0,1000), $c->domainname);
    $this->guid = sprintf( "%s@%s", time() * 1000 + rand(0,1000), $c->domainname);
  }

  function AddParticipant( $email, $status, $role ) {
    $this->participants[] = new Participant($email,$status,$role);
  }

/*
BEGIN:VEVENT
ATTENDEE;PARTSTAT=ACCEPTED;ROLE=CHAIR:mailto:cyrus@example.com
ATTENDEE;PARTSTAT=NEEDS-ACTION:mailto:lisa@example.com
DTSTAMP:20060206T001220Z
DTSTART;TZID=US/Eastern:20060104T100000
DURATION:PT1H
LAST-MODIFIED:20060206T001330Z
ORGANIZER:mailto:cyrus@example.com
SEQUENCE:1
STATUS:TENTATIVE
SUMMARY:Event #3
UID:DC6C50A017428C5216A2F1CD@example.com
X-ABC-GUID:E1CX5Dr-0007ym-Hz@example.com
END:VEVENT
*/
  function ToString() {
    $participants = "";
    foreach( $this->participants AS $k => $p ) {
      $participants .= $p->ToString();
    }
    $fmt = <<<EOFMT
BEGIN:VEVENT
%sDTSTAMP:%s
DTSTART;TXID=%s:%s".
DURATION:%s
LAST-MODIFIED:%s
ORGANIZER:%s
SEQUENCE:%d
STATUS:%s
SUMMARY:%s
UID:%s
X-ABC-GUID:%s
END:VEVENT

EOFMT;
    $string = sprintf( $fmt, $participants, iCalendar::EpochTS(time()), "Pacific/Auckland", $this->start, $this->duration, $this->modified,
                             $this->organizer, $this->sequence, $this->status, $this->summary, $this->uid, $this->guid );
    return $string;
  }
}


/**
* A Class for handling iCalendar data
*
* @package caldav
*/
class iCalendar {

  function iCalendar() {
  }

  function EpochTS($epoch) {
    $ts = date('Ymd\THis\Z', $epoch );
    return $ts;
  }

  function vTimeZone( $tzname ) {
    switch ( $tzname ) {
      case 'Pacific/Auckland':
      default:
        $tzstring = <<<EOTZ
BEGIN:VTIMEZONE
LAST-MODIFIED:20040110T032845Z
TZID:Pacific/Auckland
BEGIN:STANDARD
DTSTART:20000404T020000
RRULE:FREQ=YEARLY;BYDAY=1SU;BYMONTH=4
TZNAME:NZST
TZOFFSETFROM:+1300
TZOFFSETTO:+1200
END:STANDARD
BEGIN:DAYLIGHT
DTSTART:20001026T020000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10
TZNAME:EST
TZOFFSETFROM:+1200
TZOFFSETTO:+1300
END:DAYLIGHT
END:VTIMEZONE

EOTZ;
    }
    return $tzstring;
  }
}

?>