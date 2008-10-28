<?php
/**
* CalDAV Server - handle OPTIONS method
*
* @package   davical
* @subpackage   caldav
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("OPTIONS", "method handler");

if ( ! $request->AllowedTo('read') ) {
  $request->DoResponse( 403, translate("You may not access that collection") );
}

$exists = false;
$is_calendar = false;

if ( $request->path == '/' ) {
  $exists = true;
}
else {
  if ( preg_match( '#^/[^/]+/$#', $request->path) ) {
    $sql = "SELECT user_no, '/' || username || '/' AS dav_name, md5( '/' || username || '/') AS dav_etag, ";
    $sql .= "updated AS created, fullname AS dav_displayname, FALSE AS is_calendar FROM usr WHERE user_no = $request->user_no ; ";
  }
  else {
    $sql = "SELECT user_no, dav_name, dav_etag, created, dav_displayname, is_calendar FROM collection WHERE user_no = $request->user_no AND dav_name = ".qpg($request->path);
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

if ( !$exists ) {
  $request->DoResponse( 404, translate("No collection found at that location.") );
}

/**
* As yet we only support quite a limited range of options.  When we see clients looking
* for more than this we will work to support them further.  So we can see clients trying
* to use such methods there is a configuration option to override and allow lying about
* what is available.
*/
if ( isset($c->override_allowed_methods) )
  $allowed = $c->override_allowed_methods;
else {
  $allowed = "OPTIONS, GET, HEAD, PUT, DELETE, PROPFIND, MKCOL, MKCALENDAR, LOCK, UNLOCK, REPORT, PROPPATCH, POST";
  if ( $request->path == '/' ) {
    $exists = true;
    $allowed = "OPTIONS, GET, HEAD, PROPFIND, REPORT";
  }
}
header( "Allow: $allowed");


$request->DoResponse( 200, "" );

?>
