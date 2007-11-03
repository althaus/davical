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
  * Constructor
  * @param mixed $parameters If null, an empty Principal is created.  If it
  *              is an integer then that ID is read (if possible).  If it is
  *              an array then the Principal matching the supplied elements is read.
  *
  * @return boolean Whether we actually read data from the DB to initialise the record.
  */
  function CalDAVPrincipal( $parameters = null ) {
    global $session, $c;

    if ( $parameters == null ) return false;
    if ( is_int($parameters) ) {
      dbg_error_log( "principal", "Principal: %d", $parameters );
      $usr = getUserByID($parameters);
    }
    else if ( is_array($parameters) ) {
      if ( isset($parameters['username']) ) {
        $usr = getUserByName($parameters['username']);
      }
      else if ( isset($parameters['user_no']) ) {
        $usr = getUserByID($parameters['user_no']);
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

    $script = (preg_match('#/$#', $c->protocol_server_port_script) ? 'caldav.php' : '');
    $this->url = sprintf( "%s%s/%s/", $c->protocol_server_port_script, $script, $this->username);
//    $this->url = sprintf( "%s%s/__uuids__/%s/", $c->protocol_server_port_script, $script, $this->username);

    $this->calendar_home_set = sprintf( "%s%s/%s/", $c->protocol_server_port_script, $script, $this->username);

    $this->user_address_set = array(
       sprintf( "%s%s/%s/", $c->protocol_server_port_script, $script, $this->username),
//       sprintf( "%s%s/~%s/", $c->protocol_server_port_script, $script, $this->username),
//       sprintf( "%s%s/__uuids__/%s/", $c->protocol_server_port_script, $script, $this->username),
    );
    $this->schedule_inbox_url = sprintf( "%s.in/", $this->calendar_home_set);
    $this->schedule_outbox_url = sprintf( "%s.out/", $this->calendar_home_set);
    $this->dropbox_url = sprintf( "%s.drop/", $this->calendar_home_set);
    $this->notifications_url = sprintf( "%s.notify/", $this->calendar_home_set);

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



}