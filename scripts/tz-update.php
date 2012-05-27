#!/usr/bin/env php
<?php
/**
* DAViCal Timezone Service handler - update timezones
*
* @package   davical
* @subpackage   tzservice
* @author    Andrew McMillan <andrew@morphoss.com>
* @copyright Morphoss Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/
$script_file = __FILE__;
if ( $argc < 2 ) {

  echo <<<USAGE
Usage:

	$script_file davical.example.com [timezone_source]

Where 'davical.example.com' is the hostname of your DAViCal server and the
optional 'timezone_source' is the source of the timezone data.  If not specified
this will default to the value of the $$c->tzsource configuration value, with
a further default to the zonedb/vtimezone directory relative to the root of the
DAViCal installation.

This script can be used to initialise or update the timezone information in
DAViCal used for the in-built timezone service.

USAGE;
  exit(1);
}

$_SERVER['SERVER_NAME'] = $argv[1];
$original_dir = getcwd();
chdir(str_replace('/scripts/tz-update.php','/htdocs',$script_file));

require_once("./always.php");

if ( isset($argv[2]) ) {
  $c->tzsource = $argv[2];
}

require_once('vCalendar.php');
require_once('XMLDocument.php');
require_once('RRule-v2.php');

chdir($original_dir);

$new_zones = 0;
$modified_zones = 0;
$added_aliases = 0;


function fetch_remote_list($base_url ) {
  global $request;
  $result = array();
  $list_url = $base_url . '?action=list';
  printf( "Fetching timezone list\n", $list_url );  
  $raw_xml = file_get_contents($list_url);
  $xml_parser = xml_parser_create_ns('UTF-8');
  $xml_tags = array();
  xml_parser_set_option ( $xml_parser, XML_OPTION_SKIP_WHITE, 1 );
  xml_parser_set_option ( $xml_parser, XML_OPTION_CASE_FOLDING, 0 );
  $rc = xml_parse_into_struct( $xml_parser, $raw_xml, $xml_tags );
  if ( $rc == false ) {
    dbg_error_log( 'ERROR', 'XML parsing error: %s at line %d, column %d', xml_error_string(xml_get_error_code($xml_parser)),
                xml_get_current_line_number($xml_parser), xml_get_current_column_number($xml_parser) );
    $request->PreconditionFailed(400, 'invalid-xml',
                sprintf('XML parsing error: %s at line %d, column %d', xml_error_string(xml_get_error_code($xml_parser)),
                xml_get_current_line_number($xml_parser), xml_get_current_column_number($xml_parser) ));
  }
  xml_parser_free($xml_parser);
  $position = 0;
  return BuildXMLTree( $xml_tags, $position);
}
  
function fetch_remote_zone( $base_url, $tzid ) {
  $tz_url = $base_url . '?action=get&tzid=' .$tzid;
  printf( "Fetching zone for %s from %s\n", $tzid, $tz_url );
  dbg_error_log( 'tz/updatecheck', "Fetching zone for %s from %s\n", $tzid, $tz_url );
  $vtimezone = file_get_contents( $tz_url );
  return $vtimezone;
}

function fetch_db_zone( $tzid ) {
  $tzrow = null;
  $qry = new AwlQuery('SELECT * FROM timezones WHERE tzid=:tzid', array(':tzid' => $tzid));
  if ( $qry->Exec('tz/update',__LINE__,__FILE__) && $qry->rows() > 0 ) {
    $tzrow = $qry->Fetch();
  }
  return $tzrow;
}

function write_updated_zone( $vtimezone, $tzid ) {
  global $new_zones, $modified_zones;
  if ( empty($vtimezone) ) {
    dbg_error_log('tz/updatecheck', 'Skipping zone "%s" - no data from server', $tzid );
    return;
  }
  $tzrow = fetch_db_zone($tzid);
  if ( isset($tzrow) && $vtimezone == $tzrow->vtimezone ) {
    dbg_error_log('tz/updatecheck', 'Skipping zone "%s" - no change', $tzid );
    return;
  }
  $vtz = new vCalendar($vtimezone);
  $last_modified = $vtz->GetPValue('LAST-MODIFIED');
  if ( empty($last_modified) ) {
    $last_modified = gmdate('Ymd\THis\Z');
    // Then it was probably that way when we last updated the data, too :-(
    if ( !empty($tzrow) ) {
      $old_vtz = new vCalendar($tzrow->vtimezone);
      $old_vtz->ClearProperties('LAST-MODIFIED');
      // We need to add & remove this property so the Render is equivalent.
      $vtz->AddProperty('LAST-MODIFIED',$last_modified);
      $vtz->ClearProperties('LAST-MODIFIED');
      if ( $vtz->Render() == $old_vtz->Render() ) {
        dbg_error_log('tz/updatecheck', 'Skipping zone "%s" - no change', $tzid );
        return;
      }
    }
    $vtz->AddProperty('LAST-MODIFIED',$last_modified);
  }
  dbg_error_log('tz/updatecheck', 'Writing %s zone for "%s"', (empty($tzrow)?"new":"updated"), $tzid );
  printf("Writing %s zone for '%s'\n", (empty($tzrow)?"new":"updated"), $tzid );
  $params = array(
            ':tzid'           => $tzid,
            ':olson_name'     => $tzid,
            ':vtimezone'      => $vtz->Render(),
            ':last_modified'  => $last_modified,
            ':etag'			=> md5($vtz->Render())
  );
  if ( empty($tzrow) ) {
    $new_zones++;
    $sql = 'INSERT INTO timezones(tzid,active,olson_name,last_modified,etag,vtimezone) ';
    $sql .= 'VALUES(:tzid,TRUE,:olson_name,:last_modified,:etag,:vtimezone)';
  }
  else {
    $modified_zones++;
    $sql = 'UPDATE timezones SET active=TRUE, olson_name=:olson_name, last_modified=:last_modified, ';
    $sql .= 'etag=:etag, vtimezone=:vtimezone WHERE tzid=:tzid';
  }
  $qry = new AwlQuery($sql,$params);
  $qry->Exec('tz/update',__LINE__,__FILE__);
}

function write_zone_aliases( $tzid, $aliases ) {
  global $added_aliases;
  foreach( $aliases AS $alias_node ) {
    $alias = $alias_node->GetContent();
    $params = array(':tzid' => $tzid, ':alias' => $alias );
    $qry = new AwlQuery('SELECT * FROM tz_aliases JOIN timezones USING(our_tzno) WHERE tzid=:tzid AND tzalias=:alias', $params);
    if ( $qry->Exec('tz/update', __LINE__, __FILE__) && $qry->rows() < 1 ) {
      $qry->QDo('INSERT INTO tz_aliases(our_tzno,tzalias) SELECT our_tzno, :alias FROM timezones WHERE tzid = :tzid', $params);
      $added_aliases++;
    }
  }
}


if ( empty($c->tzsource) ) $c->tzsource = '../zonedb/vtimezones';
if ( preg_match('{^http}', $c->tzsource ) ) {

  $changesince = null;
  $qry = new AwlQuery("SELECT tzid, to_char(last_modified,'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"') AS last_modified FROM timezones");
  $current_zones = array();
  if ( $qry->Exec('tz/updatecheck',__LINE__,__FILE__) && $qry->rows() > 0 ) {
    while( $row = $qry->Fetch() )
      $current_zones[$row->tzid] = new RepeatRuleDateTime($row->last_modified);
  }

  $xmltree = fetch_remote_list($c->tzsource);
  $zones = $xmltree->GetElements('urn:ietf:params:xml:ns:timezone-service:summary');
  foreach( $zones AS $zone ) {
    $elements = $zone->GetElements('urn:ietf:params:xml:ns:timezone-service:tzid');
    $tzid = $elements[0]->GetContent();
    $elements = $zone->GetElements('urn:ietf:params:xml:ns:timezone-service:last-modified');
    $last_modified = new RepeatRuleDateTime($elements[0]->GetContent());
    if ( !isset($current_zones[$tzid]) || $last_modified > $current_zones[$tzid] ) {
      printf("Found timezone %s needs updating\n", $tzid );
      $vtimezone = fetch_remote_zone($c->tzsource,$tzid);
      write_updated_zone($vtimezone, $tzid);
    }
    $elements = $zone->GetElements('urn:ietf:params:xml:ns:timezone-service:alias');
    write_zone_aliases($tzid, $elements);
  }
  
}
else if ( file_exists($c->tzsource) ) {
  /**
   * Find all files recursively within the diectory given.
   * @param string $dirname The directory to find files in
   * @return array of filenames with full path
   */
  function recursive_files( $dirname ) {
    $d = opendir($dirname);
    $result = array();
    while( $fn = readdir($d) ) {
      if ( substr($fn,0,1) == '.' ) continue;
      if ( substr($fn,0,14) == 'primary-source' ) continue;
      $fn = $dirname.'/'.$fn;
      if ( is_dir($fn) ) {
        $result = array_merge($result,recursive_files($fn));
      }
      else {
        $result[] = $fn;
      }
    }
    return $result;
  }

  $qry = new AwlQuery();
  foreach( recursive_files($c->tzsource) AS $filename ) {
    $tzid = str_replace('.ics', '', str_replace($c->tzsource.'/', '', $filename));
    $vtimezone = file_get_contents( $filename, false );
    write_updated_zone($vtimezone, $tzid);
  }
}
else {
  dbg_error_log('ERROR', '$c->tzsource is not configured to a good source of timezone data');
}

printf("Added %d new zones, updated data for %d zones and added %d new aliases\n",
           $new_zones, $modified_zones, $added_aliases);

exit(0);