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

/**
* Preconditions
   (DAV:no-ace-conflict): The ACEs submitted in the ACL request MUST NOT
   conflict with each other.  This is a catchall error code indicating
   that an implementation-specific ACL restriction has been violated.

   (DAV:no-protected-ace-conflict): The ACEs submitted in the ACL
   request MUST NOT conflict with the protected ACEs on the resource.
   For example, if the resource has a protected ACE granting DAV:write
   to a given principal, then it would not be consistent if the ACL
   request submitted an ACE denying DAV:write to the same principal.

   (DAV:no-inherited-ace-conflict): The ACEs submitted in the ACL
   request MUST NOT conflict with the inherited ACEs on the resource.
   For example, if the resource inherits an ACE from its parent
   collection granting DAV:write to a given principal, then it would not
   be consistent if the ACL request submitted an ACE denying DAV:write
   to the same principal.  Note that reporting of this error will be
   implementation-dependent.  Implementations MUST either report this
   error or allow the ACE to be set, and then let normal ACE evaluation
   rules determine whether the new ACE has any impact on the privileges
   available to a specific principal.

   (DAV:limited-number-of-aces): The number of ACEs submitted in the ACL
   request MUST NOT exceed the number of ACEs allowed on that resource.
   However, ACL-compliant servers MUST support at least one ACE granting
   privileges to a single principal, and one ACE granting privileges to
   a group.

   (DAV:deny-before-grant): All non-inherited deny ACEs MUST precede all
   non-inherited grant ACEs.

   (DAV:grant-only): The ACEs submitted in the ACL request MUST NOT
   include a deny ACE.  This precondition applies only when the ACL
   restrictions of the resource include the DAV:grant-only constraint
   (defined in Section 5.6.1).

   (DAV:no-invert):  The ACL request MUST NOT include a DAV:invert
   element.  This precondition applies only when the ACL semantics of
   the resource includes the DAV:no-invert constraint (defined in
   Section 5.6.2).

   (DAV:no-abstract): The ACL request MUST NOT attempt to grant or deny
   an abstract privilege (see Section 5.3).

   (DAV:not-supported-privilege): The ACEs submitted in the ACL request
   MUST be supported by the resource.

   (DAV:missing-required-principal): The result of the ACL request MUST
   have at least one ACE for each principal identified in a
   DAV:required-principal XML element in the ACL semantics of that
   resource (see Section 5.5).

   (DAV:recognized-principal): Every principal URL in the ACL request
   MUST identify a principal resource.

   (DAV:allowed-principal): The principals specified in the ACEs
   submitted in the ACL request MUST be allowed as principals for the
   resource.  For example, a server where only authenticated principals
   can access resources would not allow the DAV:all or
   DAV:unauthenticated principals to be used in an ACE, since these
   would allow unauthenticated access to resources.
*/

$ace = $xmltree->GetPath("/DAV::acl/DAV::ace/*");

foreach( $ace AS $k => $v ) {
}

$request->DoResponse( 200 );
