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
require_once("always.php");
dbg_error_log( "caldav", " User agent: %s", $_SERVER['HTTP_USER_AGENT'] );
require_once("BasicAuthSession.php");

$raw_headers = apache_request_headers();
$raw_post = file_get_contents ( 'php://input');

if ( isset($debugging) && isset($_GET['method']) ) {
  $_SERVER['REQUEST_METHOD'] = $_GET['method'];
}

/**
* A variety of requests may set the "Depth" header to control recursion
*/
$query_depth = ( isset($_SERVER['HTTP_DEPTH']) ? $_SERVER['HTTP_DEPTH'] : 0 );
if ( $query_depth == 'infinite' ) $query_depth = 99;
$query_depth = intval($query_depth);

/**
* Our path is /<script name>/<user name>/<user controlled> if it ends in
* a trailing '/' then it is referring to a DAV 'collection' but otherwise
* it is referring to a DAV data item.
*
* Permissions are controlled as follows:
*  1. if there is no <user name> component, the request has read privileges
*  2. if the requester is an admin, the request has read/write priviliges
*  3. if there is a <user name> component which matches the logged on user
*     then the request has read/write privileges
*  4. otherwise we query the defined relationships between users and use
*     the minimum privileges returned from that analysis.
*/
$request_path = $_SERVER['PATH_INFO'];
$bad_chars_regex = '/[\\^\\[\\(\\\\]/';
if ( preg_match( $bad_chars_regex, $request_path ) ) {
  header("HTTP/1.1 400 Bad Request");
  header("Content-type: text/plain");
  echo "The calendar path contains illegal characters.";
  dbg_error_log("caldav", "Illegal characters /%s/ in calendar path for User: %d, Path: %s", $bad_chars_regex, $session->user_no, $request_path);
  exit(0);
}
dbg_error_log("caldav", "Legal characters /%s/ in calendar path for User: %d, Path: %s", $bad_chars_regex, $session->user_no, $request_path);

$path_split = preg_split('#/+#', $request_path );
$permissions = array();
if ( !isset($path_split[1]) || $path_split[1] == '' ) {
  dbg_error_log( "caldav", "No useful path split possible" );
  unset($path_user_no);
  unset($path_username);
  $permissions = array("read" => 'read' );
  dbg_error_log( "caldav", "Read permissions for user accessing /" );
}
else {
  $path_username = $path_split[1];
  @dbg_error_log( "caldav", "Path split into at least /// %s /// %s /// %s", $path_split[1], $path_split[2], $path_split[3] );
  $qry = new PgQuery( "SELECT * FROM usr WHERE username = ?;", $path_username );
  if ( $qry->Exec("caldav") && $path_user_record = $qry->Fetch() ) {
    $path_user_no = $path_user_record->user_no;
  }
  if ( $session->AllowedTo("Admin") ) {
    $permissions = array('read' => 'read', "write" => 'write' );
    dbg_error_log( "caldav", "Full permissions for a systems administrator" );
  }
  else if ( $session->user_no == $path_user_no ) {
    $permissions = array('read' => 'read', "write" => 'write' );
    dbg_error_log( "caldav", "Full permissions for user accessing their own hierarchy" );
  }
  else if ( isset($path_user_no) ) {
    /**
    * We need to query the database for permissions
    */
    $qry = new PgQuery( "SELECT get_permissions( ?, ? ) AS perm;", $session->user_no, $path_user_no);
    if ( $qry->Exec("caldav") && $permission_result = $qry->Fetch() ) {
      $permission_result = "!".$permission_result->perm; // We prepend something to ensure we get a non-zero position.
      $permissions = array();
      if ( strpos($permission_result,"R") )       $permissions['read'] = 'read';
      if ( strpos($permission_result,"W") )       $permissions['write'] = 'write';
    }
    dbg_error_log( "caldav", "Restricted permissions for user accessing someone elses hierarchy: read=%s, write=%s", isset($permissions['read']), isset($permissions['write']) );
  }
}

/**
* If the content we are receiving is XML then we parse it here.
*/
$xml_parser = xml_parser_create_ns('UTF-8');
$xml_tags = array();
xml_parser_set_option ( $xml_parser, XML_OPTION_SKIP_WHITE, 1 );
xml_parse_into_struct( $xml_parser, $raw_post, $xml_tags );
xml_parser_free($xml_parser);

unset($etag_if_match);
unset($etag_none_match);
if ( isset($_SERVER["HTTP_IF_NONE_MATCH"]) ) {
  $etag_none_match = str_replace('"','',$_SERVER["HTTP_IF_NONE_MATCH"]);
  if ( $etag_none_match == '' ) unset($etag_none_match);
}
if ( isset($_SERVER["HTTP_IF_MATCH"]) ) {
  $etag_if_match = str_replace('"','',$_SERVER["HTTP_IF_MATCH"]);
  if ( $etag_if_match == '' ) unset($etag_if_match);
}


/**
* We put the code for each type of request into a separate include file
*/
$request_method = $_SERVER['REQUEST_METHOD'];
switch ( $request_method ) {
  case 'OPTIONS':    include_once("caldav-OPTIONS.php");    break;
  case 'REPORT':     include_once("caldav-REPORT.php");     break;
  case 'PROPFIND':   include_once("caldav-PROPFIND.php");   break;
  case 'MKCALENDAR': include_once("caldav-MKCALENDAR.php"); break;
  case 'MKCOL':      include_once("caldav-MKCOL.php");      break;
  case 'PUT':        include_once("caldav-PUT.php");        break;
  case 'GET':        include_once("caldav-GET.php");        break;
  case 'HEAD':       include_once("caldav-GET.php");        break;
  case 'DELETE':     include_once("caldav-DELETE.php");     break;

  default:
    dbg_error_log( "caldav", "Unhandled request method >>%s<<", $_SERVER['REQUEST_METHOD'] );
    dbg_log_array( "caldav", 'HEADERS', $raw_headers );
    dbg_log_array( "caldav", '_SERVER', $_SERVER, true );
    dbg_error_log( "caldav", "RAW: %s", str_replace("\n", "",str_replace("\r", "", $raw_post)) );
}

exit(0);

?>