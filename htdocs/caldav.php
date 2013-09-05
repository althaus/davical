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
if ( isset($_SERVER['PATH_INFO']) && preg_match( '{^(/favicon.ico|davical.css|(images|js|css)/.+)$}', $_SERVER['PATH_INFO'], $matches ) ) {
  $filename = $_SERVER['DOCUMENT_ROOT'] . preg_replace('{(\.\.|\\\\)}', '', $matches[1]);
  $fh = @fopen($matches[1],'r');
  if ( ! $fh ) {
    @header( sprintf("HTTP/1.1 %d %s", 404, 'Not found') );
  }
  else {
    fpassthru($fh);
  }
  @ob_flush(); exit(0);
}
require_once('./always.php');

if ( isset($_SERVER['PATH_INFO']) && preg_match( '{^/\.well-known/(.+)$}', $_SERVER['PATH_INFO'], $matches ) ) {
  require ('well-known.php');
  @ob_flush(); exit(0);
}
elseif ( isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO'] == '/autodiscover/autodiscover.xml' ) {
  require ('autodiscover-handler.php');
  @ob_flush(); exit(0);
}

function logRequestHeaders() {
  global $c;
  
  /** Log the request headers */
  $lines = apache_request_headers();
  dbg_error_log( "LOG ", "***************** Request Header ****************" );
  dbg_error_log( "LOG ", "%s %s", $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] );
  foreach( $lines AS $k => $v ) {
    if ( $k != 'Authorization' || (isset($c->dbg['password']) && $c->dbg['password'] ) ) 
      dbg_error_log( "LOG headers", "-->%s: %s", $k, $v );
    else
      dbg_error_log( "LOG headers", "-->%s: %s", $k, 'Delicious tasty password eaten by debugging monster!' );
  }
  dbg_error_log( "LOG ", "******************** Request ********************" );

  // Log the request in all it's gory detail.
  $lines = preg_split( '#[\r\n]+#', $c->raw_post );
  foreach( $lines AS $v ) {
    dbg_error_log( "LOG request", "-->%s", $v );
  }
  unset($lines);
}

if ( !isset($c->raw_post) ) $c->raw_post = file_get_contents( 'php://input');
if ( (isset($c->dbg['ALL']) && $c->dbg['ALL']) || (isset($c->dbg['request']) && $c->dbg['request']) )
  logRequestHeaders();

// +100 $_SERVER['PHP_AUTH_USER'] = 'miuan';
// +100 $_SERVER['PHP_AUTH_PW'] = 'a';
require_once('HTTPAuthSession.php');
$session = new HTTPAuthSession();

function send_dav_header() {
  global $c;

  /**
  * access-control is rfc3744, we do most of it, but no way to say that.
  * calendar-schedule is another one we do most of, but the spec is not final yet either.
  */
  if ( isset($c->override_dav_header) ) {
    $dav = $c->override_dav_header;
  }
  else {
    $dav = '1, 2, 3, access-control, calendar-access, calendar-schedule, extended-mkcol, bind, addressbook';
    if ( $c->enable_auto_schedule ) $dav .= ', calendar-auto-schedule';
    if ( !isset($c->disable_caldav_proxy) || $c->disable_caldav_proxy == false) $dav .= ', calendar-proxy';
  }
  $dav = explode( "\n", wordwrap( $dav ) );
  foreach( $dav AS $v ) {
    header( 'DAV: '.trim($v, ', '), false);
  }
}
send_dav_header();  // Avoid polluting global namespace

require_once('CalDAVRequest.php');
$request = new CalDAVRequest();

$allowed = implode( ', ', array_keys($request->supported_methods) );
// header( 'Allow: '.$allowed);

if ( ! ($request->IsPrincipal() || isset($request->collection) || $request->method == 'PUT' || $request->method == 'MKCALENDAR' || $request->method == 'MKCOL' ) ) {
  if ( preg_match( '#^/principals/users(/.*/)$#', $request->path, $matches ) ) {
    // Although this doesn't work with the iPhone, perhaps it will with iCal...
    /** @todo integrate handling this URL into CalDAVRequest.php */
    $redirect_url = ConstructURL('/caldav.php'.$matches[1]);
    dbg_error_log( 'LOG WARNING', 'Redirecting %s for "%s" to "%s"', $request->method, $request->path, $redirect_url );
    header('Location: '.$redirect_url );
    @ob_flush(); exit(0);
  }
}
param_to_global('add_member','.*');
$add_member = isset($add_member);

switch ( $request->method ) {
  case 'OPTIONS':    include_once('caldav-OPTIONS.php');   break;
  case 'REPORT':     include_once('caldav-REPORT.php');    break;
  case 'PROPFIND':   include('caldav-PROPFIND.php');       break;
  case 'GET':        include('caldav-GET.php');            break;
  case 'HEAD':       include('caldav-GET.php');            break;
  case 'PROPPATCH':  include('caldav-PROPPATCH.php');      break;
  case 'POST':
    if ( false && $request->content_type != 'text/vcard' && !$add_member ) {
      include('caldav-POST.php');
      break;
    }
  case 'PUT':
    switch( $request->content_type ) {
      case 'text/calendar':
        include('caldav-PUT-vcalendar.php');
        break;
      case 'text/vcard':
      case 'text/x-vcard':
        include('caldav-PUT-vcard.php');
        break;
      default:
        include('caldav-PUT-default.php');
        break;
    }
    break;
  case 'MKCALENDAR': include('caldav-MKCOL.php');          break;
  case 'MKCOL':      include('caldav-MKCOL.php');          break;
  case 'DELETE':     include('caldav-DELETE.php');         break;
  case 'MOVE':       include('caldav-MOVE.php');           break;
  case 'ACL':        include('caldav-ACL.php');            break;
  case 'LOCK':       include('caldav-LOCK.php');           break;
  case 'UNLOCK':     include('caldav-LOCK.php');           break;
  case 'MKTICKET':   include('caldav-MKTICKET.php');       break;
  case 'DELTICKET':  include('caldav-DELTICKET.php');      break;
  case 'BIND':       include('caldav-BIND.php');           break;

  case 'TESTRRULE':  include('test-RRULE-v2.php');         break;

  default:
    dbg_error_log( 'caldav', 'Unhandled request method >>%s<<', $request->method );
    dbg_log_array( 'caldav', '_SERVER', $_SERVER, true );
    dbg_error_log( 'caldav', 'RAW: %s', str_replace("\n", '',str_replace("\r", '', $request->raw_post)) );
}

$request->DoResponse( 400, translate('The application program does not understand that request.') );

