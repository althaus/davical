<?php
/**
* CalDAV Server - main program
*
* @package   rscds
* @subpackage   caldav
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
require_once("../inc/always.php");
dbg_error_log( "caldav", " User agent: %s", ((isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "Unfortunately Mulberry does not send a 'User-agent' header with its requests :-(")) );
require_once("HTTPAuthSession.php");
$session = new HTTPAuthSession();
dbg_log_array( "headers", '_SERVER', $_SERVER, true );

require_once("CalDAVRequest.php");
$request = new CalDAVRequest();

switch ( $request->method ) {
  case 'OPTIONS':    include_once("caldav-OPTIONS.php");    break;
  case 'REPORT':     include_once("caldav-REPORT.php");     break;
  case 'PROPFIND':   include_once("caldav-PROPFIND.php");   break;
  case 'PROPPATCH':  include_once("caldav-PROPPATCH.php");  break;
  case 'MKCALENDAR': include_once("caldav-MKCALENDAR.php"); break;
  case 'MKCOL':      include_once("caldav-MKCALENDAR.php"); break;
  case 'PUT':        include_once("caldav-PUT.php");        break;
  case 'GET':        include_once("caldav-GET.php");        break;
  case 'HEAD':       include_once("caldav-GET.php");        break;
  case 'DELETE':     include_once("caldav-DELETE.php");     break;
  case 'LOCK':       include_once("caldav-LOCK.php");       break;
  case 'UNLOCK':     include_once("caldav-LOCK.php");       break;

  case 'TESTRRULE':  include_once("test-RRULE.php");        break;

  default:
    dbg_error_log( "caldav", "Unhandled request method >>%s<<", $request->method );
    dbg_log_array( "caldav", '_SERVER', $_SERVER, true );
    dbg_error_log( "caldav", "RAW: %s", str_replace("\n", "",str_replace("\r", "", $request->raw_post)) );
}

$request->DoResponse( 500, translate("The application program does not understand that request.") );

?>