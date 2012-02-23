#!/usr/bin/env php
<?php
/**
 * Script to export a calendar in iCalendar format from the command line.
 */

if ( $argc < 3 ) {

  $basename = $argv[0];
  echo <<<USAGE
Usage:

	$basename davical.example.com /username/calendar/ calendarfile.ics

Where:
 'davical.example.com' is the hostname of your DAViCal server.
 '/username/calendar/' is the (sub-)path of the calendar to be updated.

This script can be used to export whole calendars in iCalendar format to
stdout.

USAGE;
  exit(1);
}

$_SERVER['SERVER_NAME'] = $argv[1];
$source = $argv[2];

// Change into the web application root directory so includes work.
$script_file = __FILE__;
chdir(preg_replace('{/scripts/[^/]+.php$}','/htdocs',$script_file));
require_once("./always.php");

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

require("caldav-GET-functions.php");
$calendar = new DAVResource($source);

echo export_iCalendar($calendar); 

