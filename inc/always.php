<?php

$c->sysabbr     = 'caldav';
$c->admin_email = 'andrew@catalyst.net.nz';
$c->system_name = "Andrew's CalDAV Server";

error_log( $c->sysabbr.": DBG: ======================================= Start $PHP_SELF for $HTTP_HOST on $_SERVER[SERVER_NAME]" );
if ( file_exists("/etc/caldav/".$_SERVER['SERVER_NAME']."-conf.php") ) {
  include_once("/etc/caldav/".$_SERVER['SERVER_NAME']."-conf.php");
}
else if ( file_exists("../config/config.php") ) {
  include_once("../config/config.php");
}
else {
  include_once("caldav_configuration_missing.php");
  exit;
}

include_once("iCalendar.php");

?>