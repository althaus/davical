<?php
require_once("always.php");
dbg_error_log( "ics", " User agent: %s", $_SERVER['HTTP_USER_AGENT'] );
require_once("BasicAuthSession.php");
require_once("iCalendar.php");


$raw_headers = apache_request_headers();
$raw_post = file_get_contents ( 'php://input');

if ( isset($debugging) && isset($_GET['method']) ) {
  $_SERVER['REQUEST_METHOD'] = $_GET['method'];
}

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
*     the maximum privileges returned from that analysis.
*/
$request_path = $_SERVER['PATH_INFO'];
$path_split = preg_split('#/+#', $request_path );
$permissions = array();
if ( !isset($path_split[1]) || $path_split[1] == '' ) {
  dbg_error_log( "caldav", "No useful path split possible" );
  unset($path_user_no);
  unset($path_username);
  $permissions = array("read" => 'read' );
}
else {
  $path_username = $path_split[1];
  @dbg_error_log( "caldav", "Path split into at least /// %s /// %s /// %s", $path_split[1], $path_split[2], $path_split[3] );
  $qry = new PgQuery( "SELECT * FROM usr WHERE username = ?;", $path_username );
  if ( $qry->Exec("caldav") && $path_user_record = $qry->Fetch() ) {
    $path_user_no = $path_user_record->user_no;
  }
  if ( $session->AllowedTo("Admin") || $session->user_no == $path_user_no ) {
    $permissions = array('read' => 'read', "write" => 'write' );
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
  }
}

header("Content-type: text/plain");

if ( !isset($path_username) && ! $session->AllowedTo("Admin") ) {
  header('HTTP/1.0 401 Unauthorized');
  printf( "You may not request a summarised set of all calendar information." );
  dbg_error_log( "ics", "User '%s' attempted a request for %s which would be all calendar information.", $session->username, $_SERVER['PATH_INFO'] );
}
elseif ( isset($permissions['read']) ) {
  $results = iCalendar::iCalHeader();

  $qry = new PgQuery( "SELECT * FROM caldav_data INNER JOIN calendar_item USING(user_no, dav_name)" );
  if ( $qry->Exec("ics") && $qry->rows > 0 ) {
    while( $resource = $qry->Fetch() ) {
      $item = new iCalendar( array('icalendar' => $resource->caldav_data) );
      $results .= $item->JustThisBitPlease('VEVENT', 99999);
      $results .= $item->JustThisBitPlease('VTODO', 99999);
      $results .= $item->JustThisBitPlease('VJOURNAL', 99999);
    }
  }

  $results .= iCalendar::iCalFooter();

  print $results;
}
else {
  header('HTTP/1.0 401 Unauthorized');
  header("Content-type: text/plain");
  printf( "User '%s' does not have rights to that calendar information.", $session->username );
  dbg_error_log( "ics", "User '%s' does not have rights to that calendar information.", $session->username );
  exit;
}

?>