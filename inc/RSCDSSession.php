<?php
/**
* Session handling class and associated functions
*
* This subpackage provides some functions that are useful around web
* application session management.
*
* The class is intended to be as lightweight as possible while holding
* all session data in the database:
*  - Session hash is not predictable.
*  - No clear text information is held in cookies.
*  - Passwords are generally salted MD5 hashes, but individual users may
*    have plain text passwords set by an administrator.
*  - Temporary passwords are supported.
*  - Logout is supported
*  - "Remember me" cookies are supported, and will result in a new
*    Session for each browser session.
*
* @package   rscds
* @subpackage   RSCDSSession
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

/**
* All session data is held in the database.
*/
require_once('PgQuery.php');

/**
* @global resource $session
* @name $session
* The session object is global.
*/
$session = 1;  // Fake initialisation

// The Session object uses some (optional) configurable SQL to load
// the records related to the logged-on user...  (the where clause gets added).
// It's very important that someone not be able to externally control this,
// so we make it a function rather than a variable.
/**
* @todo Make this a defined constant
*/
function local_session_sql() {
  $sql = <<<EOSQL
SELECT session.*, usr.*
        FROM session JOIN usr USING(user_no)
EOSQL;
  return $sql;
}

/**
* We extend the AWL Session class.
*/
require_once('Session.php');

Session::_CheckLogout();

/**
* A class for creating and holding session information.
*
* @package   rscds
*/
class RSCDSSession extends Session
{

  /**
  * Create a new RSCDSSession object.
  *
  * We create a Session and extend it with some additional useful RSCDS
  * related information.
  *
  * @param string $sid A session identifier.
  */
  function RSCDSSession( $sid="" ) {
    $this->Session($sid);
  }



  /**
  * Checks whether this user is a banker
  *
  * @return boolean Whether or not the logged in user is a banker
  */
  function IsAdmin() {
    return ( $this->logged_in && isset($this->is_admin) && ($this->is_admin == 't') );
  }


  /**
  * Returns a value for user_no which is within the legal values for this user,
  * using a POST value or a GET value if available and allowed, otherwise using
  * this user's value.
  *
  * @return int The sanitised value of user_no
  */
  function SanitisedUserNo( ) {
    $user_no = 0;
    if ( ! $this->logged_in ) return $user_no;

    $user_no = $this->user_no;
    if ( $this->AllowedTo("Admin") && (isset($_POST['user_no']) || isset($_GET['user_no'])) ) {
      $user_no = intval(isset($_POST['user_no']) ? $_POST['user_no'] : $_GET['user_no'] );
    }
    if ( $user_no == 0 ) $user_no = $this->user_no;
    return $user_no;
  }


/**
* Checks that this user is logged in, and presents a login screen if they aren't.
*
* The function can optionally confirm whether they are a member of one of a list
* of groups, and deny access if they are not a member of any of them.
*
* @param string $groups The list of groups that the user must be a member of one of to be allowed to proceed.
* @return boolean Whether or not the user is logged in and is a member of one of the required groups.
*/
  function LoginRequired( $groups = "" ) {
    global $c, $session, $main_menu, $sub_menu, $tab_menu;

    if ( $this->logged_in && $groups == "" ) return;
    if ( ! $this->logged_in ) {
      $c->messages[] = "You must log in to use this system.";
      include_once("page-header.php");
      if ( function_exists("local_index_not_logged_in") ) {
        local_index_not_logged_in();
      }
      else {
        echo <<<EOHTML
<h1>Log On Please</h1>
<p>For access to the $c->system_name you should log on with
the username and password that have been issued to you.</p>

<p>If you would like to request access, please e-mail $c->admin_email.</p>
EOHTML;
        echo $this->RenderLoginPanel();
      }
    }
    else {
      $valid_groups = split(",", $groups);
      foreach( $valid_groups AS $k => $v ) {
        if ( $this->AllowedTo($v) ) return;
      }
      $c->messages[] = "You are not authorised to use this function.";
      include_once("page-header.php");
    }

    include("page-footer.php");
    exit;
  }
}

$session = new RSCDSSession();
$session->_CheckLogin();

?>