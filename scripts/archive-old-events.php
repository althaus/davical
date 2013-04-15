#!/usr/bin/env php
<?php
/**
* Script to refresh the pending alarm times for the next alarm instance.
*
* @package   davical
* @subpackage   alarms
* @author    Andrew McMillan <andrew@morphoss.com>
* @copyright Morphoss Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/
$script_file = __FILE__;

chdir(str_replace('/scripts/archive-old-events.php','/htdocs',$script_file));
$_SERVER['SERVER_NAME'] = 'localhost';

/**
* Call with something like e.g.:
*
* scripts/archive-old-events.php -a archive -p karora -o P93D
*
*/

$args = (object) array();
$args->debug = false;
$args->principal = false;
$args->collection = false;

$args->older = 'P190D';
$args->delete = false;
$args->archive_suffix = 'archive';
$debugging = null;


function parse_arguments() {
  global $args;

  $opts = getopt( 'a:c:o:p:s:dh' );
  foreach( $opts AS $k => $v ) {
    switch( $k ) {
      case 'a':   $args->archive_suffix = $v;  break;
      case 'c':   $args->collection = $v;  break;
      case 'o':   $args->older = $v;  break;
      case 'p':   $args->principal = $v;  break;
      case 's':   $_SERVER['SERVER_NAME'] = $v; break;
      case 'd':   $args->debug = true;  $debugging = explode(',',$v); break;
      case 'h':   usage();  break;
      default:    $args->{$k} = $v;
    }
  }
  $bad = false;
  if ( $args->principal === false ) {
    echo "You must supply a principal.\n";
    $bad = true;
  }
  if ( $args->collection === false ) {
    echo "You must supply a collection.\n";
    $bad = true;
  }
  if ( $bad ) {
    usage();
  }
}

function usage() {

  echo <<<USAGE
Usage:
   archive-old-events.php [-s server.domain.tld] -p principal [other options]

  -a <archive_suffix> Appendeded (after a '-') to the name of the original calendar to give
                   the archive calendar name.  Default 'archive'.
  -o <duration>    Archive events completed this much prior to the current
                   date. Default 'P-190D'
  -p <principal>   The name of the principal to do the archiving for (required).
  -c <collection>  The name of the collection to do the archiving for (required).
  -s <server>      The servername to be used to identify the DAViCal configuration file.

  -d xxx           Enable debugging where 'xxx' is a comma-separated list of debug subsystems

USAGE;
  exit(0);
}

parse_arguments();

if ( $args->debug && is_array($debugging )) {
  foreach( $debugging AS $v ) {
    $c->dbg[$v] = 1;
  }
}

require_once("./always.php");
require_once('AwlQuery.php');
require_once('AwlCache.php');
require_once('RRule-v2.php');
require_once('vCalendar.php');


/**
* Essentially what we are doing is:
*
DELETE FROM caldav_data JOIN calendar_item USING(dav_id)
      WHERE dav_name LIKE '/someprincipal/%'
        AND (
          (rrule IS NULL AND dtend < archive_before_date)
           OR (last_instance_end < archive_before_date)
        )
         
*/
$recent = new RepeatRuleDateTime(gmdate('Ymd\THis\Z'));
$recent->modify('P-6D');
$archive_before_date = new RepeatRuleDateTime(gmdate('Ymd\THis\Z'));
$archive_before_date->modify( $args->older );
if ( $archive_before_date > $recent ) {
  echo "Cowardly refusing to archive events before "+$archive_before_date->format('Y-m-d H:i:s') + "\n";
}


if ( $args->debug ) printf( "Archiving event instances finished before '%s'\n", $archive_before_date->UTC() );

// SQL to create an archive collection but only if it doesn't exist already. 
$archive_collection_sql = <<<EOSQL
INSERT INTO collection (user_no, parent_container, dav_name, dav_etag, dav_displayname, is_calendar,
                        created, modified, public_events_only, publicly_readable, is_addressbook,
                        resourcetypes, schedule_transp, timezone, description)
     SELECT user_no, parent_container, :archive_dav_name, random(),
            'Archive of ' || dav_displayname, true, current_timestamp, current_timestamp, false, false, false,
            resourcetypes, schedule_transp, timezone, 
            'Archive of ' || CASE WHEN description IS NULL OR description = '' THEN dav_name ELSE description END
       FROM collection 
      WHERE dav_name = :collection_dav_name
        AND NOT EXISTS(SELECT 1 FROM collection c2 WHERE c2.dav_name = :archive_dav_name)
EOSQL;

$collection_dav_name = sprintf( '/%s/%s/', $args->principal, $args->collection );
$collection_archive = sprintf( '/%s/%s-%s/', $args->principal, $args->collection, $args->archive_suffix );

$sqlargs = array(
      ':collection_dav_name' => $collection_dav_name,
      ':archive_dav_name' =>  $collection_archive
    );
$qry = new AwlQuery($archive_collection_sql, $sqlargs);
if ( $qry->Exec(__CLASS__, __LINE__, __FILE__) ) {
  $qry->QDo('SELECT collection_id FROM collection WHERE dav_name = ?', $collection_dav_name );
  if ( $qry->rows() != 1 ) {
    printf( "Could not find source collection '%s'\n", $collection_dav_name);
    exit(1);
  }
  $row = $qry->Fetch();
  $source_collection_id = $row->collection_id;

  $qry->QDo('SELECT collection_id FROM collection WHERE dav_name = ?', $collection_archive );
  if ( $qry->rows() != 1 ) {
    printf( "Could not create archive collection '%s'!\n", $collection_archive);
    exit(2);
  }
  $row = $qry->Fetch();
  $archive_collection_id = $row->collection_id;
  
  $archive_sql = <<<EOSQL
UPDATE caldav_data SET dav_name = replace( caldav_data.dav_name, :collection_dav_name, :archive_dav_name)
       FROM calendar_item
        WHERE caldav_data.collection_id = :source_collection_id
          AND caldav_data.caldav_type = 'VEVENT'
          AND caldav_data.dav_id = calendar_item.dav_id
          AND (
                  (rrule IS NULL AND dtend < :archive_before_date)
              OR (last_instance_end is not null AND last_instance_end < :archive_before_date)
          ) 
EOSQL;

  if ( $args->debug ) printf( "%s\n", $archive_sql );

  $sqlargs[':archive_before_date'] = $archive_before_date->FloatOrUTC();
  $sqlargs[':source_collection_id'] = $source_collection_id;
  $sqlargs[':archive_collection_id'] = $archive_collection_id;
  $qry->QDo($archive_sql, $sqlargs);

  /**
   * At this point we've done all the work, we just have to inform the rest of the world that
   * everything has changed underneath it.
   */
  
  // Now ensure the collection tag changes...
  $sql = 'UPDATE collection SET dav_etag = random(), modified = current_timestamp WHERE collection_id IN (:source_id, :archive_id)';
  $sqlargs = array(
        ':source_id' => $source_collection_id, 
        ':archive_id' => $archive_collection_id
        );
  $qry->QDo($sql, $sqlargs);

  // Delete the sync tokens...
  $sql = 'DELETE FROM sync_token WHERE collection_id IN (:source_id, :archive_id)';
  $qry->QDo($sql, $sqlargs);

  // Delete the sync_changes...
  $sql = 'DELETE FROM sync_changes WHERE collection_id IN (:source_id, :archive_id)';
  $qry->QDo($sql, $sqlargs);
  
  // Uncache anything to do with the collection, or the archive
  $cache = getCacheInstance();
  $cache_ns = 'collection-'.preg_replace( '{/[^/]*$}', '/', $collection_dav_name);
  $cache->delete( $cache_ns, null );
  $cache_ns = 'collection-'.preg_replace( '{/[^/]*$}', '/', $collection_archive);
  $cache->delete( $cache_ns, null );
}