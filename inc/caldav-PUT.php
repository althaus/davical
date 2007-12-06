<?php
/**
* CalDAV Server - handle PUT method
*
* @package   rscds
* @subpackage   caldav
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("PUT", "method handler");

if ( ! $request->AllowedTo("read") ) {
  $request->DoResponse(403);
}

if ( isset($c->dbg['ALL']) || $c->dbg['put'] ) {
  $fh = fopen('/tmp/PUT.txt','w');
  fwrite($fh,$request->raw_post);
  fclose($fh);
}

include_once('caldav-PUT-functions.php');
$is_collection = controlRequestContainer($request->username,$request->user_no, $request->path,true);

$lock_opener = $request->FailIfLocked();


if ( $is_collection  ) {
  /**
  * CalDAV does not define the result of a PUT on a collection.  We treat that
  * as an import. The code is in caldav-PUT-functions.php
  */
  import_collection($request->raw_post,$request->user_no,$request->path,true);
  $request->DoResponse( 200 );
  return;
}

$put_action_type = putCalendarResource( $request, $session->user_no, true );
$request->DoResponse( ($put_action_type == 'INSERT' ? 201 : 204) );
