<?php
require_once("always.php");

if ( !function_exists('apache_request_headers') ) {
  function apache_request_headers() {
    return getallheaders();
  }
}

function dbg_log_array( $name, $arr, $recursive = false ) {
  foreach ($arr as $key => $value) {
    error_log( "caldav: DBG: $name: >>$key<< = >>$value<<");
    if ( $recursive && (gettype($value) == 'array' || gettype($value) == 'object') ) {
      dbg_log_array( "$name"."[$key]", $value, $recursive );
    }
  }
}

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

  case 'PUT':
    include_once("caldav-PUT.php");
    break;

  default:
    error_log("Unhandled request method >>".$_SERVER['REQUEST_METHOD']."<<");
    dbg_log_array( 'HEADERS', $raw_headers );
    dbg_log_array( '_SERVER', $_SERVER, true );
    error_log( "caldav: DBG: RAW: ".str_replace("\n", "", str_replace("\r", "", $raw_post)));
}


?>