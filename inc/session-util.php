<?php
/**
* @package   awl
* @subpackage   Session
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst IT Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

if ( !function_exists("session_salted_md5") ) {
  /**
  * Make a salted MD5 string, given a string and (possibly) a salt.
  *
  * If no salt is supplied we will generate a random one.
  *
  * @param string $instr The string to be salted and MD5'd
  * @param string $salt Some salt to sprinkle into the string to be MD5'd so we don't get the same PW always hashing to the same value.
  * @return string The salt, a * and the MD5 of the salted string, as in SALT*SALTEDHASH
  */
  function session_salted_md5( $instr, $salt = "" ) {
    if ( $salt == "" ) $salt = substr( md5(rand(100000,999999)), 2, 8);
    dbg_error_log( "Login", "Making salted MD5: salt=$salt, instr=$instr, md5($salt$instr)=".md5($salt . $instr) );
    return ( sprintf("*%s*%s", $salt, md5($salt . $instr) ) );
  }
}


if ( !function_exists("session_validate_password") ) {
  /**
  * Checks what a user entered against the actual password on their account.
  * @param string $they_sent What the user entered.
  * @param string $we_have What we have in the database as their password.  Which may (or may not) be a salted MD5.
  * @return boolean Whether or not the users attempt matches what is already on file.
  */
  function session_validate_password( $they_sent, $we_have ) {
    global $debuggroups, $session;

    if ( ereg('^\*\*.+$', $we_have ) ) {
      //  The "forced" style of "**plaintext" to allow easier admin setting
      return ( "**$they_sent" == $we_have );
    }

    if ( ereg('^\*(.+)\*.+$', $we_have, $regs ) ) {
      // A nicely salted md5sum like "*<salt>*<salted_md5>"
      $salt = $regs[1];
      $md5_sent = session_salted_md5( $they_sent, $salt ) ;
      return ( $md5_sent == $we_have );
    }

    // Anything else is bad
    return false;

  }
}


if ( !function_exists("replace_uri_params") ) {
  /**
  * Given a URL (presumably the current one) and a parameter, replace the value of parameter,
  * extending the URL as necessary if the parameter is not already there.
  * @param string $uri The URI we will be replacing parameters in.
  * @param array $replacements An array of replacement pairs array( "replace_this" => "with this" )
  * @return string The URI with the replacements done.
  */
  function replace_uri_params( $uri, $replacements ) {
    $replaced = $uri;
    foreach( $replacements AS $param => $new_value ) {
      $rxp = preg_replace( '/([\[\]])/', '\\\\$1', $param );  // Some parameters may be arrays.
      $regex = "/([&?])($rxp)=([^&]+)/";
      dbg_error_log("core", "Looking for [%s] to replace with [%s] regex is %s and searching [%s]", $param, $new_value, $regex, $replaced );
      if ( preg_match( $regex, $replaced ) )
        $replaced = preg_replace( $regex, "\$1$param=$new_value", $replaced);
      else
        $replaced .= "&$param=$new_value";
    }
    if ( ! preg_match( '/\?/', $replaced  ) ) {
      $replaced = preg_replace("/&(.+)$/", "?\$1", $replaced);
    }
    $replaced = str_replace("&amp;", "--AmPeRsAnD--", $replaced);
    $replaced = str_replace("&", "&amp;", $replaced);
    $replaced = str_replace("--AmPeRsAnD--", "&amp;", $replaced);
    dbg_error_log("core", "URI <<$uri>> morphed to <<$replaced>>");
    return $replaced;
  }
}
?>