<?php
require_once("always.php");

$raw_headers = apache_request_headers();
$raw_post = file_get_contents ( 'php://input');

switch ( $_SERVER['REQUEST_METHOD'] ) {
  case 'OPTIONS':
    include_once("caldav-OPTIONS.php");
    break;

  case 'REPORT':
    include_once("caldav-REPORT.php");
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
    dbg_error_log( "Unhandled request method >>%s<<", $_SERVER['REQUEST_METHOD'] );
    dbg_log_array( 'HEADERS', $raw_headers );
    dbg_log_array( '_SERVER', $_SERVER, true );
    dbg_error_log( "RAW: %s", str_replace("\n", "",str_replace("\r", "", $raw_post)) );
}


?>