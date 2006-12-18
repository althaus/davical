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

// if ( ! $request->AllowedTo('write-properties') ) {
//   $request->DoResponse( 403 );
// }

$tree - new XMLTree();

foreach( $request->xml_tags AS $k => $v ) {

  $fulltag = $v['tag'];
  dbg_error_log("PROPPATCH", "Handling %s", $fulltag);
  // dbg_log_array( "PROPPATCH", 'values', $v, true );
  if ( $v['type'] == "open" ) {
    echo "Entering $fulltag\n";
    if ( isset($current) ) {
      echo "Type of \$current is  ".gettype($current)."\n";
      $child = new XMLElement($fulltag);
      $child->SetParent($current);
      $current->AddSubTag($child);
      $current =& $child;
    }
    else {
      echo "Root of tree is $fulltag\n";
      $root = new XMLElement($fulltag);
      $current =& $root;
    }
  }
  else if ( $v['type'] == "close" ) {
    echo "Leaving $fulltag\n";
    $parent =& $current->GetParent();
    $current =& $parent;
  }
  else if ( $v['type'] == "complete" ) {
    $value = $v['value'];
    printf( "Adding '%s' with content '%s'\n", $fulltag, $value );
    $child = new XMLElement($fulltag, $value);
    $current->AddSubTag($child);
  }
  else {
    printf( "Unhandled type '%s' for tag '%s'\n", $v['type'], $v['tag'] );
  }


//  echo $root->Render(). "\n";
//  switch ( $fulltag ) {
}


echo $root->Render(). "\n";

exit(0);

?>