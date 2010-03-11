#!/usr/bin/php
<?php

if ( @file_exists('../../awl/inc/AWLUtilities.php') ) {
  set_include_path('../inc:../../awl/inc');
}
else if ( @file_exists('../awl/inc/AWLUtilities.php') ) {
  set_include_path('inc:../awl/inc:.');
}
else {
  set_include_path('../inc:/usr/share/awl/inc');
}
include('always.php');
require_once('AwlQuery.php');
require_once('caldav-PUT-functions.php');

include('caldav-client-v2.php');

$args = (object) null;
$args->sync_all = false;

function parse_arguments() {
  global $args;

  $opts = getopt( 'u:U:p:a' );
  foreach( $opts AS $k => $v ) {
    switch( $k ) {
      case 'u':   $args->url  = $v;  break;
      case 'U':   $args->user = $v;  break;
      case 'p':   $args->pass = $v;  break;
      case 'a':   $args->sync_all = 1;  break;
      case 'c':   $args->local_collection_path = $v;  break;
      default:    $args->{$k} = $v;
    }
  }
}

parse_arguments();

// E.g.
// sync-pull.php -U andrew@example.net -p 53cret -u https://www.google.com/calendar/dav/andrew@example.net/events -c /andrew/gsync/
//

if ( !preg_match( '{/$}', $args->url ) ) $args->url .= '/';

$caldav = new CalDAVClient( $args->url, $args->user, $args->pass );

// This will find the 'Principal URL' which we can query for user-related
// properties.
$principal_url = $caldav->FindPrincipal($args->url);

// This will find the 'Calendar Home URL' which will be the folder(s) which
// contain all of the user's calendars
$calendar_home_set = $caldav->FindCalendarHome();

$calendar = null;

// This will go through the calendar_home_set and find all of the users
// calendars on the remote server.
$calendars = $caldav->FindCalendars();
if ( count($calendars) < 1 ) {
  printf( "No calendars found based on '%s'\n", $args->url );
}

// Now we have all of the remote calendars, we will look for the URL that
// matches what we were originally supplied.  While this seems laborious
// because we already have it, it means we could provide a match in some
// other way (e.g. on displayname) and we could also present a list to
// the user which is built from following the above process.
foreach( $calendars AS $k => $a_calendar ) {
  if ( rawurldecode($a_calendar->url) == rawurldecode($args->url) ) $calendar = $a_calendar;
}
if ( !isset($calendar) ) $calendar = $calendars[0];

// In reality we could have omitted all of the above parts, If we really do
// know the correct URL at the start.
printf( "Calendar '%s' is at %s\n", $calendar->displayname, $calendar->url );

// Generate a consistent filename for our synchronisation cache
$sync_cache_filename = md5($args->url . $args->user . $calendar->url);

// Do we just need to sync everything across and overwrite all the local stuff?
$sync_all = ( !file_exists($sync_cache_filename) || $args->sync_all);

if ( ! $sync_all ) {
  /**
  * Read a structure out of the cache file containing:
  *   server_getctag - A collection tag (string)
  *   server_etags   - An array of event tags (strings) keyed on filename, from the server
  *   local_etags    - An array of event tags (strings) keyed on filename, from local DAViCal
  */
  $cache = unserialize( file_get_contents($sync_cache_filename) );

  // First compare the ctag for the calendar
  if ( isset($cache) && isset($cache->server_ctag) && isset($calendar->getctag) && $calendar->getctag == $cache->server_ctag ) {
    printf( 'No changes to calendar "%s"'."\n", $args->url );
    exit(0);
  }
}
if ( !isset($cache) || !isset($cache->server_ctag) ) $sync_all = true;

// Everything now will be at our calendar URL
$caldav->SetCalendar($calendar->url);

// So it seems we do need to sync.  We now need to check each individual event
// which might have changed, so we pull a list of event etags from the server.
$server_etags = $caldav->GetCollectionETags($calendar->url);

$newcache = (object) array( 'server_ctag' => $calendar->getctag, 'server_etags' => array(), 'local_etags' => array() );

if ( $sync_all ) {
  // The easy case.  Sync them all, delete nothing
  $insert_urls = array_flip($server_etags);
  $update_urls = array();
  $delete_urls = array();
  foreach( $server_etags AS $href => $etag ) {
    $fname = preg_replace('{^.*/}', '', $href);
    $newcache->server_etags[$fname] = $etag;
  }
}
else {
  // Only sync the ones where the etag has changed.  Delete any that are no
  // longer present at the remote end.
  $insert_urls = array();
  $update_urls = array();
  foreach( $server_etags AS $href => $etag ) {
    $fname = preg_replace('{^.*/}', '', $href);
    $newcache->server_etags[$fname] = $etag;
    if ( isset($cache->server_etags[$fname]) ) {
      $cache_etag = $cache->server_etags[$fname];
      unset($cache->server_etags[$fname]);
      if ( $cache_etag == $etag ) continue;
      $update_urls[] = $href;
    }
    else {
      $insert_urls[] = $href;
    }
  }
  $delete_urls = array_flip($cache->server_etags);
}


// Fetch the calendar data
$events = $caldav->CalendarMultiget( array_merge( $insert_urls, $update_urls) );

/**
* @TODO: We should really check for collisions locally in case the local ETag is
* also different to the one we saved earlier.
*/
// Update the local system with these events
foreach( $events AS $href => $event ) {
  // Do what we need to write $v into the local calendar we are syncing to
  // at the
  $fname = preg_replace('{^.*/}', '', $href);
  $local_fname = $args->local_collection_path . $fname;
  simple_write_resource( $local_fname, $event, (isset($insert_urls[$href]) ? 'INSERT' : 'UPDATE') );
}

/**
* @TODO: We should not delete locally in the case that the local ETag is different
* to the one we saved earlier.
*/
// Delete any events which were present in our cache, but are not on the master server
foreach( $delete_urls AS $k => $v ) {
  $fname = preg_replace('{^.*/}', '', $href);
  $local_fname = $args->local_collection_path . $fname;
  $qry = new AwlQuery('DELETE FROM caldav_data WHERE dav_name = :dav_name', array( ':dav_name' => $local_fname ) );
  $qry->Exec('sync_pull',__LINE__,__FILE__);
}

// Now (re)write the cache file reflecting the current state.
$cache_file = fopen($sync_cache_filename, 'w');
fwrite( $cache_file, serialize($newcache) );
fclose($cache_file);
