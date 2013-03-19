<?php
/**
* Manages PAM repository connection with local imap server help
*
* @package   davical
* @category Technical
* @subpackage   ldap
* @author    Oliver Schulze <oliver@samera.com.py>,
*   		 Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Based on Eric Seigne script drivers_squid_pam.php
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
*/

// The PHP interpreter will die quietly unless satisfied. This provides user feedback instead.
if (!function_exists('imap_open')) {
  die("drivers_imap_pam: php5-imap required.");
}

require_once("auth-functions.php");

class imapPamDrivers
{
  /**#@+
  * @access private
  */

  /**#@-*/


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

  $imap_username = $username;
  if ( function_exists('mb_convert_encoding') ) {
    $imap_username = mb_convert_encoding($imap_username, "UTF7-IMAP",mb_detect_encoding($imap_username));
  }
  else {
    $imap_username = imap_utf7_encode($imap_username);
  }

  //$imap_url = '{localhost:143/imap/notls}';
  //$imap_url = '{localhost:993/imap/ssl/novalidate-cert}';
  $imap_url = $c->authenticate_hook['config']['imap_url'];
  $auth_result = "ERR";
  
  $imap_stream = @imap_open($imap_url, $imap_username, $password, OP_HALFOPEN);
  //print_r(imap_errors());
  if ( $imap_stream ) {
    // disconnect
    imap_close($imap_stream);
    // login ok
    $auth_result = "OK";
  }

  if ( $auth_result == "OK") {
    $principal = new Principal('username',$username);
    if ( ! $principal->Exists() ) {
      dbg_error_log( "PAM", "Principal '%s' doesn't exist in local DB, we need to create it",$username );
      $cmd = "getent passwd '$username'";
      $getent_res = exec($cmd);
      $getent_arr = explode(":", $getent_res);
      $fullname = $getent_arr[4];
      if(empty($fullname)) {
        $fullname = $username;
      }

      $principal->Create( array(
                      'username' => $username,
                      'user_active' => true,
                      'email' => $username . "@" . $c->authenticate_hook['config']['email_base'],
                      'modified' => date('c'),
                      'fullname' => $fullname
              ));
      if ( ! $principal->Exists() ) {
        dbg_error_log( "PAM", "Unable to create local principal for '%s'", $username );
        return false;
      }
      CreateHomeCalendar($username);
    }
    return $principal;
  }
  else {
    dbg_error_log( "PAM", "User %s is not a valid username (or password was wrong)", $username );
    return false;
  }

}
