<?php
/**
* DAViCal Timezone Service handler - capabilitis
*
* @package   davical
* @subpackage   tzservice
* @author    Andrew McMillan <andrew@morphoss.com>
* @copyright Morphoss Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

require_once('vCalendar.php');

$new_zones = 0;
$modified_zones = 0;

if ( empty($c->tzsource) ) $c->tzsource = '../zonedb/vtimezones';
if ( preg_match('{^http}', $c->tzsource ) ) {

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
  header('Content-type: text/plain');
  printf('Added %d new zones and updated data for %d zones', $new_zones, $modified_zones);
}
else {
  dbg_error_log('ERROR', '$c->tzsource is not configured to a good source of timezone data');
}

exit(0);