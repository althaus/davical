<?php
/**
* @package rscds
* @author Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst IT Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

// Ensure the configuration starts out as an empty object.
unset($c);

// Default some of the configurable values
$c->sysabbr     = 'rscds';
$c->admin_email = 'andrew@catalyst.net.nz';
$c->system_name = "Really Simple CalDAV Store";
$c->domain_name = $_SERVER['SERVER_NAME'];
$c->images      = "/images";
$c->save_time_zone_defs = 1;

// Kind of private configuration values
$c->total_query_time = 0;

$c->dbg = array( );

require_once("AWLUtilities.php");

dbg_error_log( "core", "==========> method =%s= =%s:%d= =%s= =%s=",
            $_SERVER['REQUEST_METHOD'], $_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT'], $_SERVER['SCRIPT_NAME'], $_SERVER['PATH_INFO']);
if ( file_exists("/etc/rscds/".$_SERVER['SERVER_NAME']."-conf.php") ) {
  include_once("/etc/rscds/".$_SERVER['SERVER_NAME']."-conf.php");
}
else if ( file_exists("../config/config.php") ) {
  include_once("../config/config.php");
}
else {
  include_once("rscds_configuration_missing.php");
  exit;
}

/**
* Force the domain name to what was in the configuration file
*/
$_SERVER['SERVER_NAME'] = $c->domain_name;

include_once("PgQuery.php");

?>