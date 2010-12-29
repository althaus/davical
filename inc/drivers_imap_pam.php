<?php
/**
* Manages PAM repository connection with local imap server help
*
* @package   davical
* @category Technical
* @subpackage   ldap
* @author    Oliver Schulze <oliver@samera.com.py>
* @copyright Based on Eric Seigne script drivers_squid_pam.php
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

require_once("auth-functions.php");

class imapPamDrivers
{
  /**#@+
  * @access private
  */

  /**#@-*/


  /**
  * Constructor.
  * @param string $imap_url formated for imap_open()
  */
  function imapPamDrivers($imap_url){
      $this->__construct($imap_url);
  }


  /**
  * The constructor
  *
  * @param string $imap_url formated for imap_open()
  */
  function __construct($imap_url)
  {
      global $c;
      if (empty($imap_url)){
          $c->messages[] = sprintf(i18n('drivers_imap_pam : imap_url parameter not configured in /etc/davical/*-conf.php'));
          $this->valid=false;
          return ;
      }
  }
}


/**
* Check the username / password against the PAM system
*/
function IMAP_PAM_check($username, $password ){
  global $c;

  /**
  * @todo Think of the children!  This is a horribly insecure use of unvalidated user input!  Probably it should be done with a popen or something, and it seems remarkably dodgy to expect that naively quoted strings will work in any way reliably.
  * Meanwhile, I've quickly hacked something basic in place to improve the situation.  No quotes/backslashes in passwords for YOU!
  */

	$username_ori = $username;
	$username = escapeshellcmd($username);
	//$password = escapeshellcmd($password);

	//$imap_url = '{localhost:143/imap/notls}';
	//$imap_url = '{localhost:993/imap/ssl/novalidate-cert}';
	$imap_url = $c->authenticate_hook['config']['imap_url'];
	$auth_result = "ERR";

	$imap_stream = @imap_open($imap_url, $username, $password, OP_HALFOPEN);
	//print_r(imap_errors());
	if ( $imap_stream ) {
		// disconnect
		imap_close($imap_stream);
		// login ok
		$auth_result = "OK";
	}

  if ( $auth_result == "OK") {
    if ( $usr = getUserByName($username) ) {
      return $usr;
    }
    else {
      dbg_error_log( "PAM", "user %s doesn't exist in local DB, we need to create it",$username );
      $cmd = "getent passwd '$username'";
      $getent_res = exec($cmd);
			$getent_arr = explode(":", $getent_res);
			$fullname = $getent_arr[4];
			if(empty($fullname)) {
				$fullname = $username;
			}
      $usr = (object) array(
              'user_no' => 0,
              'username' => $username,
              'active' => 't',
              'email' => $username . "@" . $c->authenticate_hook['config']['email_base'],
              'updated' => date(),
              'fullname' => $fullname
      );

      UpdateUserFromExternal( $usr );
      return $usr;
    }
  }
  else {
    dbg_error_log( "PAM", "User %s is not a valid username (or password was wrong)", $username );
    return false;
  }

}
