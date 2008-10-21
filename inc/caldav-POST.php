<?php
/**
* CalDAV Server - handle PUT method
*
* @package   davical
* @subpackage   caldav
* @author    Andrew McMillan <andrew@morphoss.com>
* @copyright Morphoss Ltd - http://www.morphoss.com/
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("POST", "method handler");

require_once("iCalendar.php");

if ( ! $request->AllowedTo("CALDAV:schedule-send-freebusy")
  && ! $request->AllowedTo("CALDAV:schedule-send-invite")
  && ! $request->AllowedTo("CALDAV:schedule-send-reply") ) {
  $request->DoResponse(403);
}

if ( ! ini_get('open_basedir') && (isset($c->dbg['ALL']) || $c->dbg['post']) ) {
  $fh = fopen('/tmp/POST.txt','w');
  if ( $fh ) {
    fwrite($fh,$request->raw_post);
    fclose($fh);
  }
}


$ical = new iCalendar( array('icalendar' => $request->raw_post) );
switch ( $ical->properties['METHOD'] ) {
  case 'REQUEST':
    break;

  default:
    dbg_error_log("POST", ": Unhandled '%s' method in request.", $ical->properties['METHOD'] );
}