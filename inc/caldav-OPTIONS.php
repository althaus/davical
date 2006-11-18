<?php
/**
* CalDAV Server - handle OPTIONS method
*
* @package   rscds
* @subpackage   caldav
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
  dbg_error_log("OPTIONS", "method handler");

if ( ! isset($permissions['read']) ) {
  header("HTTP/1.1 403 Forbidden");
  header("Content-type: text/plain");
  echo "You may not access that calendar.";
  dbg_error_log("OPTIONS", "Access denied for User: %d, Path: %s", $session->user_no, $request_path);
  return;
}

$exists = false;
$is_calendar = false;

if ( $request_path == '/' ) {
  $exists = true;
}
else {
  if ( preg_match( '#^/[^/]+/$#', $request_path) ) {
    $sql = "SELECT user_no, '/' || username || '/' AS dav_name, md5( '/' || username || '/') AS dav_etag, ";
    $sql .= "updated AS created, fullname AS dav_displayname, FALSE AS is_calendar FROM usr WHERE user_no = $path_user_no ; ";
  }
  else {
    $sql = "SELECT user_no, dav_name, dav_etag, created, dav_displayname, is_calendar FROM collection WHERE user_no = $path_user_no AND dav_name = ".qpg($request_path);
  }
  $qry = new PgQuery($sql );
  if( $qry->Exec("OPTIONS",__LINE__,__FILE__) && $qry->rows > 0 && $collection = $qry->Fetch() ) {
    $is_calendar = ($collection->is_calendar == 't');
    $exists = true;
  }
  elseif ( $c->collections_always_exist ) {
    $exists = true;
    // Possibly this should be another setting, but it seems unlikely that something that
    // can't deal with collections would issue MKCALENDAR or PROPPATCH commands.
    $is_calendar = true;
  }
}

if ( !exists ) {
  header("HTTP/1.1 404 Not Found");
  header("Content-type: text/plain");
  echo "No collection found at that location.";
  dbg_error_log("OPTIONS", "No collection found for User: %d, Path: %s", $session->user_no, $request_path);
  return;
}

  header( "Content-type: text/plain" );
  header( "Content-length: 0" );

  /**
  * As yet we only support quite a limited range of options.  When we see clients looking
  * for more than this we will work to support them further.  We should probably support
  * PROPPATCH, because I suspect that will be used.  Also HEAD and POST being fairly standard
  * should be handled.  COPY and MOVE would seem to be easy also.
  */
  $allowed = "OPTIONS, GET, PUT, DELETE, PROPFIND, PROPPATCH, MKCOL, MKCALENDAR";
  if ( $is_calendar ) $allowed .= ", REPORT";
  header( "Allow: $allowed");
  // header( "Allow: ACL, COPY, DELETE, GET, HEAD, LOCK, MKCALENDAR, MKCOL, MOVE, OPTIONS, POST, PROPFIND, PROPPATCH, PUT, REPORT, SCHEDULE, TRACE, UNLOCK");

  /**
  * FIXME: WTF is calendar-schedule ??  The CalDAV draft 15 doesn't mention it,
  * but some CalDAV servers seem to say they do it.  We'll leave it out for now.
  *
  * access-control is rfc3744, so we will say we do it, but I doubt if we do it
  * in all it's glory really.
  */
  $dav = "1, 2, access-control";
  if ( $is_calendar ) $dav .= ", calendar-access";
  header( "Allow: $allowed");
  header( "DAV: $dav");
  // header( "DAV: 1, 2, access-control, calendar-access, calendar-schedule");

?>