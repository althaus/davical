<?php
/**
* CalDAV Server - handle OPTIONS method
*
* @package   davical
* @subpackage   caldav
* @author    Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Catalyst .Net Ltd, Morphoss Ltd <http://www.morphoss.com/>
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
*/
dbg_error_log("OPTIONS", "method handler");

$request->NeedPrivilege( 'DAV::read' );

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
  elseif ( $c->collections_always_exist && preg_match( "#^/$session->username/#", $request->path) ) {
    $exists = true;
    // Possibly this should be another setting, but it seems unlikely that something that
    // can't deal with collections would issue MKCALENDAR or PROPPATCH commands.
    $is_calendar = true;
  }
}

if ( !$exists ) {
  $request->DoResponse( 404, translate("No collection found at that location.") );
}

$allowed = implode( ', ', array_keys($request->supported_methods) );
header( 'Allow: '.$allowed);

$request->DoResponse( 200, "" );

