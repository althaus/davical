<?php
require_once("always.php");

$raw_headers = apache_request_headers();
$raw_post = file_get_contents ( 'php://input');

switch ( $_SERVER['REQUEST_METHOD'] ) {
  case 'GET':
    include_once("freebusy-GET.php");
    break;

  default:
    dbg_error_log( "freebusy", "Unhandled request method >>%s<<", $_SERVER['REQUEST_METHOD'] );
    dbg_log_array( "freebusy", 'HEADERS', $raw_headers );
    dbg_log_array( "freebusy", '_SERVER', $_SERVER, true );
    dbg_error_log( "freebusy", "RAW: %s", str_replace("\n", "",str_replace("\r", "", $raw_post)) );
}


?>