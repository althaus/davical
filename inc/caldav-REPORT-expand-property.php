<?php

require_once('DAVResource.php');

/**
* Given a <response><href>...</href><propstat><prop><someprop/></prop><status>HTTP/1.1 200 OK</status></propstat>...</response>
* pull out the content of <someprop>content</someprop> and check to see if it has any href elements.  If it *does* then
* recurse into them, looking for the next deeper nesting of properties.
*/
function get_href_containers( &$multistatus_response ) {
  $propstat_set = $multistatus_response->GetPath('/DAV::response/DAV::propstat/');
  $propstat_200 = null;
  foreach( $propstat_set AS $k => $v ) {
    $status = $v->GetElements('status');
    if ( preg_match( '{^HTTP/\S+\s+200}', $status[0]->GetContent() ) ) {
      $propstat_200 = $v;
      break;
    }
  }
  if ( isset($propstat_200) ) {
    $properties = $propstat_200->GetPath('/DAV::propstat/DAV::prop/*');
    $href_containers = array();
    foreach( $properties AS $k => $property ) {
      $hrefs = $property->GetElements('href');
      if ( count($hrefs) > 0 ) {
        $href_containers[] = &$property;
      }
    }
    if ( count($href_containers) > 0 ) {
      return $href_containers;
    }
  }
  return null;
}


/**
* Expand the properties, recursing as needed
*/
function expand_properties( $urls, $ptree, &$reply ) {
  if ( !is_array($urls) )  $urls = array($urls);
  if ( !is_array($ptree) ) $ptree = array($ptree);

  $responses = array();
  foreach( $urls AS $m => $url ) {
    $resource = new DAVResource($url);
    $props = array();
    $subtrees = array();
    foreach( $ptree AS $n => $property ) {
      $pname = $property->GetAttribute('name');
      $pns = $property->GetAttribute('namespace');
      if ( isset($pns) ) $pname = $pns .':'. $pname;
      $props[] = $pname;
      $subtrees[$pname] = $property->GetContent();
    }
    $part_response = $resource->RenderAsXML( $props, $reply );
    if ( isset($part_response) ) {
      $href_containers = get_href_containers($part_response);
      if ( isset($href_containers) ) {
        foreach( $href_containers AS $h => $property ) {
          $hrefs = $property->GetContent();
          $pname = $property->GetNSTag();
          $property->SetContent( expand_properties($paths, $subtrees[$pname], $reply) );
        }
      }
      $responses[] = $part_response;
    }
  }

  return $responses;
}


/**
 * Build the array of properties to include in the report output
 */
$property_tree = $xmltree->GetPath('/DAV::expand-property/DAV::property');

$multistatus = new XMLElement( "multistatus", expand_properties( $request->path, $property_tree, $reply), $reply->GetXmlNsArray() );

$request->XMLResponse( 207, $multistatus );
