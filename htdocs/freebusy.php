<?php
require_once("../inc/always.php");
dbg_error_log( "freebusy", " User agent: %s", ((isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "Unfortunately Mulberry and Chandler don't send a 'User-agent' header with their requests :-(")) );
dbg_log_array( "headers", '_SERVER', $_SERVER, true );
require_once("HTTPAuthSession.php");
$session = new HTTPAuthSession();

require_once("CalDAVRequest.php");

/**
* We also allow URLs like .../freebusy.php/user@example.com to work, so long as
* the e-mail matches a single user whose calendar we have rights to.
* NOTE: It is OK for there to *be* duplicate e-mail addresses, just so long as we
* only have read permission (or more) for only one of them.
*/
$request = new CalDAVRequest(array("allow_by_email" => 1));

if ( ! $request->AllowedTo('freebusy') ) $request->DoResponse( 404 );

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


