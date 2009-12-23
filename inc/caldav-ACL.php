<?php
/**
* CalDAV Server - handle ACL method
*
* @package   davical
* @subpackage   caldav
* @author    Andrew McMillan <andrew@morphoss.com>
* @copyright Morphoss Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("ACL", "method handler");

require_once('DAVResource.php');

if ( ! ( $request->AllowedTo('read-acl') || $request->AllowedTo('read-current-user-privilege-set') || $request->AllowedTo('write-acl') ) ) {
  $request->DoResponse(403);
}

if ( ! ini_get('open_basedir') && (isset($c->dbg['ALL']) || (isset($c->dbg['put']) && $c->dbg['put'])) ) {
  $fh = fopen('/tmp/MOVE.txt','w');
  if ( $fh ) {
    fwrite($fh,$request->raw_post);
    fclose($fh);
  }
}

$resource = new DAVResource( $request->path );

$request->DoResponse( 200 );
