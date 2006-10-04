<?php

dbg_error_log("PROPFIND", "method handler");

$attributes = array();
$parser = xml_parser_create_ns('UTF-8');
xml_parser_set_option ( $parser, XML_OPTION_SKIP_WHITE, 1 );

function xml_start_callback( $parser, $el_name, $el_attrs ) {
//  dbg_error_log( "PROPFIND", "Parsing $el_name" );
//  dbg_log_array( "PROPFIND", "$el_name::attrs", $el_attrs, true );
  $attributes[$el_name] = $el_attrs;
}

function xml_end_callback( $parser, $el_name ) {
//  dbg_error_log( "PROPFIND", "Finished Parsing $el_name" );
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
$depth = $_SERVER['HTTP_DEPTH'];
if ( $depth == 'infinite' ) $depth = 99;
else $depth = intval($depth);

// dbg_log_array("PROPFIND","_SERVER", $_SERVER, true );

if ( isset($debugging) ) {
  $attribute_list = array( 'GETETAG' => 1, 'GETCONTENTLENGTH' => 1, 'GETCONTENTTYPE' => 1, 'RESOURCETYPE' => 1 );
  $depth = 1;
}

$unsupported = array();
foreach( $rpt_request AS $k => $v ) {

  $tag = $v['tag'];
  switch ( $tag ) {
    case 'DAV::PROPFIND':
      dbg_error_log( "PROPFIND", ":Request: %s -> %s", $v['type'], $tag );
//      dbg_log_array( "PROPFIND", "DAV-PROPFIND", $v, true );
      break;

    case 'DAV::PROP':
      dbg_error_log( "PROPFIND", ":Request: %s -> %s", $v['type'], $tag );
//      dbg_log_array( "PROPFIND", "DAV::PROP", $v, true );
      break;

    case 'DAV::GETETAG':
    case 'DAV::DISPLAYNAME':
    case 'DAV::GETCONTENTLENGTH':
    case 'DAV::GETCONTENTTYPE':
    case 'DAV::RESOURCETYPE':
    case 'DAV::CURRENT-USER-PRIVILEGE-SET':
      $attribute = substr($v['tag'],5);
      $attribute_list[$attribute] = 1;
      dbg_error_log( "PROPFIND", "Adding attribute '%s'", $attribute );
      break;

    case 'DAV::HREF':
      // dbg_log_array( "PROPFIND", "DAV::HREF", $v, true );
      $href_list[] = $v['value'];

    default:
      if ( preg_match('/^(.*):([^:]+)$/', $tag, $matches) ) {
        $unsupported[$matches[2]] = $matches[1];
      }
      else {
        $unsupported[$tag] = "";
      }
      dbg_error_log( "PROPFIND", "Unhandled tag >>%s<<", $tag);
  }
}


require_once("XMLElement.php");

/**
* Returns the array of privilege names converted into XMLElements
*/
function privileges($privilege_names) {
  $privileges = array();
  foreach( $privilege_names AS $k => $v ) {
    $privileges[] = new XMLElement("privilege", new XMLElement($v));
  }
  return $privileges;
}

/**
* Returns an XML sub-tree for a single collection record from the DB
*/
function collection_to_xml( $collection ) {
  global $attribute_list, $session, $c;

  dbg_error_log("PROPFIND","Building XML Response for collection '%s'", $collection->dav_name );

  $url = $_SERVER['SCRIPT_NAME'] . $collection->dav_name;
  $resourcetypes = array( new XMLElement("collection") );
  $contentlength = false;
  if ( $collection->is_calendar == 't' ) {
    $resourcetypes[] = new XMLElement("calendar", false, array("xmlns" => "urn:ietf:params:xml:ns:caldav"));
    $lqry = new PgQuery("SELECT sum(length(caldav_data)) FROM caldav_data WHERE user_no = ? AND dav_name ~ ?;", $user_no, $collection_path.'[^/]+$' );
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
  if ( isset($attribute_list['DISPLAYNAME']) ) {
    $displayname = ( $collection->caldav_displayname == "" ? ucfirst(trim(str_replace("/"," ", $collection->dav_name))) : $collection->caldav_displayname );
    $prop->NewElement("displayname", $displayname );
  }
  if ( isset($attribute_list['GETETAG']) ) {
    $prop->NewElement("getetag", '"'.$collection->dav_etag.'"' );
  }
  if ( isset($attribute_list['CURRENT-USER-PRIVILEGE-SET']) ) {
    /**
    * FIXME: Fairly basic set of privileges at present.
    */
    if ( $session->AllowedTo("Admin") && preg_match("#/.+/#", $collection->dav_name) ) {
      $privs = array("all");
    }
    else {
      $privs = array("read");
      if ( $session->user_no == $collection->user_no || $session->AllowedTo("Admin") ) {
        $privs[] = "write";
      }
    }
    $prop->NewElement("current-user-privilege-set", privileges($privs) );
  }
  $status = new XMLElement("status", "HTTP/1.1 200 OK" );

  $propstat = new XMLElement( "propstat", array( $prop, $status) );
  $href = new XMLElement("href", $url );

  $response = new XMLElement( "response", array($href,$propstat));

  return $response;
}


/**
* Return XML for a single data item from the DB
*/
function item_to_xml( $item ) {
  global $attribute_list, $session, $c;

  dbg_error_log("PROPFIND","Building XML Response for item '%s'", $item->dav_name );

  $url = $_SERVER['SCRIPT_NAME'] . $item->dav_name;
  $prop = new XMLElement("prop");
  if ( isset($attribute_list['GETCONTENTLENGTH']) ) {
    $contentlength = strlen($item->caldav_data);
    $prop->NewElement("getcontentlength", $contentlength );
  }
  if ( isset($attribute_list['GETCONTENTTYPE']) ) {
    $prop->NewElement("getcontenttype", "text/calendar" );
  }
  if ( isset($attribute_list['RESOURCETYPE']) ) {
    $prop->NewElement("resourcetype", new XMLElement("calendar", false, array("xmlns" => "urn:ietf:params:xml:ns:caldav")) );
  }
  if ( isset($attribute_list['DISPLAYNAME']) ) {
    $prop->NewElement("displayname");
  }
  if ( isset($attribute_list['GETETAG']) ) {
    $prop->NewElement("getetag", '"'.$item->dav_etag.'"' );
  }
  if ( isset($attribute_list['CURRENT-USER-PRIVILEGE-SET']) ) {
    /**
    * FIXME: Fairly basic set of privileges at present.
    */
    if ( $session->AllowedTo("Admin") && preg_match("#/.+/.#", $item->dav_name) ) {
      $privs = array("all");
    }
    else {
      $privs = array("read");
      if ( $session->user_no == $item->user_no || $session->AllowedTo("Admin") ) {
        $privs[] = "write";
      }
    }
    $prop->NewElement("current-user-privilege-set", privileges($privs) );
  }
  $status = new XMLElement("status", "HTTP/1.1 200 OK" );

  $propstat = new XMLElement( "propstat", array( $prop, $status) );
  $href = new XMLElement("href", $url );

  $response = new XMLElement( "response", array($href,$propstat));

  return $response;
}

/**
* Get XML response for items in the collection
* If '/' is requested, a list of (FIXME: visible) users is given, otherwise
* a list of calendars for the user which are parented by this path.
*
* Permissions here might well be handled through an SQL function.
*/
function get_collection_contents( $depth, $user_no, $collection_path ) {
  global $session;

  dbg_error_log("PROPFIND","Getting collection contents: Depth %d, User: %d, Path: %s, IsCalendar: %s", $depth, $user_no, $collection_path, $collection->is_calendar );

  $responses = array();
  if ( $collection->is_calendar != 't' ) {
    /**
    * Calendar collections may not contain calendar collections.
    */
    if ( $collection_path == '/' ) {
      $sql .= "SELECT user_no, '/' || username || '/' AS dav_name, md5( '/' || username || '/') AS dav_etag, ";
      $sql .= "updated AS created, updated AS modified, fullname AS dav_displayname, FALSE AS is_calendar FROM usr";
    }
    else {
      $sql = "SELECT dav_name, dav_etag, created, modified, dav_displayname, is_calendar FROM collection WHERE parent_container=".qpg($collection_path);
    }
    $qry = new PgQuery($sql);

    if( $qry->Exec("PROPFIND",__LINE,__FILE__) && $qry->rows > 0 ) {
      while( $collection = $qry->Fetch() ) {
        $responses[] = collection_to_xml( $collection );
        if ( $depth > 0 ) {
          $responses = array_merge( $responses, get_collection( $depth - 1,  $user_no, $collection->dav_name ) );
        }
      }
    }
  }

  dbg_error_log("PROPFIND","Getting collection items: Depth %d, User: %d, Path: %s", $depth, $user_no, $collection_path );

  $sql = "SELECT dav_name, caldav_data, dav_etag, created, modified FROM caldav_data WHERE dav_name ~ ".qpg('^'.$collection_path.'[^/]+$');
  $qry = new PgQuery($sql);
  if( $qry->Exec("PROPFIND",__LINE,__FILE__) && $qry->rows > 0 ) {
    while( $item = $qry->Fetch() ) {
      $responses[] = item_to_xml( $item );
    }
  }

  return $responses;
}

/**
* Get XML response for a single collection.  If Depth is >0 then
* subsidiary collections will also be got up to $depth
*/
function get_collection( $depth, $user_no, $collection_path ) {
  global $c;
  $responses = array();

  dbg_error_log("PROPFIND","Getting collection: Depth %d, User: %d, Path: %s", $depth, $user_no, $collection_path );

  if ( $collection_path == '/' ) {
    $collection->dav_name = $collection_path;
    $collection->dav_etag = md5($c->system_name . $collection_path);
    $collection->is_calendar = 'f';
    $collection->dav_displayname = $c->system_name;
    $collection->created = date('Ymd"T"His');
    $responses[] = collection_to_xml( $collection );
  }
  else {
    $user_no = intval($user_no);
    if ( preg_match( '#^/[^/]+/$#', $collection_path) ) {
      $sql .= "SELECT user_no, '/' || username || '/' AS dav_name, md5( '/' || username || '/') AS dav_etag, ";
      $sql .= "updated AS created, fullname AS dav_displayname, FALSE AS is_calendar FROM usr WHERE user_no = $user_no ; ";
    }
    else {
      $sql = "SELECT dav_name, dav_etag, created, dav_displayname, is_calendar FROM collection WHERE user_no = $user_no AND dav_name = ".qpg($collection_path);
    }
    $qry = new PgQuery($sql );
    if( $qry->Exec("PROPFIND",__LINE,__FILE__) && $qry->rows > 0 && $collection = $qry->Fetch() ) {
      $responses[] = collection_to_xml( $collection );
    }
    elseif ( $c->collections_always_exist ) {
      $collection->dav_name = $collection_path;
      $collection->dav_etag = md5($collection_path);
      $collection->is_calendar = 't';  // Everything is a calendar, if it always exists!
      $collection->dav_displayname = $collection_path;
      $collection->created = date('Ymd"T"His');
      $responses[] = collection_to_xml( $collection );
    }
  }
  if ( $depth > 0 ) {
    $responses = array_merge($responses, get_collection_contents( $depth-1,  $user_no, $collection_path ) );
  }
  return $responses;
}


if ( count($unsupported) > 0 ) {

  /**
  * That's a *BAD* request!
  */

  header('HTTP/1.1 403 Forbidden');
  header('Content-Type: application/xml; charset="utf-8"');

  $badprops = new XMLElement( "prop" );
  foreach( $unsupported AS $k => $v ) {
    // Not supported at this point...
    dbg_error_log("ERROR", " PROPFIND: Support for $v::$k properties is not implemented yet");
    $badprops->NewElement(strtolower($k),false,array("xmlns" => strtolower($v)));
  }
  $error = new XMLElement("error", new XMLElement( "propfind",$badprops), array("xmlns" => "DAV:") );
//   dbg_log_array( "PROPFIND", "ERRORXML", $error, true );

  echo $error->Render(0,'<?xml version="1.0" ?>');
  exit(0);
}
else {

  /**
  * Something that we can handle, at least roughly correctly.
  */
  $url = sprintf("http://%s:%d%s%s", $_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT'], $_SERVER['SCRIPT_NAME'], $find_path );
  $url = $_SERVER['SCRIPT_NAME'] . $find_path ;
  $url = preg_replace( '#/$#', '', $url);

  $responses = get_collection( $depth, $session->user_no, $find_path );

  $multistatus = new XMLElement( "multistatus", $responses, array('xmlns'=>'DAV:') );
}

// dbg_log_array( "PROPFIND", "XML", $multistatus, true );
$xmldoc = $multistatus->Render();
$etag = md5($xmldoc);

header("HTTP/1.1 207 Multi-Status");
header("Content-type: text/xml;charset=UTF-8");
header("ETag: \"$etag\"");

echo'<?xml version="1.0" encoding="UTF-8" ?>'."\n";
echo $xmldoc;

?>