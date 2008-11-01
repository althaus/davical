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
      dbg_error_log( "principal", "Principal: record for %s", $parameters->username );
      $usr = $parameters;
    }
    else if ( is_int($parameters) ) {
      dbg_error_log( "principal", "Principal: %d", $parameters );
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
      else if ( isset($parameters['email']) && isset($parameters['options']['allow_by_email']) ) {
        $parameters['options']['allow_by_email'] = false;
        if ( $username = $this->UsernameFromEMail($parameters['email']) ) {
          $usr = getUserByName($username);
          $this->by_email = true;
        }
      }
      else if ( isset($parameters['path']) ) {
        dbg_error_log( "principal", "Finding Principal from path: '%s', options.allow_by_email: '%s'", $parameters['path'], $parameters['options']['allow_by_email'] );
        if ( $username = $this->UsernameFromPath($parameters['path'], $parameters['options']) ) {
          $usr = getUserByName($username);
          if ( isset($parameters['options']['allow_by_email']) && is_object($usr) && preg_match( '#/(\S+@\S+[.]\S+)$#', $parameters['path']) ) {
            $this->by_email = true;
          }
        }
      }
      else if ( isset($parameters['principal-property-search']) ) {
        $usr = $this->PropertySearch($parameters['principal-property-search']);
      }
    }
    if ( !isset($usr) || !is_object($usr) ) return false;

    $this->InitialiseRecord($usr);
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

    $this->by_email = false;
    $this->url = ConstructURL( "/".$this->username."/" );

    $this->calendar_home_set = array( $this->url );

    $this->user_address_set = array(
       "mailto:".$this->email,
       ConstructURL( "/".$this->username."/" ),
//       ConstructURL( "/~".$this->username."/" ),
//       ConstructURL( "/__uuids__/".$this->username."/" ),
    );
    $this->schedule_inbox_url = sprintf( "%s.in/", $this->url);
    $this->schedule_outbox_url = sprintf( "%s.out/", $this->url);
    $this->dropbox_url = sprintf( "%s.drop/", $this->url);
    $this->notifications_url = sprintf( "%s.notify/", $this->url);

    $this->group_member_set = array();
    $qry = new PgQuery("SELECT * FROM relationship LEFT JOIN usr ON (from_user = usr.user_no) LEFT JOIN role_member ON (to_user = role_member.user_no) LEFT JOIN roles USING (role_no) WHERE to_user = ? AND role_name = 'Group';", $this->user_no );
    if ( $qry->Exec("CalDAVPrincipal") && $qry->rows > 0 ) {
      while( $membership = $qry->Fetch() ) {
            $this->group_member_set[] = ConstructURL( "/". $membership->username . "/");
      }
    }

    $this->group_membership = array();
    $qry = new PgQuery("SELECT * FROM relationship LEFT JOIN usr ON (to_user = user_no) LEFT JOIN role_member USING (user_no) LEFT JOIN roles USING (role_no) WHERE from_user = ? AND role_name = 'Group';", $this->user_no );
    if ( $qry->Exec("CalDAVPrincipal") && $qry->rows > 0 ) {
      while( $membership = $qry->Fetch() ) {
        $this->group_membership[] = ConstructURL( "/". $membership->username . "/");
      }
    }

    /**
    * calendar-free-busy-set has been dropped from draft 5 of the scheduling extensions for CalDAV
    * but we'll keep replying to it for a while longer since iCal appears to want it...
    */
    $qry = new PgQuery("SELECT dav_name FROM collection WHERE user_no = ? AND is_calendar", $this->user_no);
    $this->calendar_free_busy_set = array();
    if( $qry->Exec("CalDAVPrincipal",__LINE__,__FILE__) && $qry->rows > 0 ) {
      while( $calendar = $qry->Fetch() ) {
        $this->calendar_free_busy_set[] = ConstructURL($calendar->dav_name);
      }
    }

    dbg_error_log( "principal", "User: %s (%d) URL: %s, Home: %s, By Email: %d", $this->username, $this->user_no, $this->url, $this->calendar_home_set, $this->by_email );
  }


  /**
  * Work out the username, based on elements of the path.
  * @param string $path The path to be used.
  * @param array $options The request options, controlling whether e-mail paths are allowed.
  */
  function UsernameFromPath( $path, $options = null ) {
    global $session, $c;

    if ( $path == '/' || $path == '' ) {
      dbg_error_log( "principal", "No useful path split possible" );
      return $session->username;
    }

    $path_split = explode('/', $path );
    @dbg_error_log( "principal", "Path split into at least /// %s /// %s /// %s", $path_split[1], $path_split[2], $path_split[3] );

    if ( substr($path,0,1) == '~' ) {
      // URL is for a principal, by name
      $username = substr($path_split[1],1);
      $user = getUserByID($username);
      $user_no = $user->user_no;
    }
    else {
      $username = $path_split[1];

      if ( isset($options['allow_by_email']) && preg_match( '#/(\S+@\S+[.]\S+)$#', $path, $matches) ) {
        $email = $matches[1];
        $qry = new PgQuery("SELECT user_no, username FROM usr WHERE email = ?;", $email );
        if ( $qry->Exec("principal") && $user = $qry->Fetch() ) {
          $user_no = $user->user_no;
          $username = $user->username;
        }
      }
      elseif( $user = getUserByName( $username, 'caldav') ) {
        $user_no = $user->user_no;
      }
    }
    return $username;
  }


  /**
  * Work out the username, based on the given e-mail
  * @param string $email The email address to be used.
  */
  function UsernameFromEMail( $email ) {
    $qry = new PgQuery("SELECT user_no, username FROM usr WHERE email = ?;", $email );
    if ( $qry->Exec("principal") && $user = $qry->Fetch() ) {
      $user_no = $user->user_no;
      $username = $user->username;
    }

    return $username;
  }


  /**
  * Returns the array of privilege names converted into XMLElements
  */
  function RenderPrivileges($privilege_names, $container="privilege") {
    $privileges = array();
    foreach( $privilege_names AS $k => $v ) {
      $privileges[] = new XMLElement($container, new XMLElement($k));
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

    dbg_error_log("CalDAVPrincipal",": RenderAsXML: Principal '%s'", $this->username );

    $prop = new XMLElement("prop");
    $denied = array();
    $not_found = array();
    foreach( $properties AS $k => $tag ) {
      dbg_error_log("CalDAVPrincipal",": RenderAsXML: Principal Property '%s'", $tag );
      switch( $tag ) {
        case 'DAV::getcontenttype':
          $prop->NewElement("getcontenttype", "httpd/unix-directory" );
          break;

        case 'DAV::resourcetype':
          $prop->NewElement("resourcetype", array( new XMLElement("principal"), new XMLElement("collection")) );
          break;

        case 'DAV::displayname':
          $prop->NewElement("displayname", $this->fullname );
          break;

        case 'DAV::principal-URL':
          $prop->NewElement("principal-URL", $this->url );
          break;

        case 'DAV::getlastmodified':
          $prop->NewElement("getlastmodified", $this->modified );
          break;

        case 'DAV::group-member-set':
          $set = array();
          foreach( $this->group_member_set AS $k => $url ) {
            $set[] = new XMLElement('href', $url );
          }
          $prop->NewElement("group-member-set", $set );
          break;

        case 'DAV::group-membership':
          $set = array();
          foreach( $this->group_membership AS $k => $url ) {
            $set[] = new XMLElement('href', $url );
          }
          $prop->NewElement("group-membership", $set );
          break;

        case 'urn:ietf:params:xml:ns:caldav:schedule-inbox-URL':
          $prop->NewElement($reply->Caldav("schedule-inbox-URL"), new XMLElement('href', $this->schedule_inbox_url) );
          break;

        case 'urn:ietf:params:xml:ns:caldav:schedule-outbox-URL':
          $prop->NewElement($reply->Caldav("schedule-outbox-URL"), new XMLElement('href', $this->schedule_outbox_url) );
          break;

        case 'http://calendarserver.org/ns/:dropbox-home-URL':
          $prop->NewElement($reply->Calendarserver("dropbox-home-URL"), new XMLElement('href', $this->dropbox_url) );
          break;

        case 'http://calendarserver.org/ns/:notifications-URL':
          $prop->NewElement($reply->Calendarserver("notifications-URL"), new XMLElement('href', $this->notifications_url) );
          break;

        case 'urn:ietf:params:xml:ns:caldav:calendar-home-set':
          $set = array();
          foreach( $this->calendar_home_set AS $k => $url ) {
            $set[] = new XMLElement('href', $url );
          }
          $prop->NewElement($reply->Caldav("calendar-home-set"), $set );
          break;

        case 'urn:ietf:params:xml:ns:caldav:calendar-user-address-set':
          $set = array();
          foreach( $this->user_address_set AS $k => $v ) {
            $set[] = new XMLElement('href', $v );
          }
          $prop->NewElement($reply->Caldav("calendar-user-address-set"), $set );
          break;

        case 'DAV::getcontentlanguage':
          $locale = (isset($c->current_locale) ? $c->current_locale : "");
          if ( isset($this->locale) && $this->locale != "" ) $locale = $this->locale;
          $prop->NewElement("getcontentlanguage", $locale );
          break;

        case 'DAV::supportedlock':
          $prop->NewElement("supportedlock",
            new XMLElement( "lockentry",
              array(
                new XMLElement("lockscope", new XMLElement("exclusive")),
                new XMLElement("locktype",  new XMLElement("write")),
              )
            )
          );
          break;

        case 'DAV::acl':
          /**
          * @todo This information is semantically valid but presents an incorrect picture.
          */
          $principal = new XMLElement("principal");
          $principal->NewElement("authenticated");
          $grant = new XMLElement( "grant", array($this->RenderPrivileges($request->permissions)) );
          $prop->NewElement("acl", new XMLElement( "ace", array( $principal, $grant ) ) );
          break;

        case 'DAV::current-user-privilege-set':
          $prop->NewElement("current-user-privilege-set", $this->RenderPrivileges($request->permissions) );
          break;

        case 'DAV::supported-privilege-set':
          $prop->NewElement("supported-privilege-set", $this->RenderPrivileges( $request->SupportedPrivileges(), "supported-privilege") );
          break;

        // Empty tag responses.
        case 'DAV::creationdate':
        case 'DAV::alternate-URI-set':
        case 'DAV::getcontentlength':
          $prop->NewElement( $reply->Tag($tag));
          break;

        case 'SOME-DENIED-PROPERTY':  /** @todo indicating the style for future expansion */
          $denied[] = $reply->Tag($tag);
          break;

        default:
          dbg_error_log( 'CalDAVPrincipal', "Request for unsupported property '%s' of principal.", $this->username );
          $not_found[] = $reply->Tag($tag);
          break;
      }
    }

    if ( $props_only ) return $prop;

    $status = new XMLElement("status", "HTTP/1.1 200 OK" );

    $propstat = new XMLElement( "propstat", array( $prop, $status) );
    $href = new XMLElement("href", $this->url );

    $elements = array($href,$propstat);

    if ( count($denied) > 0 ) {
      $status = new XMLElement("status", "HTTP/1.1 403 Forbidden" );
      $noprop = new XMLElement("prop");
      foreach( $denied AS $k => $v ) {
        $noprop->NewElement( strtolower($v) );
      }
      $elements[] = new XMLElement( "propstat", array( $noprop, $status) );
    }

    if ( count($not_found) > 0 ) {
      $status = new XMLElement("status", "HTTP/1.1 404 Not Found" );
      $noprop = new XMLElement("prop");
      foreach( $not_found AS $k => $v ) {
        $noprop->NewElement( strtolower($v) );
      }
      $elements[] = new XMLElement( "propstat", array( $noprop, $status) );
    }

    $response = new XMLElement( "response", $elements );

    return $response;
  }

}
