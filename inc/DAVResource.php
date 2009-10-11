<?php
/**
* An object representing a DAV 'resource'
*
* @package   davical
* @subpackage   Resource
* @author    Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Morphoss Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
*/

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
  * Constructor
  * @param mixed $parameters If null, an empty Resourced is created.
  *     If it is an object then it is expected to be a record that was
  *     read elsewhere.
  */
  function __construct( $parameters = null ) {
    $this->_is_principal = false;
    $this->_is_collection = false;
    if ( isset($parameters) && is_object($parameters) ) {
      $this->FromRow($parameters);
    }
    else if ( isset($parameters) && is_array($parameters) ) {
    }
  }


  /**
  * Initialise from a database row
  * @param object $row The row from the DB.
  */
  function FromRow($row) {
    global $c;

    foreach( $row AS $k => $v ) {
      switch ( $k ) {
        case 'dav_name':
          $this->href = ConstructURL( '/'.$this->dav_name.'/', true );
          $this->{$k} = $v;
          break;

        case 'dav_etag':
          $this->unique_tag = $this->dav_etag;
          break;

        case 'caldav_data':
          $this->content = $this->caldav_data;
          break;

        case 'updated':
          $this->modified = ISODateToHTTPDate($this->updated);
          break;

        case 'created':
          $this->modified = ISODateToHTTPDate($this->joined);
          break;

        case 'username':
          $this->owner = ConstructURL( '/'.$this->username.'/', true );;
          $this->principal_url = ConstructURL( '/'.$this->username.'/', true );;
          break;

        default:
          $this->{$k} = $v;
      }
    }

    if ( ! isset($this->content) ) {
      $this->contenttype = 'http/unix-directory';
    }
  }


  /**
  * Return general server-related properties for this URL
  */
  function ResourceProperty( $tag, $prop, $reply = null ) {
    global $c, $session;

    if ( $reply === null ) $reply = $GLOBALS['reply'];

    dbg_error_log( 'caldav', 'Processing "%s" on "%s".', $tag, $this->path );

    switch( $tag ) {
      case 'DAV::getcontenttype':
        $prop->NewElement('getcontenttype', $this->contenttype );
        break;  

      case 'DAV::resourcetype':
        $prop->NewElement('resourcetype', $this->resourcetype );
        break;

      case 'DAV::displayname':
        $prop->NewElement('displayname', $this->displayname );
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
        $reply->DAVElement( $prop, 'owner', $reply->href( $this->principal_url ) );
        break;
	
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
          $prop->NewElement('getetag', $this->etag );
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

      case 'DAV::sync-token':
        if ( $this->_is_collection ) {
          $prop->NewElement('sync-token', $this->NewSyncToken() );
        }
        else {
          $not_found[] = $reply->Tag($tag);
        }
        break;

      default:
        dbg_error_log( 'caldav', 'Request for unsupported property "%s" of path "%s".', $tag, $this->href );
        return false;
    }
    return true;
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

        default:
          if ( ! $request->ServerProperty( $tag, $prop, $reply ) ) {
            dbg_error_log( 'principal', 'Request for unsupported property "%s" of principal "%s".', $tag, $this->username );
            $not_found[] = $reply->Tag($tag);
          }
          break;
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
