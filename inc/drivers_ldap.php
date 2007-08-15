<?php
/**
* Manages LDAP repository connection
*
* @package   rscds
* @category Technical
* @subpackage   caldav
* @author    Maxime Delorme <mdelorme@tennaxia.net>
* @copyright Maxime Delorme
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/


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
      if ($port) $this->connect=ldap_connect($host, $port);
      else $this->connect=ldap_connect($host);
      if (! $this->connect){
          $c->messages[] = sprintf(i18n( "drivers_ldap : Unable to connect to LDAP with port %s on host %s"), $port,$host );
          $this->valid=false;
          return ;
      }

      //Set LDAP protocol version
      if (isset($config['protocolVersion'])) ldap_set_option($this->connect,LDAP_OPT_PROTOCOL_VERSION, $config['protocolVersion']);

      //connect as root
      if (!ldap_bind($this->connect,$config['bindDN'],$config['passDN'])){
          $c->messages[] = sprintf(i18n( "drivers_ldap : Unable to bind to LDAP, check your bindDN >%s< and passDN >%s< of your configuration or if your server is reachable"),$config['bindDN'],$config['passDN'] );
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
  function getAllUsers($attributs){
    global $c;
    $entry = ldap_list($this->connect,$this->baseDNUsers,$this->filterUsers,$attributs);
    if (!ldap_first_entry($this->connect,$entry))
            $c->messages[] = sprintf(i18n("Error NoUserFound with filter >%s<, attributs >%s< , dn >%s<"),$this->filterUsers,join(', ',$attributs), $this->baseDNUsers);
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
    * @param array $attributs Attributes to be returned
    * @param string $passwd password to check
    * @return array Contains selected attributes from all entries corresponding to the given filter
    */
  function requestUser($filter,$attributs=NULL,$passwd)
  {

      $entry=NULL;
      // We get the DN of the USER
      $entry = ldap_search($this->connect,$this->baseDNUsers,$filter,$attributs);
      if ( !ldap_first_entry($this->connect,$entry) ){
          dbg_error_log( "ERROR", "drivers_ldap : Unable to find the user with filter %s",$filter );
          return false;
      }
      $dnUser = ldap_get_dn($this->connect, ldap_first_entry($this->connect,$entry));
      if(!@ldap_bind($this->connect,$dnUser,$passwd))
          return false;

      $i=ldap_first_entry($this->connect,$entry);
      $arr=ldap_get_attributes($this->connect,$i);
      for($i=0;$i<$arr['count'];$i++){
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
* Check the username / password against the LDAP server
*/
function LDAP_check($username, $password ){
  global $c;
  $ldapDriver = getStaticLdap();
  if ( $ldapDriver->valid ) {
    $mapping = $c->authenticate_hook['config']['mapping_field'];
    $attributs=array_values($mapping);
    $filter="(& $ldapDriver->filterUsers (".$mapping["username"]."=$username))";
    dbg_error_log( "LDAP", "checking user %s for password %s against LDAP",$username,$password );
    $valid = $ldapDriver->requestUser($filter,$attributs,$password);

    //is a valid user or not
    if ( !$valid ) return false;


    $ldap_timestamp = $valid[$mapping["updated"]];
    foreach($c->authenticate_hook['config']['format_updated'] as $k => $v)
      $$k = substr($ldap_timestamp,$v[0],$v[1]);

    //ok it is valid is already exist in db ?
    $ldap_timestamp = "$Y"."$m"."$d"."$H"."$M"."$S";
    if ( $usr = getUserByName($username) ){
      //should we update it ?
      $db_timestamp = $usr->updated;
      $db_timestamp = substr(strtr($db_timestamp, array(':' => '',' '=>'','-'=>'')),0,14);
      if($ldap_timestamp <= $db_timestamp){
          return $usr;//no need to update
      }
      //we should update the user record
    }

    //it doesn't exist so we create the new user or if we should be updated the user record
    require_once("RSCDSUser.php");

    $user_no = ( isset($usr->user_no) ? $usr->user_no:0);
    $user = new RSCDSUser($user_no);
    $validUserField = array_keys($user->Fields);

    foreach($c->authenticate_hook['config']['default_value'] as $field => $value)
      if(in_array($field,$validUserField)) $user->Set($field, $value);

    foreach($mapping as $field => $value)
      if(in_array($field,$validUserField)) $user->Set($field, $valid[$value]);

    $user->Set("updated", "$Y-$m-$d $H:$M:$S");
    $user->write();
  }

  if ( $return = getUserByName($username) ){
    return $return;
  }
}
/**
* sync LDAP against the DB
*/
function sync_LDAP(){
  global $c;
  $ldapDriver = getStaticLdap();
  if($ldapDriver->valid){
    $mapping = $c->authenticate_hook['config']['mapping_field'];
    $attributs=array_values($mapping);
    $ldap_users_tmp = $ldapDriver->getAllUsers($attributs);
    foreach($ldap_users_tmp as $key => $ldap_user){
      $ldap_users_info[$ldap_user[$mapping["username"]]] = $ldap_user;
      unset($ldap_users_tmp[$key]);
    }
    $qry = new PgQuery( "SELECT username ,user_no, updated FROM usr ");
    $qry->Exec('sync_LDAP',__LINE__,__FILE__);
    while($db_user = $qry->Fetch(true)){
      $db_users[] = $db_user['username'];
      $db_users_info[$db_user['username']] = array('user_no' => $db_user['user_no'], 'updated' => $db_user['updated']);
    }
    require_once("RSCDSUser.php");

    $ldap_users = array_keys($ldap_users_info);
    //users only in ldap
    $users_to_create = array_diff($ldap_users,$db_users);
    //users only in db
    $users_to_desactivate = array_diff($db_users,$ldap_users);
    //users present in ldap and in the db
    $users_to_update = array_intersect($db_users,$ldap_users);

    //creation of all users;
    if(sizeof($users_to_create)) $c->messages[] = sprintf(i18n('- creating record for users :  %s'),join(', ',$users_to_create));
    foreach($users_to_create as $username){
      $valid=$ldap_users_info[$username];
      $ldap_timestamp = $valid[$mapping["updated"]];
      foreach($c->authenticate_hook['config']['format_updated'] as $k => $v)
          $$k = substr($ldap_timestamp,$v[0],$v[1]);
      $user = new RSCDSUser(0);
      $validUserField = array_keys($user->Fields);
      foreach($c->authenticate_hook['config']['default_value'] as $field => $value)
          if(in_array($field,$validUserField)) $user->Set($field, $value);
      foreach($mapping as $field => $value)
          if(in_array($field,$validUserField)) $user->Set($field, $valid[$value]);
      $user->Set("updated", "$Y-$m-$d $H:$M:$S");
      $messages = $c->messages;
      $user->write();
      $c->messages = $messages;
    }
    //desactivating all users
    if(sizeof($users_to_desactivate)){
      foreach( $users_to_desactivate AS $v ) {
          $usr_in .= ', ' . qpg($v);
      }
      $usr_in = substr($usr_in,1);
      $c->messages[] = sprintf(i18n('- desactivating users : %s'),$usr_in);
      $qry = new PgQuery( "UPDATE usr set active = FALSE WHERE lower(username) in ($usr_in)");
      $qry->Exec('sync_LDAP',__LINE__,__FILE__);
    }
    //updating all users
    foreach($users_to_update as $key=> $username){
      $valid=$ldap_users_info[$username];
      $ldap_timestamp = $valid[$mapping["updated"]];
      foreach($c->authenticate_hook['config']['format_updated'] as $k => $v)
          $$k = substr($ldap_timestamp,$v[0],$v[1]);
      $ldap_timestamp = "$Y"."$m"."$d"."$H"."$M"."$S";
      $db_timestamp = substr(strtr($db_users_info[$username]['updated'], array(':' => '',' '=>'','-'=>'')),0,14);
      if($ldap_timestamp > $db_timestamp){
        $user = new RSCDSUser($db_users_info[$username]['user_no']);
        $validUserField = array_keys($user->Fields);
        foreach($c->authenticate_hook['config']['default_value'] as $field => $value)
            if(in_array($field,$validUserField)) $user->Set($field, $value);
        foreach($mapping as $field => $value)
            if(in_array($field,$validUserField)) $user->Set($field, $valid[$value]);
        $user->Set("updated", "$Y-$m-$d $H:$M:$S");
        $user->write();
      } else {
        unset($users_to_update[$key]);
        $users_nothing_done[] = $username;
      }
    }
    if(sizeof($users_to_update))   $c->messages[] = sprintf(i18n('- updating user record %s'),join(', ',$users_to_update));
    if(sizeof($users_nothing_done))$c->messages[] = sprintf(i18n('- nothing done on %s'),join(', ', $users_nothing_done));
  }
}
?>
