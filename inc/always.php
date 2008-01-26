<?php
/**
* @package rscds
* @author Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

// Ensure the configuration starts out as an empty object.
unset($c);

// Ditto for a few other global things
unset($session); unset($request); unset($dbconn);

// Default some of the configurable values
$c->sysabbr     = 'davical';
$c->admin_email = 'admin@davical.example.com';
$c->system_name = "DAViCal CalDAV Server";
$c->domain_name = $_SERVER['SERVER_NAME'];
$c->save_time_zone_defs = true;
$c->collections_always_exist = true;
$c->home_calendar_name = 'home';
$c->enable_row_linking = true;
$c->http_auth_mode = 'Basic';
// $c->default_locale = array('es_MX', 'es_MX.UTF-8', 'es');
// $c->local_tzid = 'Pacific/Auckland';  // Perhaps we should read from /etc/timezone - I wonder how standard that is?
$c->default_locale = "en_NZ";
$c->base_url = preg_replace("#/[^/]+\.php.*$#", "", $_SERVER['SCRIPT_NAME']);
$c->base_directory = preg_replace("#/[^/]*$#", "", $_SERVER['DOCUMENT_ROOT']);

$c->stylesheets = array( $c->base_url."/rscds.css" );
$c->images      = $c->base_url . "/images";

// Ensure that ../inc is in our included paths as early as possible
set_include_path( '../inc'. PATH_SEPARATOR. get_include_path());

// Kind of private configuration values
$c->total_query_time = 0;

$c->dbg = array();

// Utilities
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
                 ($_SERVER['SCRIPT_NAME'] == '/index.php' ? '' : $_SERVER['SCRIPT_NAME']) );

init_gettext( 'rscds', '../locale' );

/**
* We use @file_exists because things like open_basedir might noisily deny
* access which could break DAViCal completely by causing output to start
* too early.
*/
if ( @file_exists("/etc/davical/".$_SERVER['SERVER_NAME']."-conf.php") ) {
  include_once("/etc/davical/".$_SERVER['SERVER_NAME']."-conf.php");
}
else if ( @file_exists("/etc/rscds/".$_SERVER['SERVER_NAME']."-conf.php") ) {
  include_once("/etc/rscds/".$_SERVER['SERVER_NAME']."-conf.php");
}
else if ( @file_exists("../config/config.php") ) {
  include_once("../config/config.php");
}
else {
  include_once("davical_configuration_missing.php");
  exit;
}
if ( !isset($c->page_title) ) $c->page_title = $c->system_name;

if ( count($c->dbg) > 0 ) {
  // Only log this if debugging of some sort is turned on, somewhere
  @dbg_error_log( "LOG", "==========> method =%s= =%s= =%s= =%s= =%s=",
         $_SERVER['REQUEST_METHOD'], $c->protocol_server_port_script, $_SERVER['PATH_INFO'], $c->base_url, $c->base_directory );
}

/**
* Now that we have loaded the configuration file we can switch to a
* default site locale.  This may be overridden by each user.
*/
awl_set_locale($c->default_locale);

/**
* Work out our version
*
*/
$c->code_version = 0;
$c->version_string = '0.9.4'; // The actual version # is replaced into that during the build /release process
if ( isset($c->version_string) && preg_match( '/(\d+)\.(\d+)\.(\d+)(.*)/', $c->version_string, $matches) ) {
  $c->code_major = $matches[1];
  $c->code_minor = $matches[2];
  $c->code_patch = $matches[3];
  $c->code_version = (($c->code_major * 1000) + $c->code_minor).".".$c->code_patch;
}
dbg_error_log("caldav", "Version (%d.%d.%d) == %s", $c->code_major, $c->code_minor, $c->code_patch, $c->code_version);
header( sprintf("Server: %d.%d", $c->code_major, $c->code_minor) );

/**
* Force the domain name to what was in the configuration file
*/
$_SERVER['SERVER_NAME'] = $c->domain_name;

include_once("PgQuery.php");

$c->schema_version = 0;
$qry = new PgQuery( "SELECT schema_major, schema_minor, schema_patch FROM awl_db_revision ORDER BY schema_id DESC LIMIT 1;" );
if ( $qry->Exec("always") && $row = $qry->Fetch() ) {
  $c->schema_version = doubleval( sprintf( "%d%03d.%03d", $row->schema_major, $row->schema_minor, $row->schema_patch) );
  $c->schema_major = $row->schema_major;
  $c->schema_minor = $row->schema_minor;
  $c->schema_patch = $row->schema_patch;
}


$_known_users_name = array();
$_known_users_id   = array();
/**
* Return a user record identified by a username, caching it for any subsequent lookup
* @param string $username The username of the record to retrieve
* @param boolean $use_cache Whether or not to use the cache (default: yes)
*/
function getUserByName( $username, $use_cache = true ) {
  // Provide some basic caching in case this ends up being overused.
  if ( $use_cache && isset( $_known_users_name[$username] ) ) return $_known_users_name[$username];

  $qry = new PgQuery( "SELECT * FROM usr WHERE lower(username) = lower(?) ", $username );
  if ( $qry->Exec('always',__LINE__,__FILE__) && $qry->rows == 1 ) {
    $_known_users_name[$username] = $qry->Fetch();
    $id = $_known_users_name[$username]->user_no;
    $_known_users_id[$id] = $_known_users_name[$username];
    return $_known_users_name[$username];
  }

  return false;
}


/**
* Return a user record identified by a user_no, caching it for any subsequent lookup
* @param int $user_no The ID of the record to retrieve
* @param boolean $use_cache Whether or not to use the cache (default: yes)
*/
function getUserByID( $user_no, $use_cache = true ) {
  // Provide some basic caching in case this ends up being overused.
  if ( $use_cache && isset( $_known_users_id[$user_no] ) ) return $_known_users_id[$user_no];

  $qry = new PgQuery( "SELECT * FROM usr WHERE user_no = ? ", intval($user_no) );
  if ( $qry->Exec('always',__LINE__,__FILE__) && $qry->rows == 1 ) {
    $_known_users_id[$user_no] = $qry->Fetch();
    $name = $_known_users_id[$user_no]->username;
    $_known_users_name[$name] = $_known_users_id[$user_no];
    return $_known_users_id[$user_no];
  }

  return false;
}


/**
 * Return the HTTP status code description for a given code. Hopefully
 * this is an efficient way to code this.
 * @return string The text for a give HTTP status code, in english
 */
function getStatusMessage($status) {
  switch( $status ) {
    case 100:  $ans = "Continue";                             break;
    case 101:  $ans = "Switching Protocols";                  break;
    case 200:  $ans = "OK";                                   break;
    case 201:  $ans = "Created";                              break;
    case 202:  $ans = "Accepted";                             break;
    case 203:  $ans = "Non-Authoritative Information";        break;
    case 204:  $ans = "No Content";                           break;
    case 205:  $ans = "Reset Content";                        break;
    case 206:  $ans = "Partial Content";                      break;
    case 207:  $ans = "Multi-Status";                         break;
    case 300:  $ans = "Multiple Choices";                     break;
    case 301:  $ans = "Moved Permanently";                    break;
    case 302:  $ans = "Found";                                break;
    case 303:  $ans = "See Other";                            break;
    case 304:  $ans = "Not Modified";                         break;
    case 305:  $ans = "Use Proxy";                            break;
    case 307:  $ans = "Temporary Redirect";                   break;
    case 400:  $ans = "Bad Request";                          break;
    case 401:  $ans = "Unauthorized";                         break;
    case 402:  $ans = "Payment Required";                     break;
    case 403:  $ans = "Forbidden";                            break;
    case 404:  $ans = "Not Found";                            break;
    case 405:  $ans = "Method Not Allowed";                   break;
    case 406:  $ans = "Not Acceptable";                       break;
    case 407:  $ans = "Proxy Authentication Required";        break;
    case 408:  $ans = "Request Timeout";                      break;
    case 409:  $ans = "Conflict";                             break;
    case 410:  $ans = "Gone";                                 break;
    case 411:  $ans = "Length Required";                      break;
    case 412:  $ans = "Precondition Failed";                  break;
    case 413:  $ans = "Request Entity Too Large";             break;
    case 414:  $ans = "Request-URI Too Long";                 break;
    case 415:  $ans = "Unsupported Media Type";               break;
    case 416:  $ans = "Requested Range Not Satisfiable";      break;
    case 417:  $ans = "Expectation Failed";                   break;
    case 422:  $ans = "Unprocessable Entity";                 break;
    case 423:  $ans = "Locked";                               break;
    case 424:  $ans = "Failed Dependency";                    break;
    case 500:  $ans = "Internal Server Error";                break;
    case 501:  $ans = "Not Implemented";                      break;
    case 502:  $ans = "Bad Gateway";                          break;
    case 503:  $ans = "Service Unavailable";                  break;
    case 504:  $ans = "Gateway Timeout";                      break;
    case 505:  $ans = "HTTP Version Not Supported";           break;
    default:   $ans = "Unknown HTTP Status Code '$status'";
  }
  return $ans;
}


/**
* Construct a URL from the supplied dav_name
* @param string $partial_path  The part of the path after the script name
*/
function ConstructURL( $partial_path ) {
  global $c;

  if ( ! isset($c->_url_script_path) ) {
    $c->_url_script_path = (preg_match('#/$#', $c->protocol_server_port_script) ? 'caldav.php' : '');
    $c->_url_script_path = $c->protocol_server_port_script . $c->_url_script_path;
  }

  $url = $c->_url_script_path . $partial_path;
  $url = preg_replace( '#^(https?://.+)//#', '$1/', $url );  // Ensure we don't double any '/'
  $url = preg_replace('#^https?://[^/]+#', '', $url );
  return $url;
}

