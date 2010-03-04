<?php
/**
* CalDAV Server - handle MKTICKET method in line with defunct proposed RFC
*   from:  http://tools.ietf.org/html/draft-ito-dav-ticket-00
*
* Why are we using a defunct RFC?  Well, we want to support some kind of system
* for providing a URI to people to give out for granting privileged access
* without requiring logins.  Using a defunct proposed spec seems better than
* inventing our own.  As well as Xythos, Cosmo follows this specification,
* with some documented variations, which we will also follow.  In particular
* we use the xmlns="http://www.xythos.com/namespaces/StorageServer" rather
* than the DAV: namespace.
*
* @package   davical
* @subpackage   caldav
* @author    Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Morphoss Ltd - http://www.morphoss.com/
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
*/
dbg_error_log('MKTICKET', 'method handler');
require_once('AwlQuery.php');

$request->NeedPrivilege('DAV::bind');

require_once('XMLDocument.php');
$reply = new XMLDocument(array( 'DAV:' => '', 'T' => 'http://www.xythos.com/namespaces/StorageServer', 'DT' => 'http://xmlns.davical.org/ticket' ));

$target = new DAVResource( $request->path );
if ( ! $target->Exists() ) {
  $request->XMLResponse( 404, $reply->Render( 'error', new XMLElement('not-found') ) );
}

if ( ! isset($request->xml_tags) ) {
  $request->XMLResponse( 400, $reply->Render( 'error', new XMLElement('missing-xml-for-request') ) );
}

$xmltree = BuildXMLTree( $request->xml_tags, $position);
if ( $xmltree->GetTag() != 'http://www.xythos.com/namespaces/StorageServer:ticketinfo' ) {
  $request->XMLResponse( 400, $reply->Render( 'error', new XMLElement('invalid-xml-for-request') ) );
}

$ticket_visits = 'infinity';
$ticket_timeout = 'Seconds-3600';
$ticket_public = 0;
$ticket_privs_array = array('read-free-busy');
$ticketinfo = $xmltree->GetContent();
foreach( $ticketinfo AS $k => $v ) {
  // <!ELEMENT ticketinfo (id?, owner?, timeout, visits, privilege)>
  switch( $v->GetTag() ) {
    case 'DAV::timeout':
    case 'http://www.xythos.com/namespaces/StorageServer:timeout':
      $ticket_timeout = $v->GetContent();
      break;

    case 'DAV::public':
    case 'http://xmlns.davical.org/ticket:public':
      $ticket_public = 1;
      break;

    case 'DAV::visits':
    case 'http://www.xythos.com/namespaces/StorageServer:visits':
      $ticket_visits = $v->GetContent();
      break;

    case 'DAV::privilege':
    case 'http://www.xythos.com/namespaces/StorageServer:privilege':
      $ticket_privs_array = $v->GetElements(); // Ensure we always get an array back
      $ticket_privileges = 0;
      foreach( $ticket_privs_array AS $k1 => $v1 ) {
        $ticket_privileges |= privilege_to_bits( $v1->GetTag() );
      }
      if ( $ticket_privileges & privilege_to_bits('write') )          $ticket_privileges |= privilege_to_bits( 'read' );
      if ( $ticket_privileges & privilege_to_bits('read') )           $ticket_privileges |= privilege_to_bits( array('read-free-busy', 'read-current-user-privilege-set') );
      if ( $ticket_privileges & privilege_to_bits('read-free-busy') ) $ticket_privileges |= privilege_to_bits( 'schedule-query-freebusy') );
      break;
  }
}

if ( preg_match( '{^([a-z]+)-(\d+)$}', $ticket_timeout, $matches ) ) {
  /** It isn't specified, but timeout seems to be 'unit-number' like 'Seconds-3600', so we make it '3600 Seconds' which PostgreSQL understands */
  $sql_timeout = $matches[2] . ' ' . $matches[1];
}
else {
  $sql_timeout = $ticket_timeout;
}

$sql_visits = ( $ticket_visits == 'infinity' ? -1: intval($ticket_visits) );

$collection_id = $target->GetProperty('collection_id');
$resource_id   = $target->GetProperty('dav_id');

$i = 0;
do {
  $ticket_id = substr(sha1(date('r') .rand(2100000000) . microtime(true)), 7, 8);
  $qry = new AwlQuery(
    'INSERT INTO access_ticket ( ticket_id, dav_owner_id, is_public, privileges, target_collection_id, target_resource_id, expires, visits )
                VALUES( :ticket_id, :owner, :public, :privs, :collection, :resource, (current_timestamp + interval :expires), :visits )',
    array(
      ':ticket_id'   => $ticket_id,
      ':owner'       => $session->principal_id,
      ':public'      => $ticket_public,
      ':privs'       => $ticket_privileges,
      ':collection'  => $collection_id,
      ':resource'    => $resource_id,
      ':expires'     => $sql_timeout,
      ':visits'      => $sql_visits
    )
  )
  $result = $qry->Exec('MKTICKET', __LINE__, __FILE__);
} while( !$result && $i++ < 2 );


$ticketinfo = new XMLElement( 'T:ticketinfo', array(
      new XMLElement( 'T:id', $ticket_id),
      new XMLElement( 'owner', $reply->href( ConstructURL($session->dav_name) ) ),
      new XMLElement( 'privilege', privileges_to_XML(bits_to_privilege($ticket_privileges),$reply)),
      new XMLElement( 'T:timeout', $ticket_timeout),
      new XMLElement( 'T:visits', $ticket_visits)
  )
);
if ( $ticket_public ) $ticketinfo->NewElement( 'DT:public', $ticket_public);

$request->XMLResponse( 200, $reply->Render( 'prop', new XMLElement('T:ticketdiscovery', $ticketinfo) ) );
