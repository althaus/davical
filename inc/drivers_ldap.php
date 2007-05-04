<?php
/**
 * Manages LDAP repository connection
 *
 * @category Technical
 */


class ldapDrivers
{
    /**
     * Holds the LDAP connection parametters
     */
    protected $connect;
    function ldapDrivers($config){
        $this->__construct($config);
    }
    /**
     * Initializes the LDAP connection
     *
     * @param string $host The name of LDAP server
     * @param int $port The port number to use
     *
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

    /*
    function getAllUsers($attributs){
        $entry = ldap_list($this->connect,$this->base_dn,$this->UsrFilter,$attributs);
        if (!ldap_first_entry($this->connect,$entry)) throw new AppException("technicat.ldaptools.NoUserFound",array('filter'=>$filter,'arguments'=>print_r($attributs,1), 'dn'=>$this->base_dn));
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

     function getAllDivisions($attributs,$filter=""){
         if($filter == "") $filter=$this->DivFilter;
         $entry = ldap_list($this->connect,$this->Divdn,$filter,$attributs);
         if (!ldap_first_entry($this->connect,$entry)) throw new AppException("technicat.ldaptools.NoDivisionsFound",array('filter'=>$filter,'arguments'=>print_r($attributs,1), 'dn'=>$this->Divdn));

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

     function DivisionOfUser($attributs,$login){
         //first get dn of the user;
         $entry = ldap_search($this->connect,$this->base_dn,"uid=$login",array('dn'));
         if (!ldap_first_entry($this->connect,$entry)) throw new AppException("technicat.ldaptools.user not found",array('filter'=>"uid=$login",'arguments'=>'dn', 'dn'=>$base_dn));
         $dnUser = ldap_get_dn($this->connect, ldap_first_entry($this->connect,$entry));
         try{
            return $this->getAllDivisions($attributs,"(&($this->DivFilter)(uniqueMember=$dnUser))");
         }catch (AppException $e)
         {
            return array('');
         }
     }*/
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
        if (!ldap_first_entry($this->connect,$entry))
            dbg_error_log( "ERROR", "drivers_ldap : Unable to find the user" );
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
    /*function addToDivision($uid,$usr){
        $userDN = $this->getUserDN($usr);
        $divDN = $this->getDivDN($uid);
        $group_info['uniqueMember'] = $userDN; // User's DN is added to group's 'member' array
        if(!ldap_mod_add($this->connect,$divDN,$group_info)) throw new AppException("technical.ldaptools.unable to add user to group", array('userDN' => $userDN, "DivDN"=>$divDN));

    }

    function addToTennaxia($usr){
        $userDN = $this->getUserDN($usr);
        $entry = ldap_list($this->connect,$this->base_dn,"(&(&($this->DivFilter)(uniqueMember=$userDN))(cn=tennaxia))",array('cn'));
        if (!ldap_first_entry($this->connect,$entry)){
            $divDN = "cn=tennaxia,dc=tennaxia,dc=net";
            $group_info['uniqueMember'] = $userDN; // User's DN is added to group's 'member' array
            if(!ldap_mod_add($this->connect,$divDN,$group_info)) throw new AppException("technical.ldaptools.unable to add user to Tennaxia", array('userDN' => $userDN, "DivDN"=>$divDN));
        }
    }
    function removeFromDivision($uid,$usr){
        $userDN = $this->getUserDN($usr);
        $divDN = $this->getDivDN($uid);
        $group_info['uniqueMember'] = $userDN; // User's DN is added to group's 'member' array
        if(!ldap_mod_del($this->connect,$divDN,$group_info)) throw new AppException("technical.ldaptools.unable to remove user from group", array('userDN' => $userDN, "DivDN"=>$divDN));

    }
    function getUserDN($login){
        $entry = ldap_search($this->connect,$this->base_dn,"(&($this->UsrFilter)(uid=$login))",array('dn'));
        if (!$i=ldap_first_entry($this->connect,$entry)) throw new AppException("technicat.ldaptools.NoUserFound",array('filter'=>$this->UsrFilter,'arguments'=>print_r($args,1), 'domain'=>$this->base_dn));
        return ldap_get_dn($this->connect, $i);
    }
    function getDivDN($entryUUID){
        $entry = ldap_search($this->connect,$this->base_dn,"(&($this->DivFilter)(entryUUID=$entryUUID))",array('dn'));
        if (!$i=ldap_first_entry($this->connect,$entry)) throw new AppException("technicat.ldaptools.NoUserFound",array('filter'=>$this->UsrFilter,'arguments'=>print_r($args,1), 'domain'=>$this->base_dn));
        return ldap_get_dn($this->connect, $i);
    }*/
}


// A generic function to create and fetch static objects
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
function LDAP_check($username, $password ){
    global $c;
    $mapping = $c->authenticate_hook['config']['mapping_field'];
    $filter="uid=$username";
    $attributs=array_values($mapping);
    $ldapDriver = getStaticLdap();
    if($ldapDriver->valid){
        dbg_error_log( "LDAP", "checking user %s for password %s against LDAP",$username,$password );
        $valid = $ldapDriver->requestUser($filter,$attributs,$password);
        //is a valid user or not
        if (!$valid)
            return false;
        //ok it is valid is already exist in db ?
        $qry = new PgQuery( "SELECT * FROM usr WHERE lower(username) = ? ", $username );
        if ( $qry->Exec('BasicAuth',__LINE__,__FILE__) && $qry->rows == 1 )
          return $qry->Fetch();
        //it doesn't exist so we create the new user
        //$user = new RSCDSUser($user_no);
        dbg_error_log( "LDAP", "From LDAP User Name => %s, Full Name => %s ,EMail => %s",$valid[$mapping["User Name"]],$valid[$mapping["Full Name"]],$valid[$mapping["EMail"]]);

    }
}
?>