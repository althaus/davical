<?php
require_once("always.php");
dbg_error_log( "caldav", " User agent: %s", $_SERVER['HTTP_USER_AGENT'] );
require_once("BasicAuthSession.php");

$raw_headers = apache_request_headers();
$raw_post = file_get_contents ( 'php://input');

if ( $debugging && isset($_GET['method']) ) {
  $_SERVER['REQUEST_METHOD'] = $_GET['method'];
}

switch ( $_SERVER['REQUEST_METHOD'] ) {
  case 'OPTIONS':
    include_once("caldav-OPTIONS.php");
    break;

  case 'REPORT':
    include_once("caldav-REPORT.php");
    break;

  case 'PROPFIND':
    include_once("caldav-PROPFIND.php");
    break;

  case 'PUT':
    include_once("caldav-PUT.php");
    break;

  case 'GET':
    include_once("caldav-GET.php");
    break;

  case 'DELETE':
    include_once("caldav-DELETE.php");
    break;

  default:
    dbg_error_log( "caldav", "Unhandled request method >>%s<<", $_SERVER['REQUEST_METHOD'] );
    dbg_log_array( "caldav", 'HEADERS', $raw_headers );
    dbg_log_array( "caldav", '_SERVER', $_SERVER, true );
    dbg_error_log( "caldav", "RAW: %s", str_replace("\n", "",str_replace("\r", "", $raw_post)) );
}


?>