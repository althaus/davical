#!/usr/bin/env php
<?php
/**
 * Script to sync user data from an LDAP server
 */
$script_file = __FILE__;
if ( $argc < 2 ) {

  echo <<<USAGE
Usage:

	$script_file davical.example.com

Where 'davical.example.com' is the hostname of your DAViCal server.

This script can be used to synchronise DAViCal from your LDAP server on a regular
basis to ensure group information is up-to-date.  It's not strictly necessary as
DAViCal's user data will be updated as soon as a new or updated user logs into
DAViCal, but it can be useful to synchronise data for people who have not logged
into DAViCal so that they are visible as potential calendars, for example. 

USAGE;
  exit(1);
}

$_SERVER['SERVER_NAME'] = $argv[1];

chdir(str_replace('/scripts/cron-sync-ldap.php','/htdocs',$script_file));

require_once("./always.php");

sync_LDAP();
sync_LDAP_groups();