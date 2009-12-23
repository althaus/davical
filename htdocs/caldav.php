<?php
/**
* CalDAV Server - main program
*
* @package   davical
* @subpackage   caldav
* @author    Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Catalyst .Net Ltd, Morphoss Ltd <http://www.morphoss.com/>
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
*/
require_once('../inc/always.php');
// dbg_error_log( 'caldav', ' User agent: %s', ((isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unfortunately Mulberry does not send a "User-agent" header with its requests :-(')) );
// dbg_log_array( 'headers', '_SERVER', $_SERVER, true );
require_once('HTTPAuthSession.php');
$session = new HTTPAuthSession();

/**
* access-control is rfc3744, we do some of it, but no way to say that.
* calendar-schedule is another one we do some of, but the spec is not final yet either.
*/
if ( isset($c->override_dav_header) ) {
  $dav = $c->override_dav_header;
}
else {
  $dav = '1, 2, 3, access-control, calendar-access, calendar-schedule, extended-mkcol';
}
header( 'DAV: '.$dav);

require_once('CalDAVRequest.php');
$request = new CalDAVRequest();

$allowed = implode( ', ', array_keys($request->supported_methods) );
header( 'Allow: '.$allowed);

if ( ! ($request->IsPrincipal() || isset($request->collection) || $request->method == 'PUT' || $request->method == 'MKCALENDAR' || $request->method == 'MKCOL' ) ) {
  if ( preg_match( '#^/principals/users(/.*/)$#', $request->path, $matches ) ) {
    // Although this doesn't work with the iPhone, perhaps it will with iCal...
    /** @TODO: integrate handling this URL into CalDAVRequest.php */
    $redirect_url = ConstructURL('/caldav.php'.$matches[1]);
    dbg_error_log( 'LOG WARNING', 'Redirecting %s for "%s" to "%s"', $request->method, $request->path, $redirect_url );
    header('Location: '.$redirect_url );
    exit(0);
  }
  dbg_error_log( 'LOG WARNING', 'Attempt to %s url "%s" but no collection exists there.', $request->method, $request->path );
  if ( $request->method == 'GET' || $request->method == 'REPORT' ) {
    $request->DoResponse( 404, translate('There is no collection at that URL.') );
  }
}

switch ( $request->method ) {
  case 'OPTIONS':    include_once('caldav-OPTIONS.php');    break;
  case 'REPORT':     include_once('caldav-REPORT.php');     break;
  case 'PROPFIND':   include('caldav-PROPFIND.php');   break;
  case 'PUT':        include('caldav-PUT.php');        break;
  case 'GET':        include('caldav-GET.php');        break;
  case 'HEAD':       include('caldav-GET.php');        break;
  case 'PROPPATCH':  include('caldav-PROPPATCH.php');  break;
  case 'MKCALENDAR': include('caldav-MKCOL.php');      break;
  case 'MKCOL':      include('caldav-MKCOL.php');      break;
  case 'DELETE':     include('caldav-DELETE.php');     break;
  case 'POST':       include('caldav-POST.php');       break;
  case 'MOVE':       include('caldav-MOVE.php');       break;
  case 'ACL':        include('caldav-ACL.php');        break;
  case 'LOCK':       include('caldav-LOCK.php');       break;
  case 'UNLOCK':     include('caldav-LOCK.php');       break;

  case 'TESTRRULE':  include('test-RRULE.php');        break;

  default:
    dbg_error_log( 'caldav', 'Unhandled request method >>%s<<', $request->method );
    dbg_log_array( 'caldav', '_SERVER', $_SERVER, true );
    dbg_error_log( 'caldav', 'RAW: %s', str_replace("\n", '',str_replace("\r", '', $request->raw_post)) );
}

$request->DoResponse( 500, translate('The application program does not understand that request.') );

