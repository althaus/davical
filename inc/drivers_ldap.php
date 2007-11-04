<?php
/**
* Manages LDAP repository connection
*
* @package   davical
* @category Technical
* @subpackage   ldap
* @author    Maxime Delorme <mdelorme@tennaxia.net>
* @copyright Maxime Delorme
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

require_once("auth-functions.php");

class ldapDrivers
{
  /**#@+
  * @access private
  */

  /**
  * Holds the LDAP connection parameters
  */
  var $connect;

  /**#@-*/


  /**
  * Constructor.
  * @param array $config The configuration data
  */
  function ldapDrivers($config){
      $this->__construct($config);
  }


  /**
  * Initializes the LDAP connection
  *
  * @param array $config The configuration data
  */
  function __construct($config)
  {
      global $c;
      $host=$config['host'];
      $port=$config['port'];
      if(!function_exists('ldap_connect')){
          $c->messages[] = i18n("drivers_ldap : function ldap_connect not defined, check your php_ldap module");
          $this->valid=false;
          return ;
      }
      if ($port)
          $this->connect=ldap_connect($host, $port);
      else
          $this->connect=ldap_connect($host);

      if (! $this->connect){
          $c->messages[] = sprintf(i18n( "drivers_ldap : Unable to connect to LDAP with port %s on host %s"), $port,$host );
          $this->valid=false;
          return ;
      }

      dbg_error_log( "LDAP", "drivers_ldap : Connected to LDAP server %s",$host );

      //Set LDAP protocol version
      if (isset($config['protocolVersion'])) ldap_set_option($this->connect,LDAP_OPT_PROTOCOL_VERSION, $config['protocolVersion']);

      //connect as root
      if (!ldap_bind($this->connect,$config['bindDN'],$config['passDN'])){
          $bindDN = isset($config['bindDN']) ? $config['bindDN'] : 'anonymous';
          $passDN = isset($config['passDN']) ? $config['passDN'] : 'anonymous';
          $c->messages[] = sprintf(i18n( "drivers_ldap : Unable to bind to LDAP, check your bindDN >%s< and passDN >%s< of your configuration or if your server is reachable"),$bindDN,$passDN );
          $c->messages[] = sprintf(i18n( "if your use OpenLDAP 2.X.X maybe, unable to connect to LDAP with port %s on host %s"), $port,$host );
          $this->valid=false;
          return ;
      }
      $this->valid = true;
      //root to start search
      $this->baseDNUsers  = $config['baseDNUsers'];
      $this->filterUsers  = $config['filterUsers'];
      $this->baseDNGroups = $config['baseDNGroups'];
      $this->filterGroups = $config['filterGroups'];
  }

  /**
  * Retrieve all users from the LDAP directory
  */
  function getAllUsers($attributes){
    global $c;
    $entry = ldap_list($this->connect,$this->baseDNUsers,$this->filterUsers,$attributes);
    if (!ldap_first_entry($this->connect,$entry))
            $c->messages[] = sprintf(i18n("Error NoUserFound with filter >%s<, attributes >%s< , dn >%s<"),$this->filterUsers,join(', ',$attributes), $this->baseDNUsers);
    for($i=ldap_first_entry($this->connect,$entry);
        $i&&$arr=ldap_get_attributes($this->connect,$i);
        $i=ldap_next_entry($this->connect,$i)
    )
    {
      for($j=0;$j<$arr['count'];$j++){
          $row[$arr[$j]] = $arr[$arr[$j]][0];
      }
      $ret[]=$row;
    }
    return $ret;
  }

  /**
    * Returns the result of the LDAP query
    *
    * @param string $filter The filter used to search entries
    * @param array $attributes Attributes to be returned
    * @param string $passwd password to check
    * @return array Contains selected attributes from all entries corresponding to the given filter
    */
  function requestUser( $filter, $attributes=NULL, $passwd) {

    $entry=NULL;
    // We get the DN of the USER
    $entry = ldap_search($this->connect, $this->baseDNUsers, $filter,$attributes);
    if ( !ldap_first_entry($this->connect, $entry) ){
      dbg_error_log( "ERROR", "drivers_ldap : Unable to find the user with filter %s",$filter );
      return false;
    } else {
      dbg_error_log( "LDAP", "drivers_ldap : Found a user using filter %s",$filter );
    }

    $dnUser = ldap_get_dn($this->connect, ldap_first_entry($this->connect,$entry));
    if ( !@ldap_bind($this->connect, $dnUser, $passwd) ) {
      dbg_error_log( "LDAP", "drivers_ldap : Failed to bind to user %s using password %s", $dnUser, $passwd );
      return false;
    }

    dbg_error_log( "LDAP", "drivers_ldap : Bound to user %s using password %s", $dnUser, $passwd );

    $i = ldap_first_entry($this->connect,$entry);
    $arr = ldap_get_attributes($this->connect,$i);
    for( $i=0; $i<$arr['count']; $i++ ) {
      $ret[$arr[$i]]=$arr[$arr[$i]][0];
    }
    return $ret;

  }
}


/**
* A generic function to create and fetch static objects
*/
function getStaticLdap() {
  global $c;
  // Declare a static variable to hold the object instance
  static $instance;

  // If the instance is not there, create one
  if(!isset($instance)) {
    $ldapDrivers =& new ldapDrivers($c->authenticate_hook['config']);
  }
  return $ldapDrivers;
}


/**
* Synchronise a cached user with one from LDAP
* @param object $usr A user record to be updated (or created)
*/
function sync_user_from_LDAP( &$usr, $mapping, $ldap_values ) {
  global $c;

  dbg_error_log( "LDAP", "Going to sync the user from LDAP" );
  $validUserFields = get_fields('usr');

  foreach ( $c->authenticate_hook['config']['default_value'] as $field => $value ) {
    if ( in_array($field, $validUserFields) ) {
      $usr->{$field} =  $value;
      dbg_error_log( "LDAP", "Setting usr->%s to %s from configured defaults", $field, $value );
    }
  }

  foreach ( $mapping as $field => $value ) {
    dbg_error_log( "LDAP", "Considering copying %s", $field );
    if ( in_array($field, $validUserFields) ) {
      $usr->{$field} =  $ldap_values[$value];
      dbg_error_log( "LDAP", "Setting usr->%s to %s from LDAP field %s", $field, $ldap_values[$value], $value );
    }
  }

  UpdateUserFromExternal( $usr );
}


/**
* Check the username / password against the LDAP server
*/
function LDAP_check($username, $password ){
  global $c;

  $ldapDriver = getStaticLdap();
  if ( !$ldapDriver->valid ) {
    dbg_error_log( "ERROR", "Couldn't contact LDAP server for authentication" );
    return false;
  }

  $mapping = $c->authenticate_hook['config']['mapping_field'];
  $attributes = array_values($mapping);

  /**
  * If the config contains a filter that starts with a ( then believe
  * them and don't modify it, otherwise wrap the filter.
  */
  $filter_munge = "";
  if ( preg_match( '/^\(/', $ldapDriver->filterUsers ) ) {
    $filter_munge = $ldapDriver->filterUsers;
  }
  else {
    $filter_munge = "($ldapDriver->filterUsers)";
  }

  $filter = "(&$filter_munge(".$mapping["username"]."=$username))";
  dbg_error_log( "LDAP", "checking user %s for password %s against LDAP",$username,$password );
  $valid = $ldapDriver->requestUser( $filter, $attributes, $password );

  // is a valid user or not
  if ( !$valid ) {
    dbg_error_log( "LDAP", "user %s is not a valid user",$username );
    return false;
  }

  $ldap_timestamp = $valid[$mapping["updated"]];

  /**
  * This splits the LDAP timestamp apart and assigns values to $Y $m $d $H $M and $S
  */
  foreach($c->authenticate_hook['config']['format_updated'] as $k => $v)
    $$k = substr($ldap_timestamp,$v[0],$v[1]);

  $ldap_timestamp = "$Y"."$m"."$d"."$H"."$M"."$S";
  $valid[$mapping["updated"]] = "$Y-$m-$d $H:$M:$S";

  if ( $usr = getUserByName($username) ) {
    // should we update it ?
    $db_timestamp = $usr->updated;
    $db_timestamp = substr(strtr($db_timestamp, array(':' => '',' '=>'','-'=>'')),0,14);
    if($ldap_timestamp <= $db_timestamp) {
        return $usr; // no need to update
    }
    // we will need to update the user record
  }
  else {
    dbg_error_log( "LDAP", "user %s doesn't exist in local DB, we need to create it",$username );
    $usr = (object) array( 'user_no' => 0 );
  }

  // The local cached user doesn't exist, or is older, so we create/update their details
  sync_user_from_LDAP($usr, $mapping, $valid );

  return $usr;

}


/**
* sync LDAP against the DB
*/
function sync_LDAP(){
  global $c;
  $ldapDriver = getStaticLdap();
  if($ldapDriver->valid){
    $mapping = $c->authenticate_hook['config']['mapping_field'];
    $attributes = array_values($mapping);
    $ldap_users_tmp = $ldapDriver->getAllUsers($attributes);
    foreach($ldap_users_tmp as $key => $ldap_user){
      $ldap_users_info[$ldap_user[$mapping["username"]]] = $ldap_user;
      unset($ldap_users_tmp[$key]);
    }
    $qry = new PgQuery( "SELECT username, user_no, updated FROM usr ");
    $qry->Exec('sync_LDAP',__LINE__,__FILE__);
    while($db_user = $qry->Fetch(true)){
      $db_users[] = $db_user['username'];
      $db_users_info[$db_user['username']] = array('user_no' => $db_user['user_no'], 'updated' => $db_user['updated']);
    }
    include_once("RSCDSUser.php");

    $ldap_users = array_keys($ldap_users_info);
    // users only in ldap
    $users_to_create = array_diff($ldap_users,$db_users);
    // users only in db
    $users_to_deactivate = array_diff($db_users,$ldap_users);
    // users present in ldap and in the db
    $users_to_update = array_intersect($db_users,$ldap_users);

    // creation of all users;
    if ( sizeof($users_to_create) ) {
      $c->messages[] = sprintf(i18n('- creating record for users :  %s'),join(', ',$users_to_create));

      foreach( $users_to_create as $username ) {
        $user = (object) array( 'user_no' => 0, 'username' => $username );
        $valid = $ldap_users_info[$username];
        $ldap_timestamp = $valid[$mapping["updated"]];

        /**
        * This splits the LDAP timestamp apart and assigns values to $Y $m $d $H $M and $S
        */
        foreach($c->authenticate_hook['config']['format_updated'] as $k => $v)
            $$k = substr($ldap_timestamp,$v[0],$v[1]);
        $ldap_timestamp = "$Y"."$m"."$d"."$H"."$M"."$S";
        $valid[$mapping["updated"]] = "$Y-$m-$d $H:$M:$S";

        sync_user_from_LDAP( $user, $mapping, $valid );
      }
    }

    // deactivating all users
    if ( sizeof($users_to_deactivate) ) {
      foreach( $users_to_deactivate AS $v ) {
          $usr_in .= ', ' . qpg($v);
      }
      $usr_in = substr($usr_in,1);
      $c->messages[] = sprintf(i18n('- deactivating users : %s'),$usr_in);
      $qry = new PgQuery( "UPDATE usr SET active = FALSE WHERE lower(username) IN ($usr_in)");
      $qry->Exec('sync_LDAP',__LINE__,__FILE__);
    }

    // updating all users
    if ( sizeof($users_to_update) ) {
      foreach ( $users_to_update as $key=> $username ) {
        $valid=$ldap_users_info[$username];
        $ldap_timestamp = $valid[$mapping["updated"]];

        /**
        * This splits the LDAP timestamp apart and assigns values to $Y $m $d $H $M and $S
        */
        foreach($c->authenticate_hook['config']['format_updated'] as $k => $v)
            $$k = substr($ldap_timestamp,$v[0],$v[1]);
        $ldap_timestamp = "$Y"."$m"."$d"."$H"."$M"."$S";
        $valid[$mapping["updated"]] = "$Y-$m-$d $H:$M:$S";

        $db_timestamp = substr(strtr($db_users_info[$username]['updated'], array(':' => '',' '=>'','-'=>'')),0,14);
        if ( $ldap_timestamp > $db_timestamp ) {
          sync_user_from_LDAP($usr, $mapping, $valid );
        }
        else {
          unset($users_to_update[$key]);
          $users_nothing_done[] = $username;
        }
      }
      $c->messages[] = sprintf(i18n('- updating user record %s'),join(', ',$users_to_update));
      if ( sizeof($users_nothing_done) )
        $c->messages[] = sprintf(i18n('- nothing done on %s'),join(', ', $users_nothing_done));
    }
  }
}
