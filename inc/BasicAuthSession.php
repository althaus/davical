<?php
/**
* A Class for handling BasicAuthSession data
*
* @package rscds
* @subpackage BasicAuthSession
* @author Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst IT Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

/**
* A Class for handling a session using HTTP Basic Authentication
*
* @package rscds
*/
class BasicAuthSession {
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
  * The constructor, which pretty much drives it all
  */
  function BasicAuthSession() {
    global $c;
    if ( isset($_SERVER['PHP_AUTH_USER']) ) {
      if ( $u = $this->CheckPassword( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ) ) {
        $this->AssignSessionDetails($u);
      }
      else {
        unset($_SERVER['PHP_AUTH_USER']);
      }
    }

    if (!isset($_SERVER['PHP_AUTH_USER'])) {
      header( sprintf( 'WWW-Authenticate: Basic realm="%s"', $c->system_name) );
      header('HTTP/1.0 401 Unauthorized');
      echo 'Please log in for access to this system.';
      dbg_error_log( "BasicAuth", ":Session: User is not authorised" );
      exit;
    }
  }


  /**
  * CheckPassword does all of the password checking and
  * returns a user record object, or false if it all ends in tears.
  */
  function CheckPassword( $username, $password ) {
    $qry = new PgQuery( "SELECT * FROM usr WHERE lower(username) = ? ", $username );
    if ( $qry->Exec('BasicAuth',__LINE__,__FILE__) && $qry->rows == 1 ) {
      $usr = $qry->Fetch();
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
  }


}

$session = new BasicAuthSession();

?>