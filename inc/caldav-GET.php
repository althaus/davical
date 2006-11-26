<?php
/**
* CalDAV Server - handle GET method
*
* @package   rscds
* @subpackage   caldav
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("get", "GET method handler");

if ( ! $request->AllowedTo('read') ) {
  $request->DoResponse( 403, translate("You may not access that calendar") );
}

$qry = new PgQuery( "SELECT * FROM caldav_data WHERE user_no = ? AND dav_name = ? ;", $request->user_no, $request->path);
dbg_error_log("get", "%s", $qry->querystring );
if ( $qry->Exec("GET") && $qry->rows == 1 ) {
  $event = $qry->Fetch();

  /**
  * FIXME: There may be some circumstances where someone wants to send
  * an If-Match or If-None-Match header.  I'm not sure what that means here,
  * so we will leave it unimplemented at this point.
  */
  $request->DoResponse( 200, ($request_method == "HEAD" ? "" : $event->caldav_data), "text/calendar; charset=UTF-8" );
}
else if ( $qry->rows < 1 ) {
  $request->DoResponse( 404, "Calendar Resource Not Found." );
}
else if ( $qry->rows > 1 ) {
  $request->DoResponse( 500, "Database Error - Multiple Rows Match." );
}
else {
  $request->DoResponse( 500, "Database Error." );
}

?>