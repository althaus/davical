<?php
require_once("../inc/always.php");
dbg_error_log( "freebusy", " User agent: %s", ((isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "Unfortunately Mulberry and Chandler don't send a 'User-agent' header with their requests :-(")) );
dbg_log_array( "headers", '_SERVER', $_SERVER, true );
require_once("HTTPAuthSession.php");
$session = new HTTPAuthSession();

/**
* Submission parameters recommended by calconnect, plus some generous alternatives
*/
param_to_global('fb_start', '#^[a-z0-9/:.,+-]+$#i', 'start', 'from');
param_to_global('fb_end', '#^[a-z0-9/:.,+-]+$#i', 'end', 'until', 'finish', 'to');
param_to_global('fb_period', '#^[+-]?P?(\d+[WD]?)(T(\d+H)?(\d+M)?(\d+S)?)?+$#', 'period');
param_to_global('fb_format', '#^\S+/\S+$#', 'format');
param_to_global('fb_user', '#^.*$#', 'user', 'userid', 'user_no', 'email');
param_to_global('fb_token', '#^[a-z0-9+/-]+$#i', 'token');

if ( isset($fb_period) ) $fb_period = strtoupper($fb_period);

if ( !isset($fb_start) || $fb_start == '' )  $fb_start  = date('Y-m-d\TH:i:s', time() - 86400 ); // no recommended default.  -1 day
if ( (!isset($fb_period) && !isset($fb_end)) || ($fb_period == '' && $fb_end == '') )
  $fb_period = 'P44D'; // 44 days - 2 days more than recommended default

require_once("CalDAVRequest.php");

if ( isset($fb_format) && $fb_format != 'text/calendar' ) {
  $request->DoResponse( 406, 'This server only supports the text/calendar format for freebusy URLs' );
}


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


