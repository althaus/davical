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
    $priv = trim(strtolower(preg_replace( '/^.*:/', '', $priv)));
    switch( $priv ) {
      case 'read'                            : $out_priv &=  4609;  break;  // 1 + 512 + 4096
      case 'write'                           : $out_priv &=   198;  break;  // 2 + 4 + 64 + 128
      case 'write-properties'                : $out_priv &=     2;  break;
      case 'write-content'                   : $out_priv &=     4;  break;
      case 'unlock'                          : $out_priv &=     8;  break;
      case 'read-acl'                        : $out_priv &=    16;  break;
      case 'read-current-user-privilege-set' : $out_priv &=    32;  break;
      case 'bind'                            : $out_priv &=    64;  break;
      case 'unbind'                          : $out_priv &=   128;  break;
      case 'write-acl'                       : $out_priv &=   256;  break;
      case 'read-free-busy'                  : $out_priv &=  4608;  break; //  512 + 4096
      case 'schedule-deliver'                : $out_priv &=  7168;  break; // 1024 + 2048 + 4096
      case 'schedule-deliver-invite'         : $out_priv &=  1024;  break;
      case 'schedule-deliver-reply'          : $out_priv &=  2048;  break;
      case 'schedule-query-freebusy'         : $out_priv &=  4096;  break;
      case 'schedule-send'                   : $out_priv &= 57344;  break; // 8192 + 16384 + 32768
      case 'schedule-send-invite'            : $out_priv &=  8192;  break;
      case 'schedule-send-reply'             : $out_priv &= 16384;  break;
      case 'schedule-send-freebusy'          : $out_priv &= 32768;  break;
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
    if ( (in_bits & 198) == 198 THEN $out_priv[] = 'DAV::write';
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

  if (in_bits & 57344) != 0 ) {
    if (in_bits & 57344) == 57344 ) $out_priv[] = 'urn:ietf:params:xml:ns:caldav:schedule-send';
    if (in_bits &  8192) != 0 ) $out_priv[] = 'urn:ietf:params:xml:ns:caldav:schedule-send-invite';
    if (in_bits & 16384) != 0 ) $out_priv[] = 'urn:ietf:params:xml:ns:caldav:schedule-send-reply';
    if (in_bits & 32768) != 0 ) $out_priv[] = 'urn:ietf:params:xml:ns:caldav:schedule-send-freebusy';
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
  * @var The URL of the resource
  */
  protected $href;

  /**
  * @var The principal URL of the owner of the resource
  */
  protected $principal_url;

  /**
  * @var The unique etag associated with the current version of the resource
  */
  protected $unique_tag;

  /**
  * @var The actual resource content
  */
  protected $content;

  /**
  * @var The type of the resource, possibly multiple
  */
  protected $resourcetype;

  /**
  * @var The type of the content
  */
  protected $contenttype;

  /**
  * @var True if this resource is a collection of any kind
  */
  private $_is_collection;

  /**
  * @var An object which is the collection record for this resource, or for it's container
  */
  private $collection;

  /**
  * @var A bit mask representing the current user's privileges towards this DAVResource
  */
  private $privileges;

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
  * Constructor
  * @param mixed $parameters If null, an empty Resourced is created.
  *     If it is an object then it is expected to be a record that was
  *     read elsewhere.
  */
  function __construct( $parameters = null ) {
    $this->_is_principal = false;
    $this->_is_collection = false;
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

    foreach( $row AS $k => $v ) {
      dbg_error_log( 'resource', 'Processing resource property "%s" has "%s".', $row->dav_name, $k );
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

    $this->path = rawurldecode($inpath);

    /** Allow a path like .../username/calendar.ics to translate into the calendar URL */
    if ( preg_match( '#^(/[^/]+/[^/]+).ics$#', $this->path, $matches ) ) {
      $this->path = $matches[1]. '/';
    }

    /** strip doubled slashes */
    if ( strstr($this->path,'//') ) $this->path = preg_replace( '#//+#', '/', $this->path);

    // $this->FetchCollection(); // Do this lazily when something refers to the data
  }


  /**
  * Find the collection associated with this resource.
  */
  function FetchCollection() {
    globals $c, $session;
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
    $params = array( ':raw_path' => $this->path, ':session_principal' => $session->principal_id );
    if ( !preg_match( '#/$#', $this->path ) ) {
      $sql .= ' OR dav_name = :up_to_slash OR dav_name = :plus_slash'
      $params[':up_to_slash'] = preg_replace( '#[^/]*$#', '', $this->path);
      $params[':plus_slash']  = $this->path.'/';
    }
    $sql .= 'ORDER BY LENGTH(dav_name) DESC LIMIT 1';
    $qry = new AwlQuery( $sql, $params );
    if ( $qry->Exec('DAVResource') && $qry->rows == 1 && ($row = $qry->Fetch()) ) {
      $this->collection = $row;
      if ( $row->is_calendar == 't' ) $this->collection->type = 'calendar';
      else if ( $row->is_addressbook == 't' ) $this->collection->type = 'addressbook';
      else $this->collection->type = 'collection';
    }
    else if ( preg_match( '#^((/[^/]+/)\.(in|out)/)[^/]*$#', $this->path, $matches ) ) {
      // The request is for a scheduling inbox or outbox (or something inside one) and we should auto-create it
      $params = array( ':user_no' => $session->user_no, ':parent_container' => $matches[2], ':dav_name' => $matches[1] );
      $params['displayname'] = $session->fullname . ($matches[3] == 'in' ? ' Inbox' : ' Outbox');
      $this->collection_type = 'schedule-'. $matches[3]. 'box';
      $params['resourcetypes'] = sprintf('<DAV::collection/><urn:ietf:params:xml:ns:caldav:%s/>', $this->collection_type );
      $sql = <<<EOSQL
INSERT INTO collection ( user_no, parent_container, dav_name, dav_displayname, is_calendar, created, modified, dav_etag, resourcetypes )
    VALUES( :user_no, :parent_container, :dav_name, :dav_displayname, FALSE, current_timestamp, current_timestamp, '1', :resourcetypes )
EOSQL;
      $qry = new AwlQuery( $sql, $params );
      $qry->Exec('DAVResource');
      dbg_error_log( 'DAVResource', 'Created new collection as "$displayname".' );

      $qry = new AwlQuery( $base_sql . 'user_no = :user_no AND dav_name = :dav_name', $params );
      if ( $qry->Exec('DAVResource') && $qry->rows == 1 && ($row = $qry->Fetch()) ) {
        $this->collection = $row;
        $this->collection->type = $this->collection_type;
      }
    }
    else if ( preg_match( '#^((/[^/]+/)calendar-proxy-(read|write))/?[^/]*$#', $this->path, $matches ) ) {
      $this->collection->type = 'proxy';
      $this->_is_proxy_request = true;
      $this->proxy_type = $matches[3];
      $this->collection->dav_name = $matches[1].'/';
    }
    else if ( $this->options['allow_by_email'] && preg_match( '#^/(\S+@\S+[.]\S+)/?$#', $this->path, $matches) ) {
      /** @TODO: perhaps we should deprecate this in favour of scheduling extensions */
      $this->collection->type = 'principal_email';
      $this->collection->dav_name = $matches[1].'/';
      $this->_is_principal = true;
    }
    else if ( preg_match( '#^(/[^/]+)/?$#', $this->path, $matches) ) {
      $this->collection->dav_name = $matches[1].'/';
      $this->collection->type = 'principal';
      $this->_is_principal = true;
    }
    else if ( preg_match( '#^(/principals/[^/]+/[^/]+)/?$#', $this->path, $matches) ) {
      $this->collection->dav_name = $matches[1].'/';
      $this->collection->type = 'principal_link';
      $this->_is_principal = true;
    }
    else if ( $this->path == '/' ) {
      $this->collection->dav_name = '/';
      $this->collection->type = 'root';
    }

    $this->_is_collection = ( $this->collection->dav_name == $this->path || $this->collection->dav_name == $this->path.'/' );
    if ( $this->_is_collection ) {
      $this->_is_calendar    = $this->collection->is_calendar;
      $this->_is_addressbook = $this->collection->is_addressbook;
    }
  }


  /**
  * Build permissions for this URL
  */
  function FetchPrivileges() {
    global $session;

    if ( $this->path == '/' || $this->path == '' ) {
      $this->privileges = 1; // read
      dbg_error_log( 'DAVResource', 'Read permissions for user accessing /' );
      return;
    }

    if ( $session->AllowedTo('Admin') || $session->user_no == $this->user_no ) {
      $this->privileges = privilege_to_bits('all');
      dbg_error_log( 'DAVResource', 'Full permissions for %s', ( $session->user_no == $this->user_no ? 'user accessing their own hierarchy' : 'an administrator') );
      return;
    }

    $this->privileges = 0;
    if ( !isset($this->collection) ) $this->FetchCollection();

    $this->privileges = $this->collection->path_privileges;
  }


  /**
  * Is the user has the privileges to do what is requested.
  */
  function HavePrivilegeTo( $do_what ) {
    if ( !isset($this->privileges) ) $this->FetchPrivileges();
    $test_bits = privilege_to_bits( $do_what );
    return ($this->privileges & $test_bits) > 0;
  }


  /**
  * Returns the array of privilege names converted into XMLElements
  */
  function RenderPrivileges( $privilege_names=null, $xmldoc=null ) {
    if ( $privilege_names == null ) {
      if ( !isset($this->privileges) ) $this->FetchPrivileges();
      $privilege_names = bits_to_privilege($this->privileges);
    }
    if ( !isset($xmldoc) && isset($GLOBALS['reply']) ) $xmldoc = $GLOBALS['reply'];
    $privileges = array();
    foreach( $privilege_names AS $k ) {
      dbg_error_log( 'DAVResource', 'Adding privilege "%s".', $k );
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
      $qry = new AwlQuery($sql, array( ':this_path' => $this->path, ':match_end' => ($depth == DEPTH_INFINITY ? '' : '$') ) );
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
      if ( $lock_row->depth == DEPTH_INFINITY || $lock_row->dav_name == $this->path ) {
        return $lock_token;
      }
    }

    return false;  // Nothing matched
  }


  /**
  * Checks whether the target collection is publicly_readable
  */
  function IsPublic() {
    if ( isset($this->collection) && isset($this->collection->publicly_readable) && $this->collection->publicly_readable == 't' ) {
      return true;
    }
    return false;
  }


  /**
  * Return general server-related properties for this URL
  */
  function ResourceProperty( $tag, $prop, $reply = null ) {
    global $c, $session;

    if ( $reply === null ) $reply = $GLOBALS['reply'];

    dbg_error_log( 'resource', 'Processing "%s" on "%s".', $tag, $this->dav_name );

    switch( $tag ) {
      case 'DAV::href':
        $prop->NewElement('href', ConstructURL($this->dav_name) );
        break;

      case 'DAV::getcontenttype':
        $prop->NewElement('getcontenttype', $this->contenttype );
        break;

      case 'DAV::resourcetype':
        $prop->NewElement('resourcetype', $this->resourcetype );
        break;

      case 'DAV::displayname':
        $prop->NewElement('displayname', $this->displayname );
        break;

//      case 'DAV::getlastmodified':
//        $prop->NewElement('getlastmodified', $this->modified );
//        break;

//      case 'DAV::creationdate':
//        $prop->NewElement('creationdate', $this->created );
//        break;

      case 'DAV::getcontentlanguage':
        $locale = (isset($c->current_locale) ? $c->current_locale : '');
        if ( isset($this->locale) && $this->locale != '' ) $locale = $this->locale;
        $prop->NewElement('getcontentlanguage', $locale );
        break;

//      case 'DAV::owner':
//        // After a careful reading of RFC3744 we see that this must be the principal-URL of the owner
//        $reply->DAVElement( $prop, 'owner', $reply->href( $this->principal_url) ) );
//        break;

      // Empty tag responses.
      case 'DAV::alternate-URI-set':
      case 'DAV::getcontentlength':
        $prop->NewElement( $reply->Tag($tag));
        break;

      case 'DAV::getetag':
        if ( $this->_is_collection ) {
          $not_found[] = $reply->Tag($tag);
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
          $prop->NewElement('http://calendarserver.org/ns/:getctag', $this->etag );
        }
        else {
          $not_found[] = $reply->Tag($tag);
        }
        break;

      case 'urn:ietf:params:xml:ns:caldav:calendar-data':
        if ( isset($this->caldav_data) ) {
        }
        break;

      default:
        dbg_error_log( 'resource', 'Request for unsupported property "%s" of path "%s".', $tag, $this->href );
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

    dbg_error_log('resource',': GetPropStat: href "%s"', $this->dav_name );

    $prop = new XMLElement('prop');
    $denied = array();
    $not_found = array();
    foreach( $properties AS $k => $tag ) {
      dbg_error_log( 'resource', 'Looking at resource "%s" for property [%s]"%s".', $this->href, $k, $tag );
      if ( ! $this->ResourceProperty($tag, $prop, $reply) ) {
        dbg_error_log( 'resource', 'Request for unsupported property "%s" of resource "%s".', $tag, $this->href );
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

    dbg_error_log('principal',': RenderAsXML: Principal "%s"', $this->username );

    $prop = new XMLElement('prop');
    $denied = array();
    $not_found = array();
    foreach( $properties AS $k => $tag ) {
      if ( ! $this->ResourceProperty($tag, $prop, $reply) ) {
        dbg_error_log( 'principal', 'Request for unsupported property "%s" of principal "%s".', $tag, $this->username );
        $not_found[] = $reply->Tag($tag);
      }
    }

    if ( $props_only ) return $prop;

    $status = new XMLElement('status', 'HTTP/1.1 200 OK' );

    $propstat = new XMLElement( 'propstat', array( $prop, $status) );
    $href = $reply->href($this->url );

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
