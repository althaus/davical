<?php
/**
* An object representing a DAV 'resource'
*
* @package   davical
* @subpackage   Resource
* @author    Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Morphoss Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

require_once('AwlQuery.php');


/**
* Given a privilege string, or an array of privilege strings, return a bit mask
* of the privileges.
* @param mixed $raw_privs The string (or array of strings) of privilege names
* @return integer A bit mask of the privileges.
*/
function privilege_to_bits( $raw_privs ) {
  $out_priv = 0;

  if ( gettype($raw_privs) == 'string' ) $raw_privs = array( $raw_privs );

  foreach( $raw_privs AS $priv ) {
    $trim_priv = trim(strtolower(preg_replace( '/^.*:/', '', $priv)));
    switch( $trim_priv ) {
      case 'read'                            : $out_priv |=  4609;  break;  // 1 + 512 + 4096
      case 'write'                           : $out_priv |=   198;  break;  // 2 + 4 + 64 + 128
      case 'write-properties'                : $out_priv |=     2;  break;
      case 'write-content'                   : $out_priv |=     4;  break;
      case 'unlock'                          : $out_priv |=     8;  break;
      case 'read-acl'                        : $out_priv |=    16;  break;
      case 'read-current-user-privilege-set' : $out_priv |=    32;  break;
      case 'bind'                            : $out_priv |=    64;  break;
      case 'unbind'                          : $out_priv |=   128;  break;
      case 'write-acl'                       : $out_priv |=   256;  break;
      case 'read-free-busy'                  : $out_priv |=  4608;  break; //  512 + 4096
      case 'schedule-deliver'                : $out_priv |=  7168;  break; // 1024 + 2048 + 4096
      case 'schedule-deliver-invite'         : $out_priv |=  1024;  break;
      case 'schedule-deliver-reply'          : $out_priv |=  2048;  break;
      case 'schedule-query-freebusy'         : $out_priv |=  4096;  break;
      case 'schedule-send'                   : $out_priv |= 57344;  break; // 8192 + 16384 + 32768
      case 'schedule-send-invite'            : $out_priv |=  8192;  break;
      case 'schedule-send-reply'             : $out_priv |= 16384;  break;
      case 'schedule-send-freebusy'          : $out_priv |= 32768;  break;
      default:
        dbg_error_log( 'ERROR', 'Cannot convert privilege of "%s" into bits.', $priv );

    }
  }
  return $out_priv;
}


/**
* Given a bit mask of the privileges, will return an array of the
* text values of privileges.
* @param integer $raw_bits A bit mask of the privileges.
* @return mixed The string (or array of strings) of privilege names
*/
function bits_to_privilege( $raw_bits ) {
  $out_priv = array();

  if ( ($raw_bits & 16777215) == 16777215 ) $out_priv[] = 'all';

  if ( (in_bits &   1) != 0 ) $out_priv[] = 'DAV::read';
  if ( (in_bits &   8) != 0 ) $out_priv[] = 'DAV::unlock';
  if ( (in_bits &  16) != 0 ) $out_priv[] = 'DAV::read-acl';
  if ( (in_bits &  32) != 0 ) $out_priv[] = 'DAV::read-current-user-privilege-set';
  if ( (in_bits & 256) != 0 ) $out_priv[] = 'DAV::write-acl';
  if ( (in_bits & 512) != 0 ) $out_priv[] = 'urn:ietf:params:xml:ns:caldav:read-free-busy';

  if ( (in_bits & 198) != 0 ) {
    if ( (in_bits & 198) == 198 ) $out_priv[] = 'DAV::write';
    if ( (in_bits &   2) != 0 ) $out_priv[] = 'DAV::write-properties';
    if ( (in_bits &   4) != 0 ) $out_priv[] = 'DAV::write-content';
    if ( (in_bits &  64) != 0 ) $out_priv[] = 'DAV::bind';
    if ( (in_bits & 128) != 0 ) $out_priv[] = 'DAV::unbind';
  }

  if ( (in_bits & 7168) != 0 ) {
    if ( (in_bits & 7168) == 7168 ) $out_priv[] = 'urn:ietf:params:xml:ns:caldav:schedule-deliver';
    if ( (in_bits & 1024) != 0 ) $out_priv[] = 'urn:ietf:params:xml:ns:caldav:schedule-deliver-invite';
    if ( (in_bits & 2048) != 0 ) $out_priv[] = 'urn:ietf:params:xml:ns:caldav:schedule-deliver-reply';
    if ( (in_bits & 4096) != 0 ) $out_priv[] = 'urn:ietf:params:xml:ns:caldav:schedule-query-freebusy';
  }

  if ( (in_bits & 57344) != 0 ) {
    if ( (in_bits & 57344) == 57344 ) $out_priv[] = 'urn:ietf:params:xml:ns:caldav:schedule-send';
    if ( (in_bits &  8192) != 0 ) $out_priv[] = 'urn:ietf:params:xml:ns:caldav:schedule-send-invite';
    if ( (in_bits & 16384) != 0 ) $out_priv[] = 'urn:ietf:params:xml:ns:caldav:schedule-send-reply';
    if ( (in_bits & 32768) != 0 ) $out_priv[] = 'urn:ietf:params:xml:ns:caldav:schedule-send-freebusy';
  }

  return $out_priv;
}


/**
* A class for things to do with a DAV Resource
*
* @package   davical
*/
class DAVResource
{
  /**
  * @var The partial URL of the resource within our namespace
  */
  protected $dav_name;

  /**
  * @var Boolean: does the resource actually exist yet?
  */
  protected $exists;

  /**
  * @var The unique etag associated with the current version of the resource
  */
  protected $unique_tag;

  /**
  * @var The actual resource content, if it exists and is not a collection
  */
  protected $resource;

  /**
  * @var The type of the resource, possibly multiple
  */
  protected $resourcetype;

  /**
  * @var The type of the content
  */
  protected $contenttype;

  /**
  * @var An object which is the collection record for this resource, or for it's container
  */
  private $collection;

  /**
  * @var An object which is the principal for this resource, or would be if it existed.
  */
  private $principal;

  /**
  * @var A bit mask representing the current user's privileges towards this DAVResource
  */
  private $privileges;

  /**
  * @var True if this resource is a collection of any kind
  */
  private $_is_collection;

  /**
  * @var True if this resource is a principal-URL
  */
  private $_is_principal;

  /**
  * @var True if this resource is a calendar collection
  */
  private $_is_calendar;

  /**
  * @var True if this resource is an addressbook collection
  */
  private $_is_addressbook;

  /**
  * @var An array of the methods we support on this resource.
  */
  private $supported_methods;

  /**
  * @var An array of the reports we support on this resource.
  */
  private $supported_reports;

  /**
  * @var An array of the component types we support on this resource.
  */
  private $supported_components;

  /**
  * Constructor
  * @param mixed $parameters If null, an empty Resourced is created.
  *     If it is an object then it is expected to be a record that was
  *     read elsewhere.
  */
  function __construct( $parameters = null ) {
    $this->exists        = null;
    $this->dav_name      = null;
    $this->unique_tag    = null;
    $this->resource      = null;
    $this->collection    = null;
    $this->principal     = null;
    $this->resourcetype  = null;
    $this->contenttype   = null;
    $this->privileges    = null;

    $this->_is_collection = false;
    $this->_is_principal = false;
    $this->_is_calendar = false;
    $this->_is_addressbook = false;
    if ( isset($parameters) && is_object($parameters) ) {
      $this->FromRow($parameters);
    }
    else if ( isset($parameters) && is_array($parameters) ) {
      if ( isset($parameters['path']) ) {
        $this->FromPath($parameters['path']);
      }
    }
    else if ( isset($parameters) && is_string($parameters) ) {
      $this->FromPath($parameters);
    }
  }


  /**
  * Initialise from a database row
  * @param object $row The row from the DB.
  */
  function FromRow($row) {
    global $c;

    if ( $row == null ) return;

    $this->exists = true;
    foreach( $row AS $k => $v ) {
      dbg_error_log( 'DAVResource', 'Processing resource property "%s" has "%s".', $row->dav_name, $k );
      switch ( $k ) {
        case 'dav_etag':
          $this->unique_tag = '"'.$v.'"';
          break;

        default:
          $this->{$k} = $v;
      }
    }
  }


  /**
  * Initialise from a path
  * @param object $inpath The path to populate the resource data from
  */
  function FromPath($inpath) {
    global $c;

    $ourpath = rawurldecode($inpath);

    /** Allow a path like .../username/calendar.ics to translate into the calendar URL */
    if ( preg_match( '#^(/[^/]+/[^/]+).ics$#', $ourpath, $matches ) ) {
      $ourpath = $matches[1]. '/';
    }

    /** remove any leading protocol/server/port/prefix... */
    $base_path = ConstructURL('/');
    if ( preg_match( '%^(.*?)'.str_replace('%', '\\%',$base_path).'(.*)$%', $ourpath, $matches ) ) {
      if ( $matches[1] == '' || $matches[1] == $c->protocol_server_port ) {
        $ourpath = $matches[2];
      }
    }

    /** strip doubled slashes */
    if ( strstr($ourpath,'//') ) $ourpath = preg_replace( '#//+#', '/', $ourpath);

    if ( substr($ourpath,0,1) != '/' ) $ourpath = '/'.$ourpath;

    $this->dav_name = $ourpath;
  }


  /**
  * Find the collection associated with this resource.
  */
  function FetchCollection() {
    global $c, $session;
    /**
    * RFC4918, 8.3: Identifiers for collections SHOULD end in '/'
    *    - also discussed at more length in 5.2
    *
    * So we look for a collection which matches one of the following URLs:
    *  - The exact request.
    *  - If the exact request, doesn't end in '/', then the request URL with a '/' appended
    *  - The request URL truncated to the last '/'
    * The collection URL for this request is therefore the longest row in the result, so we
    * can "... ORDER BY LENGTH(dav_name) DESC LIMIT 1"
    */
    $this->collection = (object) array(
      'collection_id' => -1,
      'type' => 'nonexistent',
      'is_calendar' => false, 'is_principal' => false, 'is_addressbook' => false, 'resourcetypes' => '<DAV::collection/>',
    );

    $base_sql = 'SELECT collection.*, path_privileges(:session_principal, collection.dav_name), ';
    $base_sql .= 'p.principal_id, p.type_id AS principal_type_id, p.active AS principal_active, ';
    $base_sql .= 'p.displayname AS principal_displayname, p.default_privileges AS principal_default_privileges ';
    $base_sql .= 'FROM collection LEFT JOIN principal p USING (user_no) WHERE ';
    $sql = $base_sql .'dav_name = :raw_path ';
    $params = array( ':raw_path' => $this->dav_name, ':session_principal' => $session->principal_id );
    if ( !preg_match( '#/$#', $this->dav_name ) ) {
      $sql .= ' OR dav_name = :up_to_slash OR dav_name = :plus_slash ';
      $params[':up_to_slash'] = preg_replace( '#[^/]*$#', '', $this->dav_name);
      $params[':plus_slash']  = $this->dav_name.'/';
    }
    $sql .= 'ORDER BY LENGTH(dav_name) DESC LIMIT 1';
    $qry = new AwlQuery( $sql, $params );
    if ( $qry->Exec('DAVResource') && $qry->rows() == 1 && ($row = $qry->Fetch()) ) {
      $this->collection = $row;
      if ( $row->is_calendar == 't' )
        $this->collection->type = 'calendar';
      else if ( $row->is_addressbook == 't' )
        $this->collection->type = 'addressbook';
      else if ( preg_match( '#^((/[^/]+/)\.(in|out)/)[^/]*$#', $this->dav_name, $matches ) )
        $this->collection->type = 'schedule-'. $matches[3]. 'box';
      else
        $this->collection->type = 'collection';
    }
    else if ( preg_match( '#^((/[^/]+/)\.(in|out)/)[^/]*$#', $this->dav_name, $matches ) ) {
      // The request is for a scheduling inbox or outbox (or something inside one) and we should auto-create it
      $params = array( ':user_no' => $session->user_no, ':parent_container' => $matches[2], ':dav_name' => $matches[1] );
      $params['displayname'] = $session->fullname . ($matches[3] == 'in' ? ' Inbox' : ' Outbox');
      $this->collection->type = 'schedule-'. $matches[3]. 'box';
      $params['resourcetypes'] = sprintf('<DAV::collection/><urn:ietf:params:xml:ns:caldav:%s/>', $this->collection->type );
      $sql = <<<EOSQL
INSERT INTO collection ( user_no, parent_container, dav_name, dav_displayname, is_calendar, created, modified, dav_etag, resourcetypes )
    VALUES( :user_no, :parent_container, :dav_name, :dav_displayname, FALSE, current_timestamp, current_timestamp, '1', :resourcetypes )
EOSQL;
      $qry = new AwlQuery( $sql, $params );
      $qry->Exec('DAVResource');
      dbg_error_log( 'DAVResource', 'Created new collection as "$displayname".' );

      $params = array( ':raw_path' => $this->dav_name, ':session_principal' => $session->principal_id );
      $qry = new AwlQuery( $base_sql . ' dav_name = :raw_path', $params );
      if ( $qry->Exec('DAVResource') && $qry->rows() == 1 && ($row = $qry->Fetch()) ) {
        $this->collection = $row;
      }
    }
    else if ( preg_match( '#^((/[^/]+/)calendar-proxy-(read|write))/?[^/]*$#', $this->dav_name, $matches ) ) {
      $this->collection->type = 'proxy';
      $this->_is_proxy_request = true;
      $this->proxy_type = $matches[3];
      $this->collection->dav_name = $matches[1].'/';
    }
    else if ( preg_match( '#^(/[^/]+)/?$#', $this->dav_name, $matches) ) {
      $this->collection->dav_name = $matches[1].'/';
      $this->collection->type = 'principal';
      $this->_is_principal = true;
    }
    else if ( preg_match( '#^(/principals/[^/]+/[^/]+)/?$#', $this->dav_name, $matches) ) {
      $this->collection->dav_name = $matches[1].'/';
      $this->collection->type = 'principal_link';
      $this->_is_principal = true;
    }
    else if ( $this->dav_name == '/' ) {
      $this->collection->dav_name = '/';
      $this->collection->type = 'root';
    }
    else {
      dbg_error_log( 'DAVResource', 'No collection for path "%s".', $this->dav_name );
      $this->collection->exists = false;
      $this->collection->dav_name = preg_replace('{/[^/]*$}', '/', $this->dav_name);
    }

    $this->_is_collection = ( $this->collection->dav_name == $this->dav_name || $this->collection->dav_name == $this->dav_name.'/' );
    if ( $this->_is_collection ) {
      $this->dav_name = $this->collection->dav_name;
      $this->_is_calendar    = ($this->collection->type == 'calendar');
      $this->_is_addressbook = ($this->collection->type == 'addressbook');
      $this->contenttype = 'httpd/unix-directory';
      if ( isset($this->collection->dav_etag) ) $this->unique_tag = $this->collection->dav_etag;
      if ( isset($this->collection->created) )  $this->created = $this->collection->created;
      if ( isset($this->collection->modified) ) $this->modified = $this->collection->modified;
      if ( isset($this->collection->resourcetype) )
        $this->resourcetype = $this->collection->resourcetype;
      else {
        $this->resourcetype = '<DAV::collection/>';
        if ( $this->_is_principal )
          $this->resourcetype .= '<DAV::principal/>';
        else {
          $this->exists = (!isset($this->collection->exists) || $this->collection->exists);
        }
      }
    }
  }


  /**
  * Find the principal associated with this resource.
  */
  function FetchPrincipal() {
    global $c, $session;
    $this->principal = new CalDAVPrincipal( array( "path" => $this->dav_name ) );
    if ( $this->IsPrincipal() ) {
      $this->contenttype = 'httpd/unix-directory';
      $this->unique_tag = $this->principal->dav_etag;
      $this->created = $this->principal->created;
      $this->modified = $this->principal->modified;
      $this->resourcetype = '<DAV::principal/>';
    }
  }


  /**
  * Retrieve the actual resource.
  */
  function FetchResource() {
    global $c, $session;

    if ( isset($this->exists) ) return;   // True or false, we've got what we can already
    if ( !isset($this->collection) ) $this->FetchCollection();
    if ( $this->_is_collection ) return;   // We have all we're going to read

    $sql = <<<EOQRY
SELECT * FROM caldav_data LEFT JOIN calendar_item USING (collection_id,dav_id)
     WHERE caldav_data.dav_name = :dav_name
EOQRY;
    $params = array( ':dav_name' => $this->dav_name );

    $qry = new AwlQuery( $sql, $params );
    if ( $qry->Exec('DAVResource') && $qry->rows() > 0 ) {
      $this->exists = true;
      $this->resource = $qry->Fetch();
      $this->unique_tag = $this->resource->dav_etag;
      $this->created = $this->resource->created;
      $this->modified = $this->resource->modified;
      $this->contenttype = 'text/calendar';
      $this->resourcetype = '';
    }
    else {
      $this->exists = false;
    }
  }


  /**
  * Build permissions for this URL
  */
  function FetchPrivileges() {
    global $session;

    if ( $this->dav_name == '/' || $this->dav_name == '' ) {
      $this->privileges = 1; // read
//      dbg_error_log( 'DAVResource', 'Read permissions for user accessing /' );
      return;
    }

    if ( $session->AllowedTo('Admin') ) {
      $this->privileges = privilege_to_bits('all');
//      dbg_error_log( 'DAVResource', 'Full permissions for an administrator.' );
      return;
    }

    if ( $this->IsPrincipal() ) {
      if ( !isset($this->principal) ) $this->FetchPrincipal();
      $this->privileges = $this->principal->Privileges();
//      dbg_error_log( 'DAVResource', 'Privileges of "%s" for user accessing principal "%s"', $this->privileges, $this->principal->username );
      return;
    }


    $this->privileges = 0;
    if ( !isset($this->collection) ) $this->FetchCollection();
    if ( !isset($this->collection->path_privileges) ) {
      $parent_path = preg_replace('{/[^/]*/$}', '/', $this->collection->dav_name );
//      dbg_error_log( 'DAVResource', 'Checking privileges of "%s" - parent of "%s"', $parent_path, $this->collection->dav_name );
      $parent = new DAVResource( $parent_path );

      $this->collection->path_privileges = $parent->Privileges();
    }

    $this->privileges = $this->collection->path_privileges;
  }


  /**
  * Return the privileges bits for the current session user to this resource
  */
  function Privileges() {
    if ( !isset($this->privileges) ) $this->FetchPrivileges();
    return $this->privileges;
  }


  /**
  * Is the user has the privileges to do what is requested.
  */
  function HavePrivilegeTo( $do_what ) {
    if ( !isset($this->privileges) ) $this->FetchPrivileges();
    $test_bits = privilege_to_bits( $do_what );
//    dbg_error_log( 'DAVResource', 'Testing privileges of "%s"(%d) against allowed "%s" => "%s"', $do_what, $test_bits, $this->privileges, ($this->privileges & $test_bits) );
    return ($this->privileges & $test_bits) > 0;
  }


  /**
  * Returns the array of privilege names converted into XMLElements
  */
  function BuildPrivileges( $privilege_names=null, $xmldoc=null ) {
    if ( $privilege_names == null ) {
      if ( !isset($this->privileges) ) $this->FetchPrivileges();
      $privilege_names = bits_to_privilege($this->privileges);
    }
    if ( !isset($xmldoc) && isset($GLOBALS['reply']) ) $xmldoc = $GLOBALS['reply'];
    $privileges = array();
    foreach( $privilege_names AS $k ) {
//      dbg_error_log( 'DAVResource', 'Adding privilege "%s".', $k );
      $privilege = new XMLElement('privilege');
      if ( isset($xmldoc) )
        $xmldoc->NSElement($privilege,$k);
      else
        $privilege->NewElement($k);
      $privileges[] = $privilege;
    }
    return $privileges;
  }


  /**
  * Returns the array of supported methods
  */
  function FetchSupportedMethods( ) {
    if ( isset($this->supported_methods) ) return $this->supported_methods;
    if ( !isset($this->collection) ) $this->FetchCollection();

    $this->supported_methods = array(
      'OPTIONS' => '',
      'PROPFIND' => '',
      'REPORT' => '',
      'DELETE' => '',
      'LOCK' => '',
      'UNLOCK' => ''
    );
    if ( $this->IsCollection() ) {
/*      if ( $this->IsPrincipal() ) {
        $this->supported_methods['MKCALENDAR'] = '';
        $this->supported_methods['MKCOL'] = '';
      } */
      switch ( $this->collection->type ) {
        case 'root':
        case 'email':
          // We just override the list completely here.
          $this->supported_methods = array(
            'OPTIONS' => '',
            'PROPFIND' => '',
            'REPORT' => ''
          );
          break;
        case 'schedule-inbox':
        case 'schedule-outbox':
          $this->supported_methods = array_merge(
            $this->supported_methods,
            array(
              'POST' => '', 'GET' => '', 'PUT' => '', 'HEAD' => '', 'PROPPATCH' => ''
            )
          );
          break;
        case 'calendar':
          $this->supported_methods['GET'] = '';
          $this->supported_methods['PUT'] = '';
          $this->supported_methods['HEAD'] = '';
          break;
        case 'collection':
        case 'principal':
          $this->supported_methods['GET'] = '';
          $this->supported_methods['PUT'] = '';
          $this->supported_methods['HEAD'] = '';
          $this->supported_methods['MKCOL'] = '';
          $this->supported_methods['MKCALENDAR'] = '';
          $this->supported_methods['PROPPATCH'] = '';
          break;
      }
    }
    else {
      $this->supported_methods = array_merge(
        $this->supported_methods,
        array(
          'GET' => '',
          'HEAD' => '',
          'PUT' => ''
        )
      );
    }

    return $this->supported_methods;
  }


  /**
  * Checks whether the resource is locked, returning any lock token, or false
  *
  * @todo This logic does not catch all locking scenarios.  For example an infinite
  * depth request should check the permissions for all collections and resources within
  * that.  At present we only maintain permissions on a per-collection basis though.
  */
  function IsLocked( $depth = 0 ) {
    if ( !isset($this->_locks_found) ) {
      $this->_locks_found = array();
      /**
      * Find the locks that might apply and load them into an array
      */
      $sql = 'SELECT * FROM locks WHERE :this_path::text ~ (\'^\'||dav_name||:match_end)::text';
      $qry = new AwlQuery($sql, array( ':this_path' => $this->dav_name, ':match_end' => ($depth == DEPTH_INFINITY ? '' : '$') ) );
      if ( $qry->Exec('DAVResource',__LINE__,__FILE__) ) {
        while( $lock_row = $qry->Fetch() ) {
          $this->_locks_found[$lock_row->opaquelocktoken] = $lock_row;
        }
      }
      else {
        $this->DoResponse(500,i18n("Database Error"));
        // Does not return.
      }
    }

    foreach( $this->_locks_found AS $lock_token => $lock_row ) {
      if ( $lock_row->depth == DEPTH_INFINITY || $lock_row->dav_name == $this->dav_name ) {
        return $lock_token;
      }
    }

    return false;  // Nothing matched
  }


  /**
  * Checks whether this resource is a collection
  */
  function IsCollection() {
    return $this->_is_collection;
  }


  /**
  * Checks whether this resource is a principal
  */
  function IsPrincipal() {
    return $this->_is_collection;
  }


  /**
  * Checks whether this resource is a calendar
  */
  function IsCalendar() {
    return $this->_is_calendar;
  }


  /**
  * Checks whether this resource is an addressbook
  */
  function IsAddressbook() {
    return $this->_is_addressbook;
  }


  /**
  * Checks whether this resource actually exists, in the virtual sense, within the hierarchy
  */
  function Exists() {
    if ( ! isset($this->exists) ) {
      if ( $this->IsPrincipal() ) {
        if ( !isset($this->principal) ) $this->FetchPrincipal();
        $this->exists = $this->principal->Exists();
      }
      else if ( $this->IsCollection() ) {
        if ( !isset($this->collection) ) $this->FetchCollection();
      }
      else {
        if ( !isset($this->resource) ) $this->FetchResource();
      }
    }
    dbg_error_log('DAVResource',' Checking whether "%s" exists.  It would appear %s.', $this->dav_name, ($this->exists ? 'so' : 'not') );
    return $this->exists;
  }


  /**
  * Returns the dav_name of the resource in our internal namespace
  */
  function dav_name() {
    if ( isset($this->dav_name) ) return $this->dav_name;
    return null;
  }


  /**
  * Returns the principal-URL for this resource
  */
  function principal_url() {
    if ( !isset($this->principal) ) $this->FetchPrincipal();
    if ( $this->principal->Exists() ) {
      return $this->principal->principal_url;
    }
    return null;
  }


  /**
  * Returns the principal-URL for this resource
  */
  function unique_tag() {
    if ( isset($this->unique_tag) ) return $this->unique_tag;
    if ( $this->IsCollection() && !isset($this->collection) ) {
      $this->FetchCollection();
      if ( $this->IsPrincipal() && !isset($this->principal) ) $this->FetchPrincipal();
    }
    else if ( !isset($this->resource) ) $this->FetchResource();

    if ( $this->exists !== true || !isset($this->unique_tag) ) $this->unique_tag = '';

    return $this->unique_tag;
  }


  /**
  * Checks whether the target collection is publicly_readable
  */
  function IsPublic() {
    if ( !isset($this->collection) ) $this->FetchCollection();
    return ( isset($this->collection->publicly_readable) && $this->collection->publicly_readable == 't' );
  }


  /**
  * Return the type of whatever contains this resource, or would if it existed.
  */
  function ContainerType() {
    if ( !isset($this->collection) ) $this->FetchCollection();
    if ( $this->IsPrincipal() ) return 'root';
    if ( !$this->IsCollection() ) return $this->collection->type;

    if ( ! isset($this->collection->parent_container) ) return null;

    if ( isset($this->parent_container_type) ) return $this->parent_container_type;

    if ( preg_match('#/[^/]+/#', $this->collection->parent_container) ) {
      $this->parent_container_type = 'principal';
    }
    else {
      $qry = new AwlQuery('SELECT * FROM collection WHERE dav_name = :parent_name',
                                array( ':parent_name' => $this->collection->parent_container ) );
      if ( $qry->Exec('DAVResource') && $qry->rows() > 0 && $parent = $qry->Fetch() ) {
        if ( $parent->is_calendar == 't' )
          $this->parent_container_type = 'calendar';
        else if ( $parent->is_addressbook == 't' )
          $this->parent_container_type = 'addressbook';
        else if ( preg_match( '#^((/[^/]+/)\.(in|out)/)[^/]*$#', $this->dav_name, $matches ) )
          $this->parent_container_type = 'schedule-'. $matches[3]. 'box';
        else
          $this->parent_container_type = 'collection';
      }
      else
        $this->parent_container_type = null;
    }
    return $this->parent_container_type;
  }


  /**
  * Return general server-related properties, in plain form
  */
  function GetProperty( $name ) {
    global $c, $session;

//    dbg_error_log( 'DAVResource', 'Processing "%s".', $name );
    $value = null;

    switch( $name ) {
      case 'collection_id':
        if ( !isset($this->collection) ) $this->FetchCollection();
        return $this->collection->collection_id;
        break;

      default:
        if ( $this->_is_principal ) {
          if ( !isset($this->principal) ) $this->FetchPrincipal();
          if ( isset($this->principal->{$name}) ) return $this->principal->{$name};
          if ( isset($this->collection->{$name}) ) return $this->collection->{$name};
        }
        else if ( $this->_is_collection ) {
          if ( !isset($this->collection) ) $this->FetchCollection();
          if ( isset($this->collection->{$name}) ) return $this->collection->{$name};
          if ( isset($this->principal->{$name}) ) return $this->principal->{$name};
        }
        else {
          if ( !isset($this->resource) ) $this->FetchResource();
          if ( isset($this->resource->{$name}) ) return $this->resource->{$name};
          if ( !isset($this->principal) ) $this->FetchPrincipal();
          if ( isset($this->principal->{$name}) ) return $this->principal->{$name};
          if ( !isset($this->collection) ) $this->FetchCollection();
          if ( isset($this->collection->{$name}) ) return $this->collection->{$name};
        }
        dbg_error_log( 'ERROR', 'Request for property "%s" which is not understood.', $name );
    }

    return $value;
  }


  /**
  * Return general server-related properties for this URL
  */
  function ResourceProperty( $tag, $prop, $reply = null, &$denied ) {
    global $c, $session;

    if ( $reply === null ) $reply = $GLOBALS['reply'];

    dbg_error_log( 'DAVResource', 'Processing "%s" on "%s".', $tag, $this->dav_name );

    switch( $tag ) {
      case 'DAV::href':
        $prop->NewElement('href', ConstructURL($this->dav_name) );
        break;

      case 'DAV::getcontenttype':
        if ( isset($this->contenttype) ) $prop->NewElement('getcontenttype', $this->contenttype );
        break;

      case 'DAV::resourcetype':
        $prop->NewElement('resourcetype', $this->resourcetype );
        break;

      case 'DAV::displayname':
        if ( isset($this->displayname) ) $prop->NewElement('displayname', $this->displayname );
        break;

      case 'DAV::getlastmodified':
        $prop->NewElement('getlastmodified', $this->modified );
        break;

      case 'DAV::creationdate':
        $prop->NewElement('creationdate', $this->created );
        break;

      case 'DAV::getcontentlanguage':
        $locale = (isset($c->current_locale) ? $c->current_locale : '');
        if ( isset($this->locale) && $this->locale != '' ) $locale = $this->locale;
        $prop->NewElement('getcontentlanguage', $locale );
        break;

      case 'DAV::owner':
        // After a careful reading of RFC3744 we see that this must be the principal-URL of the owner
        $reply->DAVElement( $prop, 'owner', $reply->href( $this->principal_url() ) );
        break;

      // Empty tag responses.
      case 'DAV::alternate-URI-set':
      case 'DAV::getcontentlength':
        $prop->NewElement( $reply->Tag($tag));
        break;

      case 'DAV::getetag':
        if ( $this->_is_collection ) {
          return false;
        }
        else {
          $prop->NewElement('getetag', $this->unique_tag );
        }
        break;

      case 'SOME-DENIED-PROPERTY':  /** @todo indicating the style for future expansion */
        $denied[] = $reply->Tag($tag);
        break;

      case 'http://calendarserver.org/ns/:getctag':
        if ( $this->_is_collection ) {
          $prop->NewElement('http://calendarserver.org/ns/:getctag', $this->unique_tag );
        }
        else {
          return false;
        }
        break;

      case 'urn:ietf:params:xml:ns:caldav:calendar-data':
        if ( $this->_is_collection ) {
          if ( !isset($this->resource) ) $this->FetchResource();
          $reply->CalDAVElement($prop, $k, $this->resource->caldav_data );
        }
        else {
          return false;
        }
        break;

      default:
        dbg_error_log( 'DAVResource', 'Request for unsupported property "%s" of path "%s".', $tag, $this->dav_name );
        return false;
    }
    return true;
  }


  /**
  * Construct XML propstat fragment for this resource
  *
  * @param array $properties The requested properties for this resource
  *
  * @return string An XML fragment with the requested properties for this resource
  */
  function GetPropStat( $properties ) {
    global $session, $c, $request, $reply;

    dbg_error_log('DAVResource',': GetPropStat: href "%s"', $this->dav_name );

    $prop = new XMLElement('prop');
    $denied = array();
    $not_found = array();
    foreach( $properties AS $k => $tag ) {
//      dbg_error_log( 'DAVResource', 'Looking at resource "%s" for property [%s]"%s".', $this->dav_name, $k, $tag );
      if ( ! $this->ResourceProperty($tag, $prop, $reply, $denied ) ) {
        dbg_error_log( 'DAVResource', 'Request for unsupported property "%s" of resource "%s".', $tag, $this->dav_name );
        $not_found[] = $reply->Tag($tag);
      }
    }
    $status = new XMLElement('status', 'HTTP/1.1 200 OK' );

    $elements = array( new XMLElement( 'propstat', array($prop,$status) ) );

    if ( count($denied) > 0 ) {
      $status = new XMLElement('status', 'HTTP/1.1 403 Forbidden' );
      $noprop = new XMLElement('prop');
      foreach( $denied AS $k => $v ) {
        $noprop->NewElement( $v );
      }
      $elements[] = new XMLElement( 'propstat', array( $noprop, $status) );
    }

    if ( count($not_found) > 0 ) {
      $status = new XMLElement('status', 'HTTP/1.1 404 Not Found' );
      $noprop = new XMLElement('prop');
      foreach( $not_found AS $k => $v ) {
        $noprop->NewElement( $v );
      }
      $elements[] = new XMLElement( 'propstat', array( $noprop, $status) );
    }
    return $elements;
  }


  /**
  * Render XML for this resource
  *
  * @param array $properties The requested properties for this principal
  * @param reference $reply A reference to the XMLDocument being used for the reply
  * @param boolean $props_only Default false.  If true will only return the fragment with the properties, not a full response fragment.
  *
  * @return string An XML fragment with the requested properties for this principal
  */
  function RenderAsXML( $properties, &$reply, $props_only = false ) {
    global $session, $c, $request;

    dbg_error_log('DAVResource',': RenderAsXML: Principal "%s"', $this->username );

    $prop = new XMLElement('prop');
    $denied = array();
    $not_found = array();
    foreach( $properties AS $k => $tag ) {
      if ( ! $this->ResourceProperty($tag, $prop, $reply) ) {
        dbg_error_log( 'DAVResource', 'Request for unsupported property "%s" of principal "%s".', $tag, $this->username );
        $not_found[] = $reply->Tag($tag);
      }
    }

    if ( $props_only ) return $prop;

    $status = new XMLElement('status', 'HTTP/1.1 200 OK' );

    $propstat = new XMLElement( 'propstat', array( $prop, $status) );
    $href = $reply->href( ConstructURL($this->dav_name) ); /** @TODO: make ::href() into an accessor */

    $elements = array($href,$propstat);

    if ( count($denied) > 0 ) {
      $status = new XMLElement('status', 'HTTP/1.1 403 Forbidden' );
      $noprop = new XMLElement('prop');
      foreach( $denied AS $k => $v ) {
        $noprop->NewElement( $v );
      }
      $elements[] = new XMLElement( 'propstat', array( $noprop, $status) );
    }

    if ( count($not_found) > 0 ) {
      $status = new XMLElement('status', 'HTTP/1.1 404 Not Found' );
      $noprop = new XMLElement('prop');
      foreach( $not_found AS $k => $v ) {
        $noprop->NewElement( $v );
      }
      $elements[] = new XMLElement( 'propstat', array( $noprop, $status) );
    }

    $response = new XMLElement( 'response', $elements );

    return $response;
  }

}
