<?php
require_once("always.php");
dbg_error_log( "freebusy", " User agent: %s", ((isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "Unfortunately Mulberry and Chandler don't send a 'User-agent' header with their requests :-(")) );
require_once("BasicAuthSession.php");

$raw_headers = apache_request_headers();
$raw_post = file_get_contents ( 'php://input');

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
  dbg_error_log("freebusy", "Illegal characters /%s/ in calendar path for User: %d, Path: %s", $bad_chars_regex, $session->user_no, $request_path);
  exit(0);
}
dbg_error_log("freebusy", "Legal characters /%s/ in calendar path for User: %d, Path: %s", $bad_chars_regex, $session->user_no, $request_path);

$path_split = preg_split('#/+#', $request_path );
$permissions = array();
if ( !isset($path_split[1]) || $path_split[1] == '' ) {
  dbg_error_log( "freebusy", "No useful path split possible" );
  unset($path_user_no);
  unset($path_username);
}
else {
  $path_username = $path_split[1];
  @dbg_error_log( "freebusy", "Path split into at least /// %s /// %s /// %s", $path_split[1], $path_split[2], $path_split[3] );
  $qry = new PgQuery( "SELECT * FROM usr WHERE username = ?;", $path_username );
  if ( $qry->Exec("caldav") && $path_user_record = $qry->Fetch() ) {
    $path_user_no = $path_user_record->user_no;
  }
  if ( $session->AllowedTo("Admin") ) {
    $permissions = array('read' => 'read', "write" => 'write' );
    dbg_error_log( "freebusy", "Full permissions for a systems administrator" );
  }
  else if ( $session->user_no == $path_user_no ) {
    $permissions = array('read' => 'read', "write" => 'write' );
    dbg_error_log( "freebusy", "Full permissions for user accessing their own hierarchy" );
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
    dbg_error_log( "freebusy", "Restricted permissions for user accessing someone elses hierarchy: read=%s, write=%s", isset($permissions['read']), isset($permissions['write']) );
  }
}

/**
* We also allow URLs like .../freebusy.php/user@example.com to work, so long as
* the e-mail matches a single user whose calendar we have rights to.
* NOTE: It is OK for there to *be* duplicate e-mail addresses, just so long as we
* only have read permission (or more) for only one of them.
*/
if ( isset($by_email) ) unset( $by_email );
if ( preg_match( '#/(\S+@\S+[.]\S+)$#', $request_path, $matches) ) {
  $by_email = $matches[1];
  $qry = new PgQuery("SELECT user_no FROM usr WHERE email = ? AND get_permissions(?,user_no) ~ 'R';", $by_email, $session->user_no );
  if ( $qry->Exec("freebusy",__LINE__,__FILE__) && $qry->rows == 1 ) {
    $email_user = $qry->Fetch();
    $permissions['read'] = 'read';
  }
  else {
    unset( $by_email );
  }
}


if ( !isset($permissions['read']) ) {
  header("HTTP/1.1 403 Forbidden");
  header("Content-type: text/plain");
  echo "You may not access that calendar.";
  dbg_error_log("freebusy", "Access denied for User: %d, Path: %s", $session->user_no, $request_path);
  exit(0);
}

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