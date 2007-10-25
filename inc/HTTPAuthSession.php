<?php
/**
* A Class for handling HTTP Authentication
*
* @package rscds
* @subpackage HTTPAuthSession
* @author Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

/**
* A Class for handling a session using HTTP Basic Authentication
*
* @package rscds
*/
class HTTPAuthSession {
  /**#@+
  * @access private
  */

  /**
  * User ID number
  * @var user_no int
  */
  var $user_no;

  /**
  * User e-mail
  * @var email string
  */
  var $email;

  /**
  * User full name
  * @var fullname string
  */
  var $fullname;

  /**
  * Group rights
  * @var groups array
  */
  var $groups;
  /**#@-*/

  /**
  * The constructor, which just calls the actual type configured
  */
  function HTTPAuthSession() {
    global $c;

    if ( isset($c->http_auth_mode) && $c->http_auth_mode == "Digest" ) {
      $this->DigestAuthSession();
    }
    else {
      $this->BasicAuthSession();
    }
  }

  /**
  * Authorisation failed, so we send some headers to say so.
  *
  * @param string $auth_header The WWW-Authenticate header details.
  */
  function AuthFailedResponse( $auth_header = "" ) {
    global $c;
    if ( $auth_header == "" ) {
      $auth_header = sprintf( 'WWW-Authenticate: Basic realm="%s"', $c->system_name);
    }

    header('HTTP/1.1 401 Unauthorized', true, 401 );
    header('Content-type: text/plain; ; charset="utf-8"' );
    header( $auth_header );
    echo 'Please log in for access to this system.';
    dbg_error_log( "HTTPAuth", ":Session: User is not authorised" );
    exit;
  }


  /**
  * Handle Basic HTTP Authentication (not secure unless https)
  */
  function BasicAuthSession() {
    global $c;
    if ( isset($_SERVER['PHP_AUTH_USER']) ) {
      if ( $u = $this->CheckPassword( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ) ) {
        $this->AssignSessionDetails($u);
        return;
      }
    }

    $this->AuthFailedResponse();
    // Does not return
  }


  /**
  * Handle Digest HTTP Authentication (no passwords were harmed in this transaction!)
  *
  * Note that this will not actually work, unless we can either:
  *   (A) store the password plain text in the database
  *   (B) store an md5( username || realm || password ) in the database
  *
  * The problem is that potentially means that the administrator can collect the sorts
  * of things people use as passwords.  I believe this is quite a bad idea.  In scenario (B)
  * while they cannot see the password itself, they can see a hash which only varies when
  * the password varies, so can see when two users have the same password, or can use
  * some of the reverse lookup sites to attempt to reverse the hash.  I think this is a
  * less bad idea, but not ideal.  Probably better than running Basic auth of HTTP though!
  */
  function DigestAuthSession() {
    global $c;

    if ( ! empty($_SERVER['PHP_AUTH_DIGEST'])) {
      // analyze the PHP_AUTH_DIGEST variable
      if ( $data = $this->ParseDigestHeader($_SERVER['PHP_AUTH_DIGEST']) ) {
        // generate the valid response
        $user_password = "Don't be silly! Why would a user have a password like this!!?";
        /**
        * TODO: At this point we need to query the database for something fitting
        * either strategy (A) or (B) above, in order to set $user_password to
        * something useful!
        */
        $A1 = md5($data['username'] . ':' . $c->system_name . ':' . $user_password);
        $A2 = md5($_SERVER['REQUEST_METHOD'].':'.$data['uri']);
        $valid_response = md5($A1.':'.$data['nonce'].':'.$data['nc'].':'.$data['cnonce'].':'.$data['qop'].':'.$A2);

        if ( $data['response'] == $valid_response ) {
          $this->AssignSessionDetails($u);
          return;
        }
      }
    }

    $nonce = uniqid();
    $opaque = md5($c->system_name);
    $this->AuthFailedResponse( sprintf('WWW-Authenticate: Digest realm="%s", qop="auth,auth-int", nonce="%s", opaque="%s"', $c->system_name, $nonce, $opaque ) );
  }


  /**
  * Parse the HTTP Digest Auth Header
  *  - largely sourced from the PHP documentation
  */
  function ParseDigestHeader($auth_header) {
    // protect against missing data
    $needed_parts = array('nonce'=>1, 'nc'=>1, 'cnonce'=>1, 'qop'=>1, 'username'=>1, 'uri'=>1, 'response'=>1);
    $data = array();

    preg_match_all('@(\w+)=(?:([\'"])([^\2]+)\2|([^\s,]+))@', $auth_header, $matches, PREG_SET_ORDER);

    foreach ($matches as $m) {
      $data[$m[1]] = $m[3] ? $m[3] : $m[4];
      unset($needed_parts[$m[1]]);
    }

    return $needed_parts ? false : $data;
  }

  /**
  * CheckPassword does all of the password checking and
  * returns a user record object, or false if it all ends in tears.
  */
  function CheckPassword( $username, $password ) {
    global $c;

    if ( isset($c->authenticate_hook) && isset($c->authenticate_hook['call']) && function_exists($c->authenticate_hook['call']) ) {
      /**
      * The authenticate hook needs to:
      *   - Accept a username / password
      *   - Confirm the username / password are correct
      *   - Create (or update) a 'usr' record in our database
      *   - Return the 'usr' record as an object
      *   - Return === false when authentication fails
      *
      * It can expect that:
      *   - Configuration data will be in $c->authenticate_hook['config'], which might be an array, or whatever is needed.
      */
      return call_user_func( $c->authenticate_hook['call'], $username, $password );
    }

    if ( $usr = getUserByName($username) ) {
      dbg_error_log( "BasicAuth", ":CheckPassword: Name:%s, Pass:%s, File:%s", $username, $password, $usr->password );
      if ( session_validate_password( $password, $usr->password ) ) {
        return $usr;
      }
    }
    return false;
  }

  /**
  * Checks whether a user is allowed to do something.
  *
  * The check is performed to see if the user has that role.
  *
  * @param string $whatever The role we want to know if the user has.
  * @return boolean Whether or not the user has the specified role.
  */
  function AllowedTo ( $whatever ) {
    return ( $this->logged_in && isset($this->roles[$whatever]) && $this->roles[$whatever] );
  }


  /**
  * Internal function used to get the user's roles from the database.
  */
  function GetRoles () {
    $this->roles = array();
    $qry = new PgQuery( 'SELECT role_name FROM role_member m join roles r ON r.role_no = m.role_no WHERE user_no = ? ', $this->user_no );
    if ( $qry->Exec('BasicAuth') && $qry->rows > 0 ) {
      while( $role = $qry->Fetch() ) {
        $this->roles[$role->role_name] = true;
      }
    }
  }


  /**
  * Internal function used to assign the session details to a user's new session.
  * @param object $u The user+session object we (probably) read from the database.
  */
  function AssignSessionDetails( $u ) {
    // Assign each field in the selected record to the object
    foreach( $u AS $k => $v ) {
      $this->{$k} = $v;
    }

    $this->GetRoles();
    $this->logged_in = true;
    if ( function_exists("awl_set_locale") && isset($this->locale) && $this->locale != "" ) {
      awl_set_locale($this->locale);
    }
  }


}

?>