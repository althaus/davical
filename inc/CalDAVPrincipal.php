<?php
/**
* An object representing a DAV 'Principal'
*
* @package   davical
* @subpackage   Principal
* @author    Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

/**
* @var $_CalDAVPrincipalCache
* A global variable holding a cache of any DAV Principals which are
* read from the DB.
*/
$_CalDAVPrincipalCache = (object) array( 'p' => array(), 'u' => array() );


/**
* A class for things to do with a DAV Principal
*
* @package   davical
*/
class CalDAVPrincipal
{
  /**
  * @var The home URL of the principal
  */
  var $url;

  /**
  * @var RFC4791: Identifies the URL(s) of any WebDAV collections that contain
  * calendar collections owned by the associated principal resource.
  */
  var $calendar_home_set;

  /**
  * @var draft-desruisseaux-caldav-sched-03: Identify the URL of the scheduling
  * Inbox collection owned by the associated principal resource.
  */
  var $schedule_inbox_url;

  /**
  * @var draft-desruisseaux-caldav-sched-03: Identify the URL of the scheduling
  * Outbox collection owned by the associated principal resource.
  */
  var $schedule_outbox_url;

  /**
  * @var Whether or not we are using an e-mail address based URL.
  */
  var $by_email;

  /**
  * @var RFC3744: The principals that are direct members of this group.
  */
  var $group_member_set;

  /**
  * @var RFC3744: The groups in which the principal is directly a member.
  */
  var $group_membership;

  /**
   * @var caldav-cu-proxy-02: The principals which this one has read permissions on.
   */
  var $read_proxy_for;

  /**
   * @var caldav-cu-proxy-02: The principals which this one has read-write prmissions for.
   */
  var $write_proxy_for;

   /**
   * @var caldav-cu-proxy-02: The principals which have read permissions on this one.
   */
  var $read_proxy_group;

  /**
   * @var caldav-cu-proxy-02: The principals which have write permissions on this one.
   */
  var $write_proxy_group;

  /**
  * Constructor
  * @param mixed $parameters If null, an empty Principal is created.  If it
  *              is an integer then that ID is read (if possible).  If it is
  *              an array then the Principal matching the supplied elements
  *              is read.  If it is an object then it is expected to be a 'usr'
  *              record that was read elsewhere.
  *
  * @return boolean Whether we actually read data from the DB to initialise the record.
  */
  function CalDAVPrincipal( $parameters = null ) {
    global $session, $c;

    if ( $parameters == null ) return false;
    $this->by_email = false;
    if ( is_object($parameters) ) {
      dbg_error_log( 'principal', 'Principal: record for %s', $parameters->username );
      $usr = $parameters;
    }
    else if ( is_int($parameters) ) {
      dbg_error_log( 'principal', 'Principal: %d', $parameters );
      $usr = getUserByID($parameters);
    }
    else if ( is_array($parameters) ) {
      if ( ! isset($parameters['options']['allow_by_email']) ) $parameters['options']['allow_by_email'] = false;
      if ( isset($parameters['username']) ) {
        $usr = getUserByName($parameters['username']);
      }
      else if ( isset($parameters['user_no']) ) {
        $usr = getUserByID($parameters['user_no']);
      }
      else if ( isset($parameters['email']) && $parameters['options']['allow_by_email'] ) {
        if ( $username = $this->UsernameFromEMail($parameters['email']) ) {
          $usr = getUserByName($username);
          $this->by_email = true;
        }
      }
      else if ( isset($parameters['path']) ) {
        dbg_error_log( 'principal', 'Finding Principal from path: "%s", options.allow_by_email: "%s"', $parameters['path'], $parameters['options']['allow_by_email'] );
        if ( $username = $this->UsernameFromPath($parameters['path'], $parameters['options']) ) {
          $usr = getUserByName($username);
        }
      }
      else if ( isset($parameters['principal-property-search']) ) {
        $usr = $this->PropertySearch($parameters['principal-property-search']);
      }
    }
    if ( !isset($usr) || !is_object($usr) ) return false;

    $this->InitialiseRecord($usr);

    if ( is_array($parameters) && isset($parameters['path']) && preg_match('#^/principals/#', $parameters['path']) ) {
      // Force it to match
      $this->url = $parameters['path'];
    }
  }


  /**
  * Initialise the Principal object from a $usr record from the DB.
  * @param object $usr The usr record from the DB.
  */
  function InitialiseRecord($usr) {
    global $c;
    foreach( $usr AS $k => $v ) {
      $this->{$k} = $v;
    }
    if ( !isset($this->modified) ) $this->modified = ISODateToHTTPDate($this->updated);
    if ( !isset($this->created) )  $this->created  = ISODateToHTTPDate($this->joined);

    $this->by_email = false;
    $this->principal_url = ConstructURL( '/'.$this->username.'/', true );
    $this->url = $this->principal_url;

    $this->calendar_home_set = array( $this->url );

    $this->user_address_set = array(
       'mailto:'.$this->email,
       ConstructURL( '/'.$this->username.'/', true ),
//       ConstructURL( '/~'.$this->username.'/', true ),
//       ConstructURL( '/__uuids__/'.$this->username.'/', true ),
    );
    $this->schedule_inbox_url = sprintf( '%s.in/', $this->url);
    $this->schedule_outbox_url = sprintf( '%s.out/', $this->url);
    $this->dropbox_url = sprintf( '%s.drop/', $this->url);
    $this->notifications_url = sprintf( '%s.notify/', $this->url);

    $this->group_member_set = array();
    $qry = new PgQuery('SELECT * FROM relationship LEFT JOIN usr ON (from_user = usr.user_no) LEFT JOIN role_member ON (to_user = role_member.user_no) LEFT JOIN roles USING (role_no) WHERE to_user = ? AND role_name = '."'Group'", $this->user_no );
    if ( $qry->Exec('CalDAVPrincipal') && $qry->rows > 0 ) {
      while( $membership = $qry->Fetch() ) {
            $this->group_member_set[] = ConstructURL( '/'. $membership->username . '/', true);
      }
    }

    $this->group_membership = array();
    $qry = new PgQuery('SELECT * FROM relationship LEFT JOIN usr ON (to_user = user_no) LEFT JOIN role_member USING (user_no) LEFT JOIN roles USING (role_no) WHERE from_user = ? AND role_name = '."'Group'", $this->user_no );
    if ( $qry->Exec('CalDAVPrincipal') && $qry->rows > 0 ) {
      while( $membership = $qry->Fetch() ) {
        $this->group_membership[] = ConstructURL( '/'. $membership->username . '/', true);
      }
    }

    $this->read_proxy_group = array();
    $this->write_proxy_group = array();
    $this->write_proxy_for = array();
    $this->read_proxy_for = array();

    if ( !isset($c->disable_caldav_proxy) || $c->disable_caldav_proxy === false ) {
      // whom are we a proxy for? who is a proxy for us?
      // (as per Caldav Proxy section 5.1 Paragraph 7 and 5)
      $qry = new PgQuery('SELECT from_user.user_no AS from_user_no, from_user.username AS from_username,'.
              'get_permissions(from_user.user_no, to_user.user_no) AS confers,'.
              'to_user.user_no AS to_user_no, to_user.username AS to_username '.
              'FROM usr from_user, usr to_user WHERE '.
              "get_permissions(from_user.user_no, to_user.user_no) ~ '[AWR]' AND ".
              'to_user.user_no != from_user.user_no AND (from_user.user_no = ? OR '.
              'to_user.user_no = ?)', $this->user_no, $this->user_no );
      if ( $qry->Exec('CalDAVPrincipal') && $qry->rows > 0 ) {
        while( $relationship = $qry->Fetch() ) {
          if ($relationship->confers == 'R') {
            if ($relationship->from_user_no == $this->user_no) {
                // spec says without trailing slash, CalServ does it with slash, and so do we.
                $this->group_membership[] = ConstructURL( '/'. $relationship->to_username . '/calendar-proxy-read/', true);
                  $this->read_proxy_for[] = ConstructURL( '/'. $relationship->to_username . '/', true);
            } else /* ($relationship->to_user_no == $this->user_no) */ {
              $this->read_proxy_group[] = ConstructURL( '/'. $relationship->from_username . '/', true);
            }
          } else if (preg_match('/[WA]/', $relationship->confers)) {
            if ($relationship->from_user_no == $this->user_no) {
              $this->group_membership[] = ConstructURL( '/'. $relationship->to_username . '/calendar-proxy-write/', true);
              $this->write_proxy_for[] = ConstructURL( '/'. $relationship->to_username . '/', true);
            } else /* ($relationship->to_user_no == $this->user_no) */ {
              $this->write_proxy_group[] = ConstructURL( '/'. $relationship->from_username . '/', true);
            }
          }
        }
      }
    }

    /**
    * calendar-free-busy-set has been dropped from draft 5 of the scheduling extensions for CalDAV
    * but we'll keep replying to it for a while longer since iCal appears to want it...
    */
    $qry = new PgQuery('SELECT dav_name FROM collection WHERE user_no = ? AND is_calendar', $this->user_no);
    $this->calendar_free_busy_set = array();
    if( $qry->Exec('CalDAVPrincipal',__LINE__,__FILE__) && $qry->rows > 0 ) {
      while( $calendar = $qry->Fetch() ) {
        $this->calendar_free_busy_set[] = ConstructURL($calendar->dav_name, true);
      }
    }

    dbg_error_log( 'principal', ' User: %s (%d) URL: %s, Home: %s, By Email: %d', $this->username, $this->user_no, $this->url, $this->calendar_home_set, $this->by_email );
  }


  /**
  * Work out the username, based on elements of the path.
  * @param string $path The path to be used.
  * @param array $options The request options, controlling whether e-mail paths are allowed.
  */
  function UsernameFromPath( $path, $options = null ) {
    global $session, $c;

    if ( $path == '/' || $path == '' ) {
      dbg_error_log( 'principal', 'No useful path split possible' );
      return $session->username;
    }

    $path_split = explode('/', $path );
    @dbg_error_log( 'principal', 'Path split into at least /// %s /// %s /// %s', $path_split[1], $path_split[2], $path_split[3] );

    $username = $path_split[1];
    if ( $path_split[1] == 'principals' ) $username = $path_split[3];
    if ( substr($username,0,1) == '~' ) $username = substr($username,1);

    if ( isset($options['allow_by_email']) && $options['allow_by_email'] && preg_match( '#/(\S+@\S+[.]\S+)$#', $path, $matches) ) {
      $email = $matches[1];
      $qry = new PgQuery('SELECT user_no, username FROM usr WHERE email = ?;', $email );
      if ( $qry->Exec('principal') && $user = $qry->Fetch() ) {
        $user_no = $user->user_no;
        $username = $user->username;
      }
    }
    elseif( $user = getUserByName( $username, 'caldav') ) {
      $user_no = $user->user_no;
    }
    return $username;
  }


  /**
  * Work out the username, based on the given e-mail
  * @param string $email The email address to be used.
  */
  function UsernameFromEMail( $email ) {
    $qry = new PgQuery('SELECT user_no, username FROM usr WHERE email = ?;', $email );
    if ( $qry->Exec('principal') && $user = $qry->Fetch() ) {
      $user_no = $user->user_no;
      $username = $user->username;
    }

    return $username;
  }


  /**
  * Returns a representation of the principal as a collection
  */
  function AsCollection() {
    $collection = (object) array(
                            'collection_id' => (isset($this->collection_id) ? $this->collection_id : 0),
                            'is_calendar' => 'f',
                            'is_principal' => 't',
                            'user_no'  => (isset($this->user_no)  ? $this->user_no : 0),
                            'username' => (isset($this->username) ? $this->username : 0),
                            'email'    => (isset($this->email)    ? $this->email : ''),
                            'created'  => (isset($this->created)  ? $this->created : date('Ymd\THis'))
                  );
    $collection->dav_name = (isset($this->dav_name) ? $this->dav_name : '/' . $this->username . '/');
    $collection->dav_etag = (isset($this->dav_etag) ? $this->dav_etag : md5($this->username . $this->updated));
    $collection->dav_displayname =  (isset($this->dav_displayname) ? $this->dav_displayname : (isset($this->fullname) ? $this->fullname : $this->username));
    return $collection;
  }


  /**
  * Returns the array of privilege names converted into XMLElements
  */
  function RenderPrivileges($privilege_names, $container='privilege') {
    global $reply;
    $privileges = array();
    foreach( $privilege_names AS $k => $v ) {
      $privilege = new XMLElement($container);
      $reply->NSElement($privilege,$k);
      $privileges[] = $privilege;
    }
    return $privileges;
  }


  /**
  * Render XML for a single Principal (user) from the DB
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
      dbg_error_log('principal',': RenderAsXML: Principal Property "%s"', $tag );
      switch( $tag ) {
        case 'DAV::getcontenttype':
          $prop->NewElement('getcontenttype', 'httpd/unix-directory' );
          break;

        case 'DAV::resourcetype':
          $prop->NewElement('resourcetype', array( new XMLElement('principal'), new XMLElement('collection')) );
          break;

        case 'DAV::displayname':
          $prop->NewElement('displayname', $this->fullname );
          break;

        case 'DAV::principal-URL':
          $prop->NewElement('principal-URL', $reply->href($this->principal_url) );
          break;

        case 'DAV::getlastmodified':
          $prop->NewElement('getlastmodified', $this->modified );
          break;

        case 'DAV::creationdate':
          $prop->NewElement('creationdate', $this->created );
          break;

        case 'DAV::group-member-set':
          $prop->NewElement('group-member-set', $reply->href($this->group_member_set) );
          break;

        case 'DAV::group-membership':
          $prop->NewElement('group-membership', $reply->href($this->group_membership) );
          break;

        case 'urn:ietf:params:xml:ns:caldav:schedule-inbox-URL':
          $reply->CalDAVElement($prop, 'schedule-inbox-URL', $reply->href($this->schedule_inbox_url) );
          break;

        case 'urn:ietf:params:xml:ns:caldav:schedule-outbox-URL':
          $reply->CalDAVElement($prop, 'schedule-outbox-URL', $reply->href($this->schedule_outbox_url) );
          break;

        case 'http://calendarserver.org/ns/:dropbox-home-URL':
          $reply->CalendarserverElement($prop, 'dropbox-home-URL', $reply->href($this->dropbox_url) );
          break;

        case 'http://calendarserver.org/ns/:notifications-URL':
          $reply->CalendarserverElement($prop, 'notifications-URL', $reply->href($this->notifications_url) );
          break;

        case 'urn:ietf:params:xml:ns:caldav:calendar-home-set':
          $reply->CalDAVElement($prop, 'calendar-home-set', $reply->href( $this->calendar_home_set ) );
          break;

        case 'urn:ietf:params:xml:ns:caldav:calendar-user-address-set':
          $reply->CalDAVElement($prop, 'calendar-user-address-set', $reply->href($this->user_address_set) );
          break;

//        case 'urn:ietf:params:xml:ns:caldav:supported-calendar-component-set':
//          // Note that this won't appear on a PROPFIND against a Principal URL, since this routine is only called for a collection
//          $components = array();
//          $set_of_components = array( 'VEVENT', 'VTODO', 'VJOURNAL', 'VTIMEZONE', 'VFREEBUSY' );
//          foreach( $set_of_components AS $v ) {
//            $components[] = $reply->NewXMLElement( 'comp', '', array('name' => $v), 'urn:ietf:params:xml:ns:caldav');
//          }
//          $reply->CalDAVElement($prop, 'supported-calendar-component-set', $components );
//          break;

        case 'DAV::getcontentlanguage':
          $locale = (isset($c->current_locale) ? $c->current_locale : '');
          if ( isset($this->locale) && $this->locale != '' ) $locale = $this->locale;
          $prop->NewElement('getcontentlanguage', $locale );
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

        case 'DAV::acl':
          /**
          * @todo This information is semantically valid but presents an incorrect picture.
          */
          $principal = new XMLElement('principal');
          $principal->NewElement('authenticated');
          $grant = new XMLElement( 'grant', array($this->RenderPrivileges($request->permissions)) );
          $prop->NewElement('acl', new XMLElement( 'ace', array( $principal, $grant ) ) );
          break;

        case 'DAV::current-user-privilege-set':
          $prop->NewElement('current-user-privilege-set', $this->RenderPrivileges($request->permissions) );
          break;

        case 'DAV::supported-privilege-set':
          $prop->NewElement('supported-privilege-set', $this->RenderPrivileges( $request->SupportedPrivileges(), 'supported-privilege') );
          break;

        // Empty tag responses.
        case 'DAV::alternate-URI-set':
        case 'DAV::getcontentlength':
          $prop->NewElement( $reply->Tag($tag));
          break;

//        case 'http://calendarserver.org/ns/:getctag':
//          $reply->CalendarServerElement( $prop, 'getctag', '"'.md5($this->username . $this->updated).'"' );
//          break;
//        case 'DAV::getetag':
//          $reply->DAVElement( $prop, 'getetag', '"'.md5($this->username . $this->updated).'"' );
//          break;

        case 'SOME-DENIED-PROPERTY':  /** @todo indicating the style for future expansion */
          $denied[] = $reply->Tag($tag);
          break;

        default:
          dbg_error_log( 'principal', 'Request for unsupported property "%s" of principal "%s".', $tag, $this->username );
          $not_found[] = $reply->Tag($tag);
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
