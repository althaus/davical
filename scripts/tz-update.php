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

chdir(str_replace('/scripts/tz-update.php','/htdocs',$script_file));

require_once("./always.php");

if ( isset($argv[2]) ) {
  $c->tzsource = $argv[2];
}

require_once('vCalendar.php');
require_once('XMLDocument.php');
require_once('RRule-v2.php');

$new_zones = 0;
$modified_zones = 0;

if ( empty($c->tzsource) ) $c->tzsource = '../zonedb/vtimezones';
if ( preg_match('{^http}', $c->tzsource ) ) {

  function fetch_tz_ids( $base_url, $current_zones ) {
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
    $xmltree = BuildXMLTree( $xml_tags, $position);
    $zones = $xmltree->GetElements('urn:ietf:params:xml:ns:timezone-service:summary');
    foreach( $zones AS $zone ) {
      $elements = $zone->GetElements('urn:ietf:params:xml:ns:timezone-service:tzid');
      $tzid = $elements[0]->GetContent();
      $elements = $zone->GetElements('urn:ietf:params:xml:ns:timezone-service:last-modified');
      $last_modified = new RepeatRuleDateTime($elements[0]->GetContent());
      if ( $last_modified > $current_zones[$tzid] ) {
        $result[] = $tzid;
        printf("Found timezone %s needs updating\n", $tzid);
      }
      $elements = $zone->GetElements('urn:ietf:params:xml:ns:timezone-service:alias');
      foreach( $elements AS $element ) {
        $alias = $element->GetContent();
      }
    }
    return $result;
  }

  $changesince = null;
  $qry = new AwlQuery("SELECT tzid, to_char(last_modified,'YYYY-MM-DD\"T\"HH24:MI:SS') AS last_modified FROM timezones");
  $current_zones = array();
  if ( $qry->Exec('tz/updatecheck',__LINE__,__FILE__) && $qry->rows() > 0 ) {
    $row = $qry->Fetch();
    $current_zones[$row->tzid] = new RepeatRuleDateTime($row->last_modified);
  }
  foreach( fetch_tz_ids($c->tzsource, $current_zones) AS $tzid ) {
    $tz_url = $c->tzsource . '?action=get&tzid=' .$tzid;
    $tzrow = null;
    if ( $qry->QDo('SELECT * FROM timezones WHERE tzid=:tzid', array(':tzid' => $tzid)) && $qry->rows() > 0 ) {
      $tzrow = $qry->Fetch();
    }
    printf( "Fetching zone for %s from %s\n", $tzid, $tz_url );  
    dbg_error_log( 'tz/updatecheck', "Fetching zone for %s from %s\n", $tzid, $tz_url );  
    $vtimezone = file_get_contents( $tz_url );
    if ( empty($vtimezone) ) {
      dbg_error_log('tz/updatecheck', 'Skipping zone "%s" - no data from server', $tzid );
      continue;
    }
    if ( $vtimezone == $tzrow->vtimezone ) {
      dbg_error_log('tz/updatecheck', 'Skipping zone "%s" - no change', $tzid );
      continue;
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
          continue;
        }
      }
      $vtz->AddProperty('LAST-MODIFIED',$last_modified);
    }
    dbg_error_log('tz/updatecheck', 'Writing %s zone for "%s"', (empty($tzrow)?"new":"updated"), $tzid );
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
    $qry->QDo($sql,$params);
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
    $tzrow = null;
    if ( $qry->QDo('SELECT * FROM timezones WHERE tzid=:tzid', array(':tzid' => $tzid)) && $qry->rows() > 0 ) {
      $tzrow = $qry->Fetch();
    }
    $vtimezone = file_get_contents( $filename, false );
    if ( $vtimezone == $tzrow->vtimezone ) {
      dbg_error_log('tz/updatecheck', 'Skipping zone "%s" - no change', $tzid );
      continue;
    }
    $vtz = new vCalendar($vtimezone);
    $last_modified = $vtz->GetProperty('LAST-MODIFIED');
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
          continue;
        }
      }
      $vtz->AddProperty('LAST-MODIFIED',$last_modified);
    }
    dbg_error_log('tz/updatecheck', 'Writing %s zone for "%s"', (empty($tzrow)?"new":"updated"), $tzid );
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
    $qry->QDo($sql,$params);
  }
}
else {
  dbg_error_log('ERROR', '$c->tzsource is not configured to a good source of timezone data');
}

header('Content-type: text/plain');
printf("Added %d new zones and updated data for %d zones\n", $new_zones, $modified_zones);

exit(0);