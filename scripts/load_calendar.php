#!/usr/bin/env php
<?php
/**
 * Script to load a calendar from an ICS file from the command line.
 */

if ( $argc < 5 ) {

  $basename = $argv[0];
  echo <<<USAGE
Usage:

	$basename davical.example.com replace /username/calendar/ calendarfile.ics

Where:
 'davical.example.com' is the hostname of your DAViCal server.
 'replace' is either 'replace' or 'append'
 '/username/calendar/' is the (sub-)path of the calendar to be updated.
 'calendarfile.ics' is the iCalendar file to be loaded.

This script can be used to load events or whole calendars from an external
iCalendar file.

If the mode is 'replace' and the target calendar does not exist then it will
be created.  For appending the calendar must always exist.

USAGE;
  exit(1);
}

$_SERVER['SERVER_NAME'] = $argv[1];
$mode = $argv[2];
$target = $argv[3];
$source = $argv[4];

if ( ! is_readable($source) ) {
  printf( "The iCalendar source file '%s' was not found.\n", $source );
  exit(1);
}
$ics = trim(file_get_contents($source));
if ( strlen($ics) < 29 ) {
  printf( "The iCalendar source file '%s' was missing or invalid.\n", $source );
  exit(1);
}


$script_file = __FILE__;
chdir(preg_replace('{/scripts/[^/]+.php$}','/htdocs',$script_file));

require_once("./always.php");
require_once('caldav-PUT-functions.php');
require_once('check_UTF8.php');
$c->readonly_webdav_collections = false; // Override any active default.

dbg_error_log('load-collection',':Write: Loaded %d bytes from %s', strlen($ics), $source );
if ( !check_string($ics) ) {
  $ics = force_utf8($ics);
  if ( !check_string($ics) ) {
    printf( "The source file '%s' contains some non-UTF-8 characters.\n", $source );
    exit(1);
  }
}

class FakeSession {

  var $user_no;
  var $principal_id;
  var $username;
  var $email;
  var $dav_name;
  var $principal;
  var $logged_in;

  function __construct($user_no = null) {
    if ( empty($user_no) ) {
      $this->user_no = -1;
      $this->principal_id = -1;
      $this->logged_in = false;
      return;
    }

    $this->user_no = $user_no;
    $principal = new Principal('user_no',$user_no);
    // Assign each field in the selected record to the object
    foreach( $principal AS $k => $v ) {
      $this->{$k} = $v;
    }
    $this->username = $principal->username();
    $this->principal_id = $principal->principal_id();
    $this->email = $principal->email();
    $this->dav_name = $principal->dav_name();
    $this->principal = $principal;
    
    $this->logged_in = true;

  }

  function AllowedTo($do_something) {
    return $this->logged_in;
  }
}
$session = new FakeSession();

$dest = new DAVResource($target);
$session = new FakeSession($dest->user_no());
if ( $mode == 'append' && ! $dest->Exists() ) {
  printf( "The target '%s' does not exist.\n", $target );
  exit(1);
}

if ( ! $dest->IsCollection() ) {
  printf( "The target '%s' is not a collection.\n", $target );
  exit(1);
}

$user_no = $dest->user_no();
$username = $session->username;
param_to_global('mode');
include_once('caldav-PUT-functions.php');
controlRequestContainer( $session->username, $dest->user_no(), $target, false, ($dest->IsPublic() ? true : false));
import_collection( $ics, $dest->user_no(), $target, $session->user_no, ($mode == 'append') );
printf(translate("Calendar '%s' was loaded from file.\n"), $target);

