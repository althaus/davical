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
$c->stylesheets = array( "/rscds.css" );
$c->collections_always_exist = true;


// Kind of private configuration values
$c->total_query_time = 0;

$c->dbg = array( );

require_once("AWLUtilities.php");

/**
* Calculate the simplest form of reference to this page, excluding the PATH_INFO following the script name.
*/
$c->protocol_server_port_script = sprintf( "%s://%s%s%s", (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on'? 'https' : 'http'), $_SERVER['SERVER_NAME'],
                 (
                   ( (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on') && $_SERVER['SERVER_PORT'] == 80 )
                           || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' && $_SERVER['SERVER_PORT'] == 443 )
                   ? ''
                   : ':'.$_SERVER['SERVER_PORT']
                 ),
                 $_SERVER['SCRIPT_NAME'] );

dbg_error_log( "LOG", "==========> method =%s= =%s= =%s=", $_SERVER['REQUEST_METHOD'], $c->protocol_server_port_script, $_SERVER['PATH_INFO']);

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
* Figure our version from the changelog
*/
$c->code_version = 0;
$changelog = false;
if ( file_exists("../debian/changelog") ) {
  $changelog = fopen( "../debian/changelog", "r" );
}
else if ( file_exists("/usr/share/doc/rscds/changelog.Debian") ) {
  $changelog = fopen( "/usr/share/doc/rscds/changelog.Debian", "r" );
}
else if ( file_exists("/usr/share/doc/rscds/changelog") ) {
  $changelog = fopen( "/usr/share/doc/rscds/changelog", "r" );
}
if ( $changelog ) {
  list( $c->code_pkgver, $c->code_major, $c->code_minor, $c->code_patch, $c->code_debian ) = fscanf($changelog, "%s (%d.%d.%d-%d)");
  $c->code_version = (($c->code_major * 1000) + $c->code_minor).".".$c->code_patch;
  fclose($changelog);
}
dbg_error_log("caldav", "Version %s (%d.%d.%d-%d) == %s", $c->code_pkgver, $c->code_major, $c->code_minor, $c->code_patch, $c->code_debian, $c->code_version);
header( sprintf("Server: %s/%d.%d", $c->code_pkgver, $c->code_major, $c->code_minor) );


/**
* Force the domain name to what was in the configuration file
*/
$_SERVER['SERVER_NAME'] = $c->domain_name;

include_once("PgQuery.php");

?>