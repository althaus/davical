<?php

dbg_error_log("PROPFIND", "method handler");

$attributes = array();
$parser = xml_parser_create_ns('UTF-8');
xml_parser_set_option ( $parser, XML_OPTION_SKIP_WHITE, 1 );

function xml_start_callback( $parser, $el_name, $el_attrs ) {
  dbg_error_log( "PROPFIND", "Parsing $el_name" );
  dbg_log_array( "PROPFIND", "$el_name::attrs", $el_attrs, true );
  $attributes[$el_name] = $el_attrs;
}

function xml_end_callback( $parser, $el_name ) {
  dbg_error_log( "PROPFIND", "Finished Parsing $el_name" );
}

xml_set_element_handler ( $parser, 'xml_start_callback', 'xml_end_callback' );

$rpt_request = array();
xml_parse_into_struct( $parser, $raw_post, $rpt_request );
xml_parser_free($parser);

$find_path = $_SERVER['PATH_INFO'];
list( $blank, $username, $calpath ) = split( '/', $find_path, 3);
$calpath = "/".$calpath;
$href_list = array();
$attribute_list = array();

if ( isset($debugging) ) {
  $attribute_list = array( 'GETETAG' => 1, 'GETCONTENTLENGTH' => 1, 'GETCONTENTTYPE' => 1, 'RESOURCETYPE' => 1 );
}

foreach( $rpt_request AS $k => $v ) {

  switch ( $v['tag'] ) {

    case 'DAV::PROPFIND':
      dbg_log_array( "PROPFIND", "DAV-PROPFIND", $v, true );
      break;

    case 'DAV::PROP':
      dbg_log_array( "PROPFIND", "DAV::PROP", $v, true );
      break;

    case 'DAV::GETETAG':
    case 'DAV::GETCONTENTLENGTH':
    case 'DAV::GETCONTENTTYPE':
    case 'DAV::RESOURCETYPE':
      $attribute = substr($v['tag'],5);
      $attribute_list[$attribute] = 1;
      break;

    case 'DAV::HREF':
      dbg_log_array( "PROPFIND", "DAV::HREF", $v, true );
      $href_list[] = $v['value'];
      break;

    default:
      dbg_error_log( "PROPFIND", "Unhandled tag >>".$v['tag']."<<");
  }
}


/**
* Here is the kind of thing we are going to do, returning a top-level collection
* response, followed by a response for each calendar (or other resource) within it.
* <?xml version='1.0' encoding='UTF-8'?>
* <multistatus xmlns='DAV:'>
*  <response>
*   <href>/caldav.php/path/they/sent/</href>
*   <propstat>
*    <prop>
*      <getcontentlength/>
*      <getcontenttype>httpd/unix-directory</getcontenttype>
*      <resourcetype>
*       <collection/>
*      </resourcetype>
*    </prop>
*    <status>HTTP/1.1 200 OK</status>
*   </propstat>
*  </response>
*  <response>
*   <href>/caldav.php/path/they/sent/calendar</href>
*   <propstat>
*    <prop>
*      <getcontentlength/>
*      <getcontenttype>httpd/unix-directory</getcontenttype>
*      <resourcetype>
*       <collection/>
*       <calendar xmlns='urn:ietf:params:xml:ns:caldav'/>
*      </resourcetype>
*    </prop>
*    <status>HTTP/1.1 200 OK</status>
*   </propstat>
*  </response>
* </multistatus>
*/

require_once("XMLElement.php");

if ( count($href_list) > 0 ) {
  // Not supported at this point...
  dbg_error_log("ERROR", " PROPFIND: Support for PROPFIND on specific URLs is not implemented");
}
else {
  $responses = array();
  $url = sprintf("http://%s:%d%s%s", $_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT'], $_SERVER['SCRIPT_NAME'], $find_path );
  $url = $_SERVER['SCRIPT_NAME'] . $find_path ;
  $url = preg_replace( '#/$#', '', $url);

  $sql = "SELECT * FROM calendar WHERE user_no = ? AND dav_name ~ ?;";
  if ( $calpath == '' ) {
    $sql = "SELECT user_no, '/' || username || '/' AS dav_name, md5( '/' || username || '/') AS dav_etag, updated AS created FROM usr WHERE user_no = $session->user_no UNION ".$sql;
  }
  $qry = new PgQuery($sql, $session->user_no, '^/'.$username.$calpath );
  $qry->Exec("PROPFIND",__LINE,__FILE__);

  while( $calendar = $qry->Fetch() ) {
    $url = $_SERVER['SCRIPT_NAME'] . $calendar->dav_name;
    $resourcetypes = array( new XMLElement("collection") );
    $contentlength = false;
    if ( $calendar->dav_name != "/$username/" ) {
      $resourcetypes[] = new XMLElement("calendar", false, array("xmlns" => "urn:ietf:params:xml:ns:caldav"));
      $lqry = new PgQuery("SELECT sum(length(caldav_data)) FROM caldav_data WHERE user_no = ? AND dav_name ~ ?;", $session->user_no, '^/'.$username.$calpath.'[^/]+$' );
      if ( $lqry->Exec("PROPFIND",__LINE,__FILE__) && $row = $lqry->Fetch() ) {
        $contentlength = $row->sum;
      }
    }
    $prop = new XMLElement("prop");
    if ( isset($attribute_list['GETCONTENTLENGTH']) ) {
      $prop->NewElement("getcontentlength", $contentlength );
    }
    if ( isset($attribute_list['GETCONTENTTYPE']) ) {
//      $prop->NewElement("getcontenttype", "text/calendar" );
      $prop->NewElement("getcontenttype", "httpd/unix-directory" );
    }
    if ( isset($attribute_list['RESOURCETYPE']) ) {
      $prop->NewElement("resourcetype", $resourcetypes );
    }
    if ( isset($attribute_list['GETETAG']) ) {
      $prop->NewElement("getetag", '"'.$calendar->dav_etag.'"' );
    }
    $status = new XMLElement("status", "HTTP/1.1 200 OK" );

    $propstat = new XMLElement( "propstat", array( $prop, $status) );
    $href = new XMLElement("href", $url );

    $responses[] = new XMLElement( "response", array($href,$propstat));
  }

  $multistatus = new XMLElement( "multistatus", $responses, array('xmlns'=>'DAV:') );
}

dbg_log_array( "PROPFIND", "XML", $multistatus, true );
$xmldoc = $multistatus->Render();
$etag = md5($xmldoc);

header("HTTP/1.1 207 Multi-Status");
header("Content-type: text/xml;charset=UTF-8");
header("DAV: 1, 2, calendar-access, calendar-schedule");
header("ETag: \"$etag\"");

echo'<?xml version="1.0" encoding="UTF-8" ?>'."\n";
echo $xmldoc;

?>