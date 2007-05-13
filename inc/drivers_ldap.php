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
      $host=$config['host'];
      $port=$config['port'];
      if(!function_exists('ldap_connect')){
          dbg_error_log( "ERROR", "drivers_ldap : function ldap_connect not defined, check your php_ldap module");
          $this->valid=false;
          return ;
      }
      if ($port) $this->connect=ldap_connect($host, $port);
      else $this->connect=ldap_connect($host);
      if (! $this->connect){
          dbg_error_log( "ERROR", "drivers_ldap : Unable to connect to LDAP with port %s on host %s", $port,$host );
          $this->valid=false;
          return ;
      }
      //connect as root
      if (!ldap_bind($this->connect,$config['bindDN'],$config['passDN'])){
          dbg_error_log( "ERROR", "drivers_ldap : Unable to bind to LDAP, check your bindDN >%s< and passDN >%s< of your configuration",$config['bindDN'],$config['passDN'] );
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
          dbg_error_log( "ERROR", "drivers_ldap : Unable to find the user" );
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
  $mapping = $c->authenticate_hook['config']['mapping_field'];
  $filter = $mapping["username"]."=$username";
  $attributs = array_values($mapping);
  $ldapDriver = getStaticLdap();
  if ( $ldapDriver->valid ) {
    dbg_error_log( "LDAP", "checking user %s for password %s against LDAP", $username, $password );
    $valid = $ldapDriver->requestUser($filter,$attributs,$password);

    //is a valid user or not
    if ( !$valid ) return false;

    //ok it is valid is already exist in db ?
    $qry = new PgQuery( "SELECT * FROM usr WHERE lower(username) = ? ", $username );

    $ldap_timestamp = $valid[$mapping["updated"]];
    foreach($c->authenticate_hook['config']['format_udpated'] as $k => $v)
      $$k = substr($ldap_timestamp,$v[0],$v[1]);

    $ldap_timestamp = "$Y"."$m"."$d"."$H"."$M"."$S";
    if ( $qry->Exec('BasicAuth',__LINE__,__FILE__) && $qry->rows == 1 ){
      //should we update it ?
      $usr = $qry->Fetch();
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

  $qry = new PgQuery( "SELECT * FROM usr WHERE lower(username) = ? ", $username );
  if ( $qry->Exec('BasicAuth',__LINE__,__FILE__) && $qry->rows == 1 ){
    return $qry->Fetch();
  }
}

?>