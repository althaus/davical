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
  * @var The types of the resource, possibly multiple
  */
  protected $resourcetypes;

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
  * @var True if this resource is, or is in, a proxy collection
  */
  private $_is_proxy_request;

  /**
  * @var An array of the methods we support on this resource.
  */
  private $supported_methods;

  /**
  * @var An array of the reports we support on this resource.
  */
  private $supported_reports;

  /**
  * @var An array of the dead properties held for this resource
  */
  private $dead_properties;

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
    $this->resourcetypes = null;
    $this->contenttype   = null;
    $this->privileges    = null;
    $this->dead_properties   = null;
    $this->supported_methods = null;
    $this->supported_reports = null;

    $this->_is_collection  = false;
    $this->_is_principal   = false;
    $this->_is_calendar    = false;
    $this->_is_addressbook = false;
    $this->_is_proxy       = false;
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
    $this->dav_name = $row->dav_name;
    $this->_is_collection = preg_match( '{/$}', $row->dav_name );

    if ( $this->_is_collection ) {
      $this->contenttype = 'httpd/unix-directory';
      $this->collection = (object) array();

      $this->_is_principal = preg_match( '{^/[^/]+/$}', $row->dav_name );
      if ( preg_match( '#^(/principals/[^/]+/[^/]+)/?$#', $row->dav_name, $matches) ) {
        $this->collection->dav_name = $matches[1].'/';
        $this->collection->type = 'principal_link';
        $this->_is_principal = true;
      }
    }
    else {
      $this->resource = (object) array();
    }

    dbg_error_log( 'DAVResource', ':FromRow: Named "%s" is%s a collection.', $row->dav_name, ($this->_is_collection?'':' not') );

    foreach( $row AS $k => $v ) {
      if ( $this->_is_collection )
        $this->collection->{$k} = $v;
      else
        $this->resource->{$k} = $v;
      switch ( $k ) {
        case 'created':
        case 'modified':
        case 'resourcetypes':
          $this->{$k} = $v;
          break;

        case 'dav_etag':
          $this->unique_tag = '"'.$v.'"';
          break;

      }
    }

    if ( $this->_is_collection ) {
      if ( !isset( $this->collection->type ) || $this->collection->type == 'collection' ) {
        if ( $this->_is_principal )
          $this->collection->type = 'principal';
        else if ( $row->is_calendar == 't' )
          $this->collection->type = 'calendar';
        else if ( $row->is_addressbook == 't' )
          $this->collection->type = 'addressbook';
        else if ( preg_match( '#^((/[^/]+/)\.(in|out)/)[^/]*$#', $this->dav_name, $matches ) )
          $this->collection->type = 'schedule-'. $matches[3]. 'box';
        else if ( $this->dav_name == '/' )
          $this->collection->type = 'root';
        else
          $this->collection->type = 'collection';
      }

      $this->_is_calendar     = ($this->collection->is_calendar == 't');
      $this->_is_addressbook  = ($this->collection->is_addressbook == 't');
      if ( $this->_is_principal && !isset($this->resourcetypes) ) {
        $this->resourcetypes = '<DAV::collection/><DAV::principal/>';
      }
      if ( isset($this->collection->dav_displayname) ) $this->collection->displayname = $this->collection->dav_displayname;
    }
    else {
      $this->resourcetypes = '';
      if ( isset($this->resource->caldav_data) ) {
        if ( substr($this->resource->caldav_data,0,15) == 'BEGIN:VCALENDAR' ) $this->contenttype = 'text/calendar';
        $this->resource->displayname = $this->resource->summary;
      }
    }
  }


  /**
  * Initialise from a path
  * @param object $inpath The path to populate the resource data from
  */
  function FromPath($inpath) {
    global $c;

    $this->dav_name = DeconstructURL($inpath);

    $this->FetchCollection();
    if ( $this->_is_collection ) {
      if ( $this->_is_principal ) $this->FetchPrincipal();
    }
    else {
      $this->FetchResource();
    }
    dbg_error_log( 'DAVResource', ':FromPath: Path "%s" is%s a collection%s.',
               $this->dav_name, ($this->_is_collection?' '.$this->resourcetypes:' not'), ($this->_is_principal?' and a principal':'') );
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
    dbg_error_log( 'DAVResource', ':FetchCollection: Looking for collection for "%s".', $this->dav_name );

    $this->collection = (object) array(
      'collection_id' => -1,
      'type' => 'nonexistent',
      'is_calendar' => false, 'is_principal' => false, 'is_addressbook' => false
    );

    $base_sql = 'SELECT collection.*, path_privs(:session_principal::int8, collection.dav_name,:scan_depth::int), ';
    $base_sql .= 'p.principal_id, p.type_id AS principal_type_id, ';
    $base_sql .= 'p.displayname AS principal_displayname, p.default_privileges AS principal_default_privileges, ';
    $base_sql .= 'time_zone.tz_spec ';
    $base_sql .= 'FROM collection LEFT JOIN principal p USING (user_no) ';
    $base_sql .= 'LEFT JOIN time_zone ON (collection.timezone=time_zone.tz_id) ';
    $base_sql .= 'WHERE ';
    $sql = $base_sql .'collection.dav_name = :raw_path ';
    $params = array( ':raw_path' => $this->dav_name, ':session_principal' => $session->principal_id, ':scan_depth' => $c->permission_scan_depth );
    if ( !preg_match( '#/$#', $this->dav_name ) ) {
      $sql .= ' OR collection.dav_name = :up_to_slash OR collection.dav_name = :plus_slash ';
      $params[':up_to_slash'] = preg_replace( '#[^/]*$#', '', $this->dav_name);
      $params[':plus_slash']  = $this->dav_name.'/';
    }
    $sql .= 'ORDER BY LENGTH(collection.dav_name) DESC LIMIT 1';
    $qry = new AwlQuery( $sql, $params );
    if ( $qry->Exec('DAVResource') && $qry->rows() == 1 && ($row = $qry->Fetch()) ) {
      $this->collection = $row;
      $this->collection->exists = true;
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
        $this->collection->exists = true;
      }
    }
    else if ( preg_match( '#^(/([^/]+)/calendar-proxy-(read|write))/?[^/]*$#', $this->dav_name, $matches ) ) {
      $this->collection->type = 'proxy';
      $this->_is_proxy_request = true;
      $this->proxy_type = $matches[3];
      $this->collection->dav_name = $matches[1].'/';
      $this->collection->dav_displayname = sprintf( '%s proxy %s', $matches[2], $matches[3] );
      $this->collection->exists = true;
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
      $this->collection->exists = true;
      $this->collection->displayname = $c->system_name;
      $this->collection->default_privileges = (1 | 16 | 32);
    }
    else {
      dbg_error_log( 'DAVResource', 'No collection for path "%s".', $this->dav_name );
      $this->collection->exists = false;
      $this->collection->dav_name = preg_replace('{/[^/]*$}', '/', $this->dav_name);
    }

    dbg_error_log( 'DAVResource', ':FetchCollection: Found collection named "%s" of type "%s".', $this->collection->dav_name, $this->collection->type );

    $this->_is_collection = ( $this->collection->dav_name == $this->dav_name || $this->collection->dav_name == $this->dav_name.'/' );
    if ( $this->_is_collection ) {
      $this->dav_name = $this->collection->dav_name;
      $this->_is_calendar    = ($this->collection->type == 'calendar');
      $this->_is_addressbook = ($this->collection->type == 'addressbook');
      $this->contenttype = 'httpd/unix-directory';
      if ( !isset($this->exists) && isset($this->collection->exists) ) {
        // If this seems peculiar it's because we only set it to false above...
        $this->exists = $this->collection->exists;
      }
      if ( $this->exists ) {
        if ( isset($this->collection->dav_etag) ) $this->unique_tag = '"'.$this->collection->dav_etag.'"';
        if ( isset($this->collection->created) )  $this->created = $this->collection->created;
        if ( isset($this->collection->modified) ) $this->modified = $this->collection->modified;
        if ( isset($this->collection->dav_displayname) ) $this->collection->displayname = $this->collection->dav_displayname;
      }
      if ( isset($this->collection->resourcetypes) )
        $this->resourcetypes = $this->collection->resourcetypes;
      else {
        $this->resourcetypes = '<DAV::collection/>';
        if ( $this->_is_principal ) $this->resourcetypes .= '<DAV::principal/>';
      }
    }
  }


  /**
  * Find the principal associated with this resource.
  */
  function FetchPrincipal() {
    global $c, $session;
    $this->principal = new CalDAVPrincipal( array( "path" => $this->dav_name ) );
    if ( $this->_is_principal && $this->principal->Exists() ) {
//      $this->contenttype = 'httpd/unix-directory';
      $this->exists = true;
      $this->unique_tag = $this->principal->dav_etag;
      $this->created = $this->principal->created;
      $this->modified = $this->principal->modified;
      $this->resourcetypes = '<DAV::collection/><DAV::principal/>';
    }
  }


  /**
  * Retrieve the actual resource.
  */
  function FetchResource() {
    global $c, $session;

    if ( isset($this->exists) ) return;   // True or false, we've got what we can already
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
      if ( substr($this->resource->caldav_data,0,15) == 'BEGIN:VCALENDAR' ) {
        $this->contenttype = 'text/calendar';
      }
      $this->resourcetypes = '';
    }
    else {
      $this->exists = false;
    }
  }


  /**
  * Fetch any dead properties for this URL
  */
  function FetchDeadProperties() {
    if ( isset($this->dead_properties) ) return;

    $this->dead_properties = array();
    $qry = new AwlQuery('SELECT property_name, property_value FROM property WHERE dav_name= :dav_name', array(':dav_name' => $this->dav_name) );
    if ( $qry->Exec('DAVResource') ) {
      while ( $property = $qry->Fetch() ) {
        $this->dead_properties[$property->property_name] = $property->property_value;
      }
    }
  }


  /**
  * Build permissions for this URL
  */
  function FetchPrivileges() {
    global $session;

    if ( $this->dav_name == '/' || $this->dav_name == '' ) {
      $this->privileges = (1 | 16 | 32); // read + read-acl + read-current-user-privilege-set
      dbg_error_log( 'DAVResource', 'Read permissions for user accessing /' );
      return;
    }

    if ( $session->AllowedTo('Admin') ) {
      $this->privileges = privilege_to_bits('all');
      dbg_error_log( 'DAVResource', 'Full permissions for an administrator.' );
      return;
    }

    if ( $this->IsPrincipal() ) {
      if ( !isset($this->principal) ) $this->FetchPrincipal();
      $this->privileges = $this->principal->Privileges();
      dbg_error_log( 'DAVResource', 'Privileges of "%s" for user accessing principal "%s"', $this->privileges, $this->principal->username );
      return;
    }


    $this->privileges = 0;
    if ( !isset($this->collection->path_privs) ) {
      $parent_path = preg_replace('{/[^/]*/$}', '/', $this->collection->dav_name );
      dbg_error_log( 'DAVResource', 'Checking privileges of "%s" - parent of "%s"', $parent_path, $this->collection->dav_name );
      $parent = new DAVResource( $parent_path );

      $this->collection->path_privs = $parent->Privileges();
      $this->collection->user_no = $parent->GetProperty('user_no');
      $this->collection->principal_id = $parent->GetProperty('principal_id');
    }

    $this->privileges = $this->collection->path_privs;
    if ( is_string($this->privileges) ) $this->privileges = bindec( $this->privileges );
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
//    dbg_error_log( 'DAVResource', 'Testing privileges of "%s" (%s) against allowed "%s" => "%s" (%s)', $do_what, decbin($test_bits), decbin($this->privileges), ($this->privileges & $test_bits), decbin($this->privileges & $test_bits) );
    return ($this->privileges & $test_bits) > 0;
  }


  /**
  * Check if we have the needed privilege or send an error response.
  *
  * @param string $privilege The name of the needed privilege.
  */
  function NeedPrivilege( $privilege ) {
    global $request;

    if ( $this->HavePrivilegeTo($privilege) ) return;

    $request->NeedPrivilege( $privilege, $this->dav_name );
    exit(0);  // Unecessary, but might clarify things
  }


  /**
  * Returns the array of privilege names converted into XMLElements
  */
  function BuildPrivileges( $privilege_names=null, &$xmldoc=null ) {
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

    $this->supported_methods = array(
      'OPTIONS' => '',
      'PROPFIND' => '',
      'REPORT' => '',
      'DELETE' => '',
      'LOCK' => '',
      'UNLOCK' => '',
      'MOVE' => ''
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
  * Returns the array of supported methods converted into XMLElements
  */
  function BuildSupportedMethods( ) {
    if ( !isset($this->supported_methods) ) $this->FetchSupportedMethods();
    $methods = array();
    foreach( $this->supported_methods AS $k => $v ) {
//      dbg_error_log( 'DAVResource', ':BuildSupportedMethods: Adding method "%s" which is "%s".', $k, $v );
      $methods[] = new XMLElement( 'supported-method', null, array('name' => $k) );
    }
    return $methods;
  }


  /**
  * Returns the array of supported reports
  */
  function FetchSupportedReports( ) {
    if ( isset($this->supported_reports) ) return $this->supported_reports;

    $this->supported_reports = array(
      'DAV::principal-property-search' => '',
      'DAV::expand-property' => '',
      'DAV::sync-collection' => ''
    );

    if ( !isset($this->collection) ) $this->FetchCollection();

    if ( $this->collection->is_calendar ) {
      $this->supported_reports = array_merge(
        $this->supported_reports,
        array(
          'urn:ietf:params:xml:ns:caldav:calendar-query' => '',
          'urn:ietf:params:xml:ns:caldav:calendar-multiget' => '',
          'urn:ietf:params:xml:ns:caldav:free-busy-query' => ''
        )
      );
    }
    return $this->supported_reports;
  }


  /**
  * Returns the array of supported reports converted into XMLElements
  */
  function BuildSupportedReports( &$reply ) {
    if ( !isset($this->supported_reports) ) $this->FetchSupportedReports();
    $reports = array();
    foreach( $this->supported_reports AS $k => $v ) {
      dbg_error_log( 'DAVResource', ':BuildSupportedReports: Adding supported report "%s" which is "%s".', $k, $v );
      $report = new XMLElement('report');
      $reply->NSElement($report, $k );
      $reports[] = new XMLElement('supported-report', $report );
    }
    return $reports;
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
    return $this->_is_collection && $this->_is_principal;
  }


  /**
  * Checks whether this resource is a calendar
  */
  function IsCalendar() {
    return $this->_is_collection && $this->_is_calendar;
  }


  /**
  * Checks whether this resource is a calendar
  * @param string $type The type of scheduling collection, 'read', 'write' or 'any'
  */
  function IsSchedulingCollection( $type = 'any' ) {
    if ( $this->_is_collection && preg_match( '{schedule-(inbox|outbox)}', $this->collection->type, $matches ) ) {
      return ($type == 'any' || $type == $matches[1]);
    }
    return false;
  }


  /**
  * Checks whether this resource is an addressbook
  */
  function IsAddressbook() {
    return $this->_is_collection && $this->_is_addressbook;
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
      else if ( ! $this->IsCollection() ) {
        if ( !isset($this->resource) ) $this->FetchResource();
      }
    }
//    dbg_error_log('DAVResource',' Checking whether "%s" exists.  It would appear %s.', $this->dav_name, ($this->exists ? 'so' : 'not') );
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
    if ( $this->IsPrincipal() && !isset($this->principal) ) $this->FetchPrincipal();
    else if ( !$this->_is_collection && !isset($this->resource) ) $this->FetchResource();

    if ( $this->exists !== true || !isset($this->unique_tag) ) $this->unique_tag = '';

    return $this->unique_tag;
  }


  /**
  * Checks whether the target collection is publicly_readable
  */
  function IsPublic() {
    return ( isset($this->collection->publicly_readable) && $this->collection->publicly_readable == 't' );
  }


  /**
  * Return the type of whatever contains this resource, or would if it existed.
  */
  function ContainerType() {
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
  * Return ACL settings
  */
  function GetACL( &$xmldoc ) {
    global $c, $session;

    if ( !isset($this->principal) ) $this->FetchPrincipal();
    $default_privs = $this->principal->default_privileges;
    if ( isset($this->collection->default_privileges) ) $default_privs = $this->collection->default_privileges;

    $acl = array();
    $privilege_names = bits_to_privilege($default_privs);
    $privileges = array();
    foreach( $privilege_names AS $k ) {
      $privilege = new XMLElement('privilege');
      if ( isset($xmldoc) )
        $xmldoc->NSElement($privilege,$k);
      else
        $privilege->NewElement($k);
      $privileges[] = $privilege;
    }
    $acl[] = new XMLElement('ace', array(
                new XMLElement('principal', new XMLElement('authenticated')),
                new XMLElement('grant', $privileges ) )
              );

    $qry = new AwlQuery('SELECT dav_principal.dav_name, grants.* FROM grants JOIN dav_principal ON (to_principal=principal_id) WHERE by_collection = :collection_id OR by_principal = :principal_id ORDER BY by_collection',
                                array( ':collection_id' => $this->collection->collection_id, ':principal_id' => $this->principal->principal_id ) );
    if ( $qry->Exec('DAVResource') && $qry->rows() > 0 ) {
      $by_collection = null;
      while( $grant = $qry->Fetch() ) {
        if ( !isset($by_collection) ) $by_collection = isset($grant->by_collection);
        if ( $by_collection &&  !isset($grant->by_collection) ) break;

        $privilege_names = bits_to_privilege($grant->privileges);
        $privileges = array();
        foreach( $privilege_names AS $k ) {
          $privilege = new XMLElement('privilege');
          if ( isset($xmldoc) )
            $xmldoc->NSElement($privilege,$k);
          else
            $privilege->NewElement($k);
          $privileges[] = $privilege;
        }
        $acl[] = new XMLElement('ace', array(
                        new XMLElement('principal', $xmldoc->href(ConstructURL($grant->dav_name))),
                        new XMLElement('grant', $privileges ) )
                  );
      }
    }
    return $acl;

  }


  /**
  * Return general server-related properties, in plain form
  */
  function GetProperty( $name ) {
    global $c, $session;

//    dbg_error_log( 'DAVResource', ':GetProperty: Fetching "%s".', $name );
    $value = null;

    switch( $name ) {
      case 'collection_id':
        return $this->collection->collection_id;
        break;

      case 'resourcetype':
        if ( isset($this->resourcetypes) ) {
          $this->resourcetypes = preg_replace('{^<(.*)/>$}', '$1', $this->resourcetypes);
          $type_list = explode('/><', $this->resourcetypes);
          return $type_list;
        }

      default:
        if ( $this->_is_principal ) {
          if ( !isset($this->principal) ) $this->FetchPrincipal();
          if ( isset($this->principal->{$name}) ) return $this->principal->{$name};
          if ( isset($this->collection->{$name}) ) return $this->collection->{$name};
        }
        else if ( $this->_is_collection ) {
          if ( isset($this->collection->{$name}) ) return $this->collection->{$name};
          if ( isset($this->principal->{$name}) ) return $this->principal->{$name};
        }
        else {
          if ( !isset($this->resource) ) $this->FetchResource();
          if ( isset($this->resource->{$name}) ) return $this->resource->{$name};
          if ( !isset($this->principal) ) $this->FetchPrincipal();
          if ( isset($this->principal->{$name}) ) return $this->principal->{$name};
          if ( isset($this->collection->{$name}) ) return $this->collection->{$name};
        }
//        dbg_error_log( 'DAVResource', ':GetProperty: Failed to find property "%s" on "%s".', $name, $this->dav_name );
    }

    return $value;
  }


  /**
  * Return an array which is an expansion of the DAV::allprop
  */
  function DAV_AllProperties() {
    if ( isset($this->dead_properties) ) $this->FetchDeadProperties();
    $allprop = array_merge( (isset($this->dead_properties)?$this->dead_properties:array()), array(
      'DAV::getcontenttype', 'DAV::resourcetype', 'DAV::getcontentlength', 'DAV::displayname', 'DAV::getlastmodified',
      'DAV::creationdate', 'DAV::getetag', 'DAV::getcontentlanguage', 'DAV::supportedlock', 'DAV::lockdiscovery',
      'DAV::owner', 'DAV::principal-URL', 'DAV::current-user-principal',
      'urn:ietf:params:xml:ns:carddav:max-resource-size', 'urn:ietf:params:xml:ns:carddav:supported-address-data',
      'urn:ietf:params:xml:ns:carddav:addressbook-description', 'urn:ietf:params:xml:ns:carddav:addressbook-home-set'
    ) );

    return $allprop;
  }


  /**
  * Return general server-related properties for this URL
  */
  function ResourceProperty( $tag, $prop, &$reply, &$denied ) {
    global $c, $session, $request;

//    dbg_error_log( 'DAVResource', 'Processing "%s" on "%s".', $tag, $this->dav_name );

    if ( $reply === null ) $reply = $GLOBALS['reply'];

    switch( $tag ) {
      case 'DAV::allprop':
        $property_list = $this->DAV_AllProperties();
        $discarded = array();
        foreach( $property_list AS $k => $v ) {
          $this->ResourceProperty($v, $prop, $reply, $discarded);
        }
        break;

      case 'DAV::href':
        $prop->NewElement('href', ConstructURL($this->dav_name) );
        break;

      case 'DAV::getcontenttype':
        if ( !isset($this->contenttype) && !$this->_is_collection && !isset($this->resource) ) $this->FetchResource();
        $prop->NewElement('getcontenttype', $this->contenttype );
        break;

      case 'DAV::resourcetype':
        $resourcetypes = $prop->NewElement('resourcetype' );
        $type_list = $this->GetProperty('resourcetype');
        if ( !is_array($type_list) ) return true;
//        dbg_error_log( 'DAVResource', ':ResourceProperty: "%s" are "%s".', $tag, implode(', ',$type_list) );
        foreach( $type_list AS $k => $v ) {
          if ( $v == '' ) continue;
          $reply->NSElement( $resourcetypes, $v );
        }
        break;

      case 'DAV::getlastmodified':
        /** peculiarly, it seems that getlastmodified is HTTP Date format! */
        $reply->NSElement($prop, $tag, ISODateToHTTPDate($this->GetProperty('modified')) );
        break;

      case 'DAV::creationdate':
        /** bizarrely, it seems that creationdate is ISO8601 format */
        $reply->NSElement($prop, $tag, DateToISODate($this->GetProperty('created')) );
        break;

      case 'DAV::getcontentlength':
        if ( $this->_is_collection ) return false;
        if ( !isset($this->resource) ) $this->FetchResource();
        $reply->NSElement($prop, $tag, strlen($this->resource->caldav_data) );
        break;

      case 'DAV::getcontentlanguage':
        $locale = (isset($c->current_locale) ? $c->current_locale : '');
        if ( isset($this->locale) && $this->locale != '' ) $locale = $this->locale;
        $reply->NSElement($prop, $tag, $locale );
        break;

      case 'DAV::acl-restrictions':
        $reply->NSElement($prop, $tag, array( new XMLElement('grant-only'), new XMLElement('no-invert') ) );
        break;

      case 'DAV::inherited-acl-set':
        $inherited_acls = array();
        if ( ! $this->_is_collection ) {
          $inherited_acls[] = $reply->href(ConstructURL($this->collection->dav_name));
        }
        $reply->NSElement($prop, $tag, $inherited_acls );
        break;

      case 'DAV::owner':
        // After a careful reading of RFC3744 we see that this must be the principal-URL of the owner
        $reply->DAVElement( $prop, 'owner', $reply->href( $this->principal_url() ) );
        break;

      // Empty tag responses.
      case 'DAV::alternate-URI-set':
        $reply->NSElement($prop, $tag );
        break;

      case 'DAV::getetag':
        if ( $this->_is_collection ) return false;
        $reply->NSElement($prop, $tag, $this->unique_tag() );
        break;

      case 'http://calendarserver.org/ns/:getctag':
        if ( ! $this->_is_collection ) return false;
        $reply->NSElement($prop, $tag, $this->unique_tag() );
        break;

      case 'http://calendarserver.org/ns/:calendar-proxy-read-for':
        $proxy_type = 'read';
      case 'http://calendarserver.org/ns/:calendar-proxy-write-for':
        if ( !isset($proxy_type) ) $proxy_type = 'write';
        $reply->CalendarserverElement($prop, 'calendar-proxy-'.$proxy_type.'-for', $reply->href( $this->principal->ProxyFor($proxy_type) ) );
        break;

      case 'DAV::current-user-privilege-set':
        if ( $this->HavePrivilegeTo('DAV::read-current-user-privilege-set') ) {
          $reply->NSElement($prop, $tag, $this->BuildPrivileges() );
        }
        else {
          $denied[] = $tag;
        }
        break;

      case 'urn:ietf:params:xml:ns:caldav:supported-calendar-data':
        if ( ! $this->IsCalendar() && ! $this->IsSchedulingCollection() ) return false;
        $reply->NSElement($prop, $tag, 'text/calendar' );
        break;

      case 'urn:ietf:params:xml:ns:caldav:supported-calendar-component-set':
        if ( ! $this->_is_collection ) return false;
        if ( $this->IsCalendar() )
          $set_of_components = array( 'VEVENT', 'VTODO', 'VJOURNAL', 'VTIMEZONE', 'VFREEBUSY' );
        else if ( $this->IsSchedulingCollection() )
          $set_of_components = array( 'VEVENT', 'VTODO', 'VFREEBUSY' );
        else return false;
        $components = array();
        foreach( $set_of_components AS $v ) {
          $components[] = $reply->NewXMLElement( 'comp', '', array('name' => $v), 'urn:ietf:params:xml:ns:caldav');
        }
        $reply->CalDAVElement($prop, 'supported-calendar-component-set', $components );
        break;

      case 'DAV::supported-method-set':
        $prop->NewElement('supported-method-set', $this->BuildSupportedMethods() );
        break;

      case 'DAV::supported-report-set':
        $prop->NewElement('supported-report-set', $this->BuildSupportedReports( $reply ) );
        break;

      case 'DAV::supportedlock':
        $prop->NewElement('supportedlock',
          new XMLElement( 'lockentry',
            array(
              new XMLElement('lockscope', new XMLElement('exclusive')),
              new XMLElement('locktype',  new XMLElement('write')),
            )
          )
        );
        break;

      case 'DAV::supported-privilege-set':
        $prop->NewElement('supported-privilege-set', $request->BuildSupportedPrivileges($reply) );
        break;

      case 'DAV::current-user-principal':
        $prop->NewElement('current-user-principal', $reply->href( $request->principal->principal_url ) );
        break;

      case 'SOME-DENIED-PROPERTY':  /** @todo indicating the style for future expansion */
        $denied[] = $reply->Tag($tag);
        break;

      case 'urn:ietf:params:xml:ns:caldav:calendar-timezone':
        if ( ! $this->_is_collection ) return false;
        if ( !isset($this->collection->tz_spec) || $this->collection->tz_spec == '' ) return false;

        $cal = new iCalComponent();
        $cal->VCalendar();
        $cal->AddComponent( new iCalComponent($this->collection->tz_spec) );
        $reply->NSElement($prop, $tag, $cal->Render() );
        break;

      case 'urn:ietf:params:xml:ns:caldav:calendar-data':
        if ( $this->_is_collection ) return false;
        if ( !isset($this->resource) ) $this->FetchResource();
        $reply->NSElement($prop, $tag, $this->resource->caldav_data );
        break;

      case 'urn:ietf:params:xml:ns:carddav:max-resource-size':
        if ( ! $this->_is_collection || !$this->_is_addressbook ) return false;
        $reply->NSElement($prop, $tag, 65500 );
        break;

      case 'urn:ietf:params:xml:ns:carddav:supported-address-data':
        if ( ! $this->_is_collection || !$this->_is_addressbook ) return false;
        $address_data = $reply->NewXMLElement( 'address-data', false,
                      array( 'content-type' => 'text/vcard', 'version' => '3.0'), 'urn:ietf:params:xml:ns:carddav');
        $reply->NSElement($prop, $tag, $address_data );
        break;

      case 'DAV::acl':
        if ( $this->HavePrivilegeTo('DAV::read-acl') ) {
          $reply->NSElement($prop, $tag, $this->GetACL( $reply ) );
        }
        else {
          $denied[] = $tag;
        }
        break;

      default:
        $property_value = $this->GetProperty(preg_replace('{^.*:}', '', $tag));
        if ( isset($property_value) ) {
          $reply->NSElement($prop, $tag, $property_value );
        }
        else {
          if ( !isset($this->dead_properties) ) $this->FetchDeadProperties();
          if ( isset($this->dead_properties[$tag]) ) {
            $reply->NSElement($prop, $tag, $this->dead_properties[$tag] );
          }
          else {
//            dbg_error_log( 'DAVResource', 'Request for unsupported property "%s" of path "%s".', $tag, $this->dav_name );
            return false;
          }
        }
    }

    return true;
  }


  /**
  * Construct XML propstat fragment for this resource
  *
  * @param array of string $properties The requested properties for this resource
  *
  * @return string An XML fragment with the requested properties for this resource
  */
  function GetPropStat( $properties, &$reply, $props_only = false ) {
    global $request;

    dbg_error_log('DAVResource',':GetPropStat: propstat for href "%s"', $this->dav_name );

    $prop = new XMLElement('prop');
    $denied = array();
    $not_found = array();
    foreach( $properties AS $k => $tag ) {
      if ( is_object($tag) ) {
        dbg_error_log( 'DAVResource', ':GetPropStat: "$properties" should be an array of text. Assuming this object is an XMLElement!.' );
        $tag = $tag->GetTag();
      }
      $found = $this->ResourceProperty($tag, $prop, $reply, $denied );
      if ( !$found ) {
       if ( !isset($this->principal) ) $this->FetchPrincipal();
        $found = $this->principal->PrincipalProperty( $tag, $prop, $reply, $denied );
      }
      if ( ! $found && ! $request->ServerProperty($tag, $prop, $reply) ) {
//        dbg_error_log( 'DAVResource', 'Request for unsupported property "%s" of resource "%s".', $tag, $this->dav_name );
        $not_found[] = $reply->Tag($tag);
      }
    }
    if ( $props_only ) return $prop;

    $status = new XMLElement('status', 'HTTP/1.1 200 OK' );

    $elements = array( new XMLElement( 'propstat', array($prop,$status) ) );

    if ( count($denied) > 0 ) {
      $status = new XMLElement('status', 'HTTP/1.1 403 Forbidden' );
      $noprop = new XMLElement('prop');
      foreach( $denied AS $k => $v ) {
        $reply->NSElement($noprop, $v);
      }
      $elements[] = new XMLElement( 'propstat', array( $noprop, $status) );
    }

    if ( count($not_found) > 0 ) {
      $status = new XMLElement('status', 'HTTP/1.1 404 Not Found' );
      $noprop = new XMLElement('prop');
      foreach( $not_found AS $k => $v ) {
        $noprop->NewElement($v);
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

    dbg_error_log('DAVResource',':RenderAsXML: Resource "%s"', $this->dav_name );

    if ( !$this->Exists() ) return null;

    if ( $props_only ) {
      dbg_error_log('LOG WARNING','DAVResource::RenderAsXML Called misguidedly - should be call to DAVResource::GetPropStat' );
      return $this->GetPropStat( $properties, $reply, true );
    }

    $elements = $this->GetPropStat( $properties, $reply );
    array_unshift( $elements, $reply->href(ConstructURL($this->dav_name)));

    $response = new XMLElement( 'response', $elements );

    return $response;
  }

}
