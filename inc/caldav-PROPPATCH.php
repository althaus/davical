<?php
/**
* CalDAV Server - handle PROPPATCH method
*
* @package   rscds
* @subpackage   caldav
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("PROPPATCH", "method handler");

if ( ! $request->AllowedTo('write-properties') ) {
  $request->DoResponse( 403 );
}

$position = 0;
$xmltree = BuildXMLTree( $request->xml_tags, $position);

echo $xmltree->Render();

if ( $xmltree->GetTag() != "DAV::PROPERTYUPDATE" ) {
  $request->DoResponse( 403 );
}

$tmp = $xmltree->GetPath("/DAV::PROPERTYUPDATE/DAV::SET/DAV::PROP");

$settings = array();
foreach( $tmp AS $k => $v ) {
  printf("Content of %s is type %s\n", $v->GetTag(), gettype($v->GetContent()) );
  foreach( $v->GetContent() AS $k1 => $setting ) {
    $settings[$setting->GetTag()] = $setting->GetContent();
  }
}

foreach( $settings AS $setting => $value ) {
  printf("Setting '%s' is set to '%s'\n", $setting, $value);
}

exit(0);

?>