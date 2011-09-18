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


$sql = 'SELECT our_tzno, tzid, active, olson_name, vtimezone, etag, ';
$sql .= 'to_char(last_modified AT TIME ZONE \'UTC\',\'Dy, DD Mon IYYY HH24:MI:SS "GMT"\') AS last_modified ';
$sql .= 'FROM timezones WHERE tzid=:tzid';
$params = array( ':tzid' => $tzid );
$qry = new AwlQuery($sql,$params);
if ( !$qry->Exec() ) exit(1);
if ( $qry->rows() < 1 ) {
  $sql = 'SELECT our_tzno, tzid, active, olson_name, vtimezone, etag, ';
  $sql .= 'to_char(last_modified AT TIME ZONE \'UTC\',\'Dy, DD Mon IYYY HH24:MI:SS "GMT"\') AS last_modified ';
  $sql .= 'FROM timezones JOIN tz_aliases USING(our_tzno) WHERE tzalias=:tzid';
  if ( !$qry->Exec() ) exit(1);
  if ( $qry->rows() < 1 ) $request->DoResponse(404);
}

$tz = $qry->Fetch();

$vtz = new vCalendar($tz->vtimezone);
$vtz->AddProperty('TZ-URL', ConstructURL($_SERVER['REQUEST_URI']));
$vtz->AddProperty('TZNAME', $tz->olson_name );
if ( $qry->QDo('SELECT * FROM tz_localnames WHERE our_tzno = :our_tzno', array(':our_tzno'=>$tz->our_tzno)) && $qry->rows() ) {
  while( $name = $qry->Fetch() ) {
    if ( strpos($_SERVER['QUERY_STRING'], 'lang='.$name->locale) !== false ) {
      $vtz->AddProperty('TZNAME',$name->localised_name, array('LANGUAGE',str_replace('_','-',$name->locale)));
    }
  }
}


header( 'Etag: "'.$tz->dav_etag.'"' );
header( 'Last-Modified', $tz->last_modified );

$request->DoResponse(200, $vtz->Render(), 'text/calendar');

exit(0);