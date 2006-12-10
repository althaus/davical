<?php
/**
* CalDAV Server - handle PROPFIND method
*
* @package   rscds
* @subpackage   caldav
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("PROPFIND", "method handler");

if ( ! $request->AllowedTo('read') ) {
  $request->DoResponse( 403, translate("You may not access that calendar") );
}

require_once("XMLElement.php");
require_once("iCalendar.php");

$href_list = array();
$attribute_list = array();
$unsupported = array();

foreach( $request->xml_tags AS $k => $v ) {

  $tag = $v['tag'];
  dbg_error_log( "PROPFIND", " Handling Tag '%s' => '%s' ", $k, $v );
  switch ( $tag ) {
    case 'DAV::PROPFIND':
    case 'DAV::PROP':
      dbg_error_log( "PROPFIND", ":Request: %s -> %s", $v['type'], $tag );
      break;

    case 'URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-DESCRIPTION':
    case 'URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-TIMEZONE':
    case 'URN:IETF:PARAMS:XML:NS:CALDAV:SUPPORTED-CALENDAR-COMPONENT-SET':
    case 'URN:IETF:PARAMS:XML:NS:CALDAV:SUPPORTED-CALENDAR-DATA':
    case 'URN:IETF:PARAMS:XML:NS:CALDAV:MAX-RESOURCE-SIZE':
    case 'URN:IETF:PARAMS:XML:NS:CALDAV:MIN-DATE-TIME':
    case 'URN:IETF:PARAMS:XML:NS:CALDAV:MAX-DATE-TIME':
    case 'URN:IETF:PARAMS:XML:NS:CALDAV:MAX-INSTANCES':
    case 'URN:IETF:PARAMS:XML:NS:CALDAV:MAX-ATTENDEES-PER-INSTANCE':
    case 'URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-HOME-SET':
    case 'URN:IETF:PARAMS:XML:NS:CALDAV:SUPPORTED-COLLATION-SET':
    case 'HTTP://APACHE.ORG/DAV/PROPS/:EXECUTABLE':
    case 'DAV::CHECKED-OUT':
    case 'DAV::CHECKED-IN':
    case 'DAV::SOURCE':
    case 'DAV::LOCKDISCOVERY':
      /** These are ignored */
      break;

    case 'DAV::ACL':                            /** acl             - only vaguely supported */
    case 'DAV::CREATIONDATE':                   /** creationdate    - should work fine */
    case 'DAV::GETLASTMODIFIED':                /** getlastmodified - should work fine */
    case 'DAV::DISPLAYNAME':                    /** displayname     - should work fine */
    case 'DAV::GETCONTENTLENGTH':               /** getcontentlength- should work fine */
    case 'DAV::GETCONTENTTYPE':                 /** getcontenttype  - should work fine */
    case 'DAV::GETETAG':                        /** getetag         - should work fine */
    case 'DAV::SUPPORTEDLOCK':                  /** supportedlock   - should work fine */
    case 'DAV::RESOURCETYPE':                   /** resourcetype    - should work fine */
    case 'DAV::GETCONTENTLANGUAGE':             /** resourcetype    - should return the user's chosen locale, or default locale */
    case 'DAV::SUPPORTED-PRIVILEGE-SET':        /** supported-privilege-set    - should work fine */
    case 'DAV::CURRENT-USER-PRIVILEGE-SET':     /** current-user-privilege-set - only vaguely supported */
    case 'DAV::ALLPROP':                        /** allprop - limited support */
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


/**
* Returns the array of privilege names converted into XMLElements
*/
function privileges($privilege_names, $container="privilege") {
  $privileges = array();
  foreach( $privilege_names AS $k => $v ) {
    $privileges[] = new XMLElement($container, new XMLElement($k));
  }
  return $privileges;
}

/**
* Returns an XML sub-tree for a single collection record from the DB
*/
function collection_to_xml( $collection ) {
  global $attribute_list, $session, $c, $request;

  dbg_error_log("PROPFIND","Building XML Response for collection '%s'", $collection->dav_name );

  $url = $_SERVER['SCRIPT_NAME'] . $collection->dav_name;
  $resourcetypes = array( new XMLElement("collection") );
  $contentlength = false;
  if ( $collection->is_calendar == 't' ) {
    $resourcetypes[] = new XMLElement("calendar", false, array("xmlns" => "urn:ietf:params:xml:ns:caldav"));
    $lqry = new PgQuery("SELECT sum(length(caldav_data)) FROM caldav_data WHERE user_no = ? AND dav_name ~ ?;", $collection->user_no, $collection->dav_name.'[^/]+$' );
    if ( $lqry->Exec("PROPFIND",__LINE__,__FILE__) && $row = $lqry->Fetch() ) {
      $contentlength = $row->sum;
    }
  }
  $prop = new XMLElement("prop");
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['GETLASTMODIFIED']) ) {
    $prop->NewElement("getlastmodified", ( isset($collection->modified)? $collection->modified : false ));
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['GETCONTENTLENGTH']) ) {
    $prop->NewElement("getcontentlength", $contentlength );
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['GETCONTENTTYPE']) ) {
    $prop->NewElement("getcontenttype", "httpd/unix-directory" );
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['CREATIONDATE']) ) {
    $prop->NewElement("creationdate", $collection->created );
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['RESOURCETYPE']) ) {
    $prop->NewElement("resourcetype", $resourcetypes );
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['DISPLAYNAME']) ) {
    $displayname = ( $collection->dav_displayname == "" ? ucfirst(trim(str_replace("/"," ", $collection->dav_name))) : $collection->dav_displayname );
    $prop->NewElement("displayname", $displayname );
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['GETETAG']) ) {
    $prop->NewElement("getetag", '"'.$collection->dav_etag.'"' );
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['CURRENT-USER-PRIVILEGE-SET']) ) {
    $prop->NewElement("current-user-privilege-set", privileges($request->permissions) );
  }
  if ( isset($attribute_list['ACL']) ) {
    /**
    * FIXME: This information is semantically valid but presents an incorrect picture.
    */
    $principal = new XMLElement("principal");
    $principal->NewElement("authenticated");
    $grant = new XMLElement( "grant", array(privileges($request->permissions)) );
    $prop->NewElement("acl", new XMLElement( "ace", array( $principal, $grant ) ) );
  }

  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['GETCONTENTLANGUAGE']) ) {
    $contentlength = strlen($item->caldav_data);
    $prop->NewElement("getcontentlanguage", $c->current_locale );
  }

  if ( isset($attribute_list['SUPPORTEDLOCK']) ) {
    $prop->NewElement("supportedlock",
       new XMLElement( "lockentry",
         array(
           new XMLElement("lockscope", new XMLElement("exclusive")),
           new XMLElement("locktype",  new XMLElement("write")),
         )
       )
     );
  }

  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['SUPPORTED-PRIVILEGE-SET']) ) {
    $prop->NewElement("supported-privilege-set", privileges( $request->SupportedPrivileges(), "supported-privilege") );
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
  global $attribute_list, $session, $c, $request;

  dbg_error_log("PROPFIND","Building XML Response for item '%s'", $item->dav_name );

  $url = $_SERVER['SCRIPT_NAME'] . $item->dav_name;
  $prop = new XMLElement("prop");
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['GETLASTMODIFIED']) ) {
    $prop->NewElement("getlastmodified", ( isset($item->modified)? $item->modified : false ));
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['GETCONTENTLENGTH']) ) {
    $contentlength = strlen($item->caldav_data);
    $prop->NewElement("getcontentlength", $contentlength );
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['GETCONTENTTYPE']) ) {
    $prop->NewElement("getcontenttype", "text/calendar" );
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['CREATIONDATE']) ) {
    $prop->NewElement("creationdate", $item->created );
  }
  /**
  * Non-collections should return an empty resource type, it appears from RFC2518 8.1.2
  */
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['RESOURCETYPE']) ) {
    $prop->NewElement("resourcetype");
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['DISPLAYNAME']) ) {
    $prop->NewElement("displayname", $item->dav_displayname );
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['GETETAG']) ) {
    $prop->NewElement("getetag", '"'.$item->dav_etag.'"' );
  }

  if ( isset($attribute_list['ACL']) ) {
    /**
    * FIXME: This information is semantically valid but presents an incorrect picture.
    */
    $principal = new XMLElement("principal");
    $principal->NewElement("authenticated");
    $grant = new XMLElement( "grant", array(privileges($request->permissions)) );
    $prop->NewElement("acl", new XMLElement( "ace", array( $principal, $grant ) ) );
  }

  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['GETCONTENTLANGUAGE']) ) {
    $contentlength = strlen($item->caldav_data);
    $prop->NewElement("getcontentlanguage", $c->current_locale );
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['CURRENT-USER-PRIVILEGE-SET']) ) {
    $prop->NewElement("current-user-privilege-set", privileges($request->permissions) );
  }

  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['SUPPORTEDLOCK']) ) {
    $prop->NewElement("supportedlock",
       new XMLElement( "lockentry",
         array(
           new XMLElement("lockscope", new XMLElement("exclusive")),
           new XMLElement("locktype",  new XMLElement("write")),
         )
       )
     );
  }
  $status = new XMLElement("status", "HTTP/1.1 200 OK" );

  $propstat = new XMLElement( "propstat", array( $prop, $status) );
  $href = new XMLElement("href", $url );

  $response = new XMLElement( "response", array($href,$propstat));

  return $response;
}

/**
* Get XML response for items in the collection
* If '/' is requested, a list of visible users is given, otherwise
* a list of calendars for the user which are parented by this path.
*/
function get_collection_contents( $depth, $user_no, $collection ) {
  global $session;

  dbg_error_log("PROPFIND","Getting collection contents: Depth %d, User: %d, Path: %s", $depth, $user_no, $collection->dav_name );

  $responses = array();
  if ( $collection->is_calendar != 't' ) {
    /**
    * Calendar collections may not contain calendar collections.
    */
    if ( $collection->dav_name == '/' ) {
      $sql = "SELECT user_no, user_no, '/' || username || '/' AS dav_name, md5( '/' || username || '/') AS dav_etag, ";
      $sql .= "to_char(updated at time zone 'GMT',?) AS created, ";
      $sql .= "to_char(updated at time zone 'GMT',?) AS modified, ";
      $sql .= "fullname AS dav_displayname, FALSE AS is_calendar FROM usr ";
      $sql .= "WHERE get_permissions($session->user_no,user_no) ~ '[RAW]';";
    }
    else {
      $sql = "SELECT user_no, dav_name, dav_etag, ";
      $sql .= "to_char(created at time zone 'GMT',?) AS created, ";
      $sql .= "to_char(modified at time zone 'GMT',?) AS modified, ";
      $sql .= "dav_displayname, is_calendar FROM collection ";
      $sql .= "WHERE parent_container=".qpg($collection->dav_name);
    }
    $qry = new PgQuery($sql, PgQuery::Plain(iCalendar::HttpDateFormat()), PgQuery::Plain(iCalendar::HttpDateFormat()));

    if( $qry->Exec("PROPFIND",__LINE__,__FILE__) && $qry->rows > 0 ) {
      while( $subcollection = $qry->Fetch() ) {
        $responses[] = collection_to_xml( $subcollection );
        if ( $depth > 0 ) {
          $responses = array_merge( $responses, get_collection( $depth - 1,  $user_no, $subcollection->dav_name ) );
        }
      }
    }
  }

  dbg_error_log("PROPFIND","Getting collection items: Depth %d, User: %d, Path: %s", $depth, $user_no, $collection->dav_name );

  $sql = "SELECT caldav_data.dav_name, caldav_data, caldav_data.dav_etag, ";
  $sql .= "to_char(coalesce(calendar_item.created, caldav_data.created) at time zone 'GMT',?) AS created, ";
  $sql .= "to_char(last_modified at time zone 'GMT',?) AS modified, ";
  $sql .= "summary AS dav_displayname ";
  $sql .= "FROM caldav_data JOIN calendar_item USING( user_no, dav_name) WHERE dav_name ~ ".qpg('^'.$collection->dav_name.'[^/]+$');
  $sql .= "ORDER BY caldav_data.dav_name ";
  $qry = new PgQuery($sql, PgQuery::Plain(iCalendar::HttpDateFormat()), PgQuery::Plain(iCalendar::HttpDateFormat()));
  if( $qry->Exec("PROPFIND",__LINE__,__FILE__) && $qry->rows > 0 ) {
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
      $sql = "SELECT user_no, '/' || username || '/' AS dav_name, md5( '/' || username || '/') AS dav_etag, ";
      $sql .= "to_char(updated at time zone 'GMT',?) AS created, ";
      $sql .= "to_char(updated at time zone 'GMT',?) AS modified, ";
      $sql .= "fullname AS dav_displayname, FALSE AS is_calendar FROM usr WHERE user_no = $user_no ; ";
    }
    else {
      $sql = "SELECT user_no, dav_name, dav_etag, ";
      $sql .= "to_char(created at time zone 'GMT',?) AS created, ";
      $sql .= "to_char(modified at time zone 'GMT',?) AS modified, ";
      $sql .= "dav_displayname, is_calendar FROM collection WHERE user_no = $user_no AND dav_name = ".qpg($collection_path);
    }
    $qry = new PgQuery($sql, PgQuery::Plain(iCalendar::HttpDateFormat()), PgQuery::Plain(iCalendar::HttpDateFormat()) );
    if( $qry->Exec("PROPFIND",__LINE__,__FILE__) && $qry->rows > 0 && $collection = $qry->Fetch() ) {
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
  if ( $depth > 0 && isset($collection) ) {
    $responses = array_merge($responses, get_collection_contents( $depth-1,  $user_no, $collection ) );
  }
  return $responses;
}

/**
* Get XML response for a single item.  Depth is irrelevant for this.
*/
function get_item( $item_path ) {
  $responses = array();

  dbg_error_log("PROPFIND","Getting item: Path: %s", $item_path );

  $sql = "SELECT caldav_data.dav_name, caldav_data, caldav_data.dav_etag, ";
  $sql .= "to_char(coalesce(calendar_item.created, caldav_data.created) at time zone 'GMT',?) AS created, ";
  $sql .= "to_char(last_modified at time zone 'GMT',?) AS modified, ";
  $sql .= "summary AS dav_displayname ";
  $sql .= "FROM caldav_data JOIN calendar_item USING( user_no, dav_name)  WHERE dav_name = ?;";
  $qry = new PgQuery($sql, PgQuery::Plain(iCalendar::HttpDateFormat()), PgQuery::Plain(iCalendar::HttpDateFormat()), $item_path);
  if( $qry->Exec("PROPFIND",__LINE__,__FILE__) && $qry->rows > 0 ) {
    while( $item = $qry->Fetch() ) {
      $responses[] = item_to_xml( $item );
    }
  }
  return $responses;
}


$request->UnsupportedRequest($unsupported); // Won't return if there was unsupported stuff.

if ( $request->AllowedTo('read') ) {

  /**
  * Something that we can handle, at least roughly correctly.
  */
  $url = $c->protocol_server_port_script . $request->path ;
  $url = preg_replace( '#/$#', '', $url);
  if ( $request->IsCollection() ) {
    $responses = get_collection( $request->depth, $request->user_no, $request->path );
  }
  else {
    $responses = get_item( $request->path );
  }


  $multistatus = new XMLElement( "multistatus", $responses, array('xmlns'=>'DAV:') );
}
else {
  $request->DoResponse( 403, translate("You do not have appropriate rights to view that resource.") );
}

// dbg_log_array( "PROPFIND", "XML", $multistatus, true );
$xmldoc = $multistatus->Render(0,'<?xml version="1.0" encoding="utf-8" ?>');
$etag = md5($xmldoc);
header("ETag: \"$etag\"");
$request->DoResponse( 207, $xmldoc, 'text/xml; charset="utf-8"' );

?>