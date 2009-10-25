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
  * @var True if this resource is a collection
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

    $this->FindCollection();
  }


  /**
  * Find the collection associated with this resource.
  */
  function FindCollection() {
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
      'type' => 'unknown',
      'is_calendar' => false, 'is_principal' => false, 'is_addressbook' => false, 'resourcetypes' => '<DAV::collection/>',

    );

    $sql = "SELECT * FROM collection WHERE dav_name = :raw_path ";
    $params = array( ':raw_path' => $this->path);
    if ( !preg_match( '#/$#', $this->path ) ) {
      $sql .= ' OR dav_name = :up_to_slash OR dav_name = :plus_slash'
      $params[':up_to_slash'] = preg_replace( '#[^/]*$#', '', $this->path);
      $params[':plus_slash']  = $this->path."/";
    }
    $sql .= "ORDER BY LENGTH(dav_name) DESC LIMIT 1";
    $qry = new AwlQuery( $sql, $params );
    if ( $qry->Exec('caldav') && $qry->rows == 1 && ($row = $qry->Fetch()) ) {
      $this->collection = $row;
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
      $qry->Exec('caldav');
      dbg_error_log( "caldav", "Created new collection as '$displayname'." );

      $qry = new AwlQuery( "SELECT * FROM collection WHERE user_no = :user_no AND dav_name = :dav_name", $params );
      if ( $qry->Exec('caldav') && $qry->rows == 1 && ($row = $qry->Fetch()) ) {
        $this->collection = $row;
        $this->collection->type = $this->collection_type;
      }
    }
    else if ( preg_match( '#^((/[^/]+/)calendar-proxy-(read|write))/?[^/]*$#', $this->path, $matches ) ) {
      $this->collection_type = 'proxy';
      $this->_is_proxy_request = true;
      $this->proxy_type = $matches[3];
      $this->collection_path = $matches[1].'/';
    }
    else if ( $this->options['allow_by_email'] && preg_match( '#^/(\S+@\S+[.]\S+)/?$#', $this->path) ) {
      /** @TODO: we should deprecate this now that Evolution 2.27 can do scheduling extensions */
      $this->collection_id = -1;
      $this->collection_type = 'email';
      $this->collection_path = $this->path;
      $this->_is_principal = true;
    }
    else if ( preg_match( '#^(/[^/]+)/?$#', $this->path, $matches) || preg_match( '#^(/principals/[^/]+/[^/]+)/?$#', $this->path, $matches) ) {
      $this->collection_id = -1;
      $this->collection_path = $matches[1].'/';  // Enforce trailling '/'
      $this->collection_type = 'principal';
      $this->_is_principal = true;
      if ( preg_match( '#^(/principals/[^/]+/[^/]+)/?$#', $this->path, $matches) ) {
        // Force a depth of 0 on these, which are at the wrong URL.
        $this->depth = 0;
      }
    }
    else if ( $this->path == '/' ) {
      $this->collection_id = -1;
      $this->collection_path = '/';
      $this->collection_type = 'root';
    }

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
