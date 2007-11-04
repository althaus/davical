<?php
/**
* CalDAV Server - handle PROPFIND method
*
* @package   davical
* @subpackage   propfind
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("PROPFIND", "method handler");

if ( ! ($request->AllowedTo('read') || $request->AllowedTo('freebusy')) ) {
  $request->DoResponse( 403, translate("You may not access that calendar") );
}

require_once("XMLElement.php");
require_once("iCalendar.php");

$href_list = array();
$attribute_list = array();
$unsupported = array();
$arbitrary = array();

$namespaces = array( "xmlns" => "DAV:" );

function add_namespace( $prefix, $namespace ) {
  global $namespaces;

  $prefix = 'xmlns:'.$prefix;
  if ( !isset($namespaces[$prefix]) || $namespaces[$prefix] != $namespace ) {
    $namespaces[$prefix] = $namespace;
  }
}


foreach( $request->xml_tags AS $k => $v ) {

  $ns_tag = $v['tag'];
  if ( preg_match('/^(.*):([^:]+)$/', $ns_tag, $matches) ) {
    $namespace = $matches[1];
    $tag = $matches[2];
  }
  else {
    $namespace = "";
    $tag = $ns_tag;
  }
  dbg_error_log( "PROPFIND", " Handling Tag '%s' => '%s' ", $k, $v );

  switch ( $tag ) {
    case 'PROPFIND':
    case 'PROP':
      dbg_error_log( "PROPFIND", ":Request: %s -> %s", $v['type'], $tag );
      break;

    case 'HREF':
      // dbg_log_array( "PROPFIND", "HREF", $v, true );
      $href_list[] = $v['value'];
      dbg_error_log( "PROPFIND", "Adding href '%s'", $v['value'] );
      break;


    /**
    * Handled DAV properties
    */
    case 'ACL':                            /** acl             - only vaguely supported */
    case 'CREATIONDATE':                   /** creationdate    - should work fine */
    case 'GETLASTMODIFIED':                /** getlastmodified - should work fine */
    case 'DISPLAYNAME':                    /** displayname     - should work fine */
    case 'GETCONTENTLENGTH':               /** getcontentlength- should work fine */
    case 'GETCONTENTTYPE':                 /** getcontenttype  - should work fine */
    case 'GETETAG':                        /** getetag         - should work fine */
    case 'SUPPORTEDLOCK':                  /** supportedlock   - should work fine */
    case 'PRINCIPAL-URL':                  /** principal-url   - should work fine */
    case 'RESOURCETYPE':                   /** resourcetype    - should work fine */
    case 'GETCONTENTLANGUAGE':             /** resourcetype    - should return the user's chosen locale, or default locale */
    case 'SUPPORTED-PRIVILEGE-SET':        /** supported-privilege-set    - should work fine */
    case 'CURRENT-USER-PRIVILEGE-SET':     /** current-user-privilege-set - only vaguely supported */
    case 'ALLPROP':                        /** allprop - limited support */
      $attribute_list[$tag] = 1;
      dbg_error_log( "PROPFIND", "Adding %s attribute '%s'", $namespace, $tag );
      break;



    /**
    * Handled CalDAV properties
    */
    case 'CALENDAR-HOME-SET':                /** calendar-home-set is used by iCal in Leopard - should work fine */
    case 'SUPPORTED-COLLATION-SET':          /** fixed server definition - should work fine */
    case 'SUPPORTED-CALENDAR-COMPONENT-SET': /** fixed server definition - should work fine */

    /**
    * Handled calendar-schedule properties
    */
    case 'CALENDAR-USER-ADDRESS-SET':        /** CalDAV+s: slightly supported */
//    case 'SCHEDULE-INBOX-URL':               /** CalDAV+s: not supported */
//    case 'SCHEDULE-OUTBOX-URL':              /** CalDAV+s: not supported */
//    case 'DROPBOX-HOME-URL':   // HTTP://CALENDARSERVER.ORG/NS/
//    case 'NOTIFICATIONS-URL':  // HTTP://CALENDARSERVER.ORG/NS/
      if ( $_SERVER['PATH_INFO'] == '/' || $_SERVER['PATH_INFO'] == '' ) {
        $arbitrary[$ns_tag] = $ns_tag;
        dbg_error_log( "PROPFIND", "Adding arbitrary DAV property '%s'", $ns_tag );
      }
      else {
        $attribute_list[$tag] = 1;
        dbg_error_log( "PROPFIND", "Adding %s attribute '%s'", $namespace, $tag );
      }
      break;


    case 'CALENDAR-TIMEZONE':           // CalDAV
    case 'SUPPORTED-CALENDAR-DATA':     // CalDAV
    case 'MAX-RESOURCE-SIZE':           // CalDAV
    case 'MIN-DATE-TIME':               // CalDAV
    case 'MAX-DATE-TIME':               // CalDAV
    case 'MAX-INSTANCES':               // CalDAV
    case 'MAX-ATTENDEES-PER-INSTANCE':  // CalDAV
//    case 'CHECKED-OUT':   // DAV:
//    case 'CHECKED-IN':    // DAV:
//    case 'SOURCE':        // DAV:
//    case 'LOCKDISCOVERY': // DAV:
//    case 'EXECUTABLE':         // HTTP://APACHE.ORG/DAV/PROPS/
      /** These are ignored specifically */
      break;

    /**
    * Add the ones that are specifically unsupported here.
    */
    case 'This is not a supported property':  // an impossible example
      $unsupported[$tag] = "";
      dbg_error_log( "PROPFIND", "Unsupported tag >>%s<< in xmlns >>%s<<", $tag, $namespace);
      break;

    /**
    * Arbitrary DAV properties may also be reported
    */
    case 'CALENDAR-DESCRIPTION':        // CalDAV, informational
    default:
      $arbitrary[$ns_tag] = $ns_tag;
      dbg_error_log( "PROPFIND", "Adding arbitrary DAV property '%s'", $ns_tag );
      break;
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
* Fetches any arbitrary properties that were requested by the PROPFIND into an
* array, which we return.
* @return array The arbitrary properties.
*/
function get_arbitrary_properties($dav_name) {
  global $arbitrary;

  $results = (object) array( 'found' => array(), 'missing' => $arbitrary );

  if ( count($arbitrary) > 0 ) {
    $sql = "";
    foreach( $arbitrary AS $k => $v ) {
      $sql .= ($sql == "" ? "" : ", ") . qpg($k);
    }
    $qry = new PgQuery("SELECT property_name, property_value FROM property WHERE dav_name=? AND property_name IN ($sql)", $dav_name );
    while( $qry->Exec("PROPFIND") && $property = $qry->Fetch() ) {
      $results->found[$property->property_name] = $property->property_value;
      unset($results->missing[$property->property_name]);
    }
  }

  return $results;
}


/**
* Handles any properties related to the DAV::PRINCIPAL in the request
*/
function add_principal_properties( &$prop, &$not_found, &$denied ) {
  global $attribute_list, $session, $c, $request;

  if ( isset($attribute_list['PRINCIPAL-URL'] ) ) {
    $prop->NewElement("principal-url", new XMLElement('href', $request->principal->url ) );
  }

  if ( isset($attribute_list['CALENDAR-HOME-SET'] ) ) {
    add_namespace("C", "urn:ietf:params:xml:ns:caldav");
    $prop->NewElement("C:calendar-home-set", new XMLElement('href', $request->principal->calendar_home_set ) );
  }
  if ( isset($attribute_list['SCHEDULE-INBOX-URL'] ) ) {
    add_namespace("C", "urn:ietf:params:xml:ns:caldav");
    $prop->NewElement("C:schedule-inbox-url", new XMLElement('href', $request->principal->schedule_inbox_url) );
  }
  if ( isset($attribute_list['SCHEDULE-OUTBOX-URL'] ) ) {
    add_namespace("C", "urn:ietf:params:xml:ns:caldav");
    $prop->NewElement("C:schedule-outbox-url", new XMLElement('href', $request->principal->schedule_outbox_url) );
  }

  if ( isset($attribute_list['DROPBOX-HOME-URL'] ) ) {
    add_namespace("A", "http://calendarserver.org/ns/");
    $prop->NewElement("A:dropbox-home-url", new XMLElement('href', $request->principal->dropbox_url) );
  }
  if ( isset($attribute_list['NOTIFICATIONS-URL'] ) ) {
    add_namespace("A", "http://calendarserver.org/ns/");
    $prop->NewElement("A:notifications-url", new XMLElement('href', $request->principal->notifications_url) );
  }

  if ( isset($attribute_list['CALENDAR-USER-ADDRESS-SET'] ) ) {
    add_namespace("C", "urn:ietf:params:xml:ns:caldav");
    $email = new XMLElement('href', 'mailto:'.$request->principal->email );
    $calendar = new XMLElement('href', $request->principal->calendar_home_set );
    $prop->NewElement("C:calendar-user-address-set", array( $calendar, $email) );
  }
}


/**
* Returns an XML sub-tree for a single collection record from the DB
*/
function collection_to_xml( $collection ) {
  global $arbitrary, $attribute_list, $session, $c, $request;

  dbg_error_log("PROPFIND","Building XML Response for collection '%s'", $collection->dav_name );

  $arbitrary_results = get_arbitrary_properties($collection->dav_name);
  $collection->properties = $arbitrary_results->found;

  $url = $c->protocol_server_port_script . $collection->dav_name;
  $url = preg_replace( '#^(https?://.+)//#', '$1/', $url );  // Ensure we don't double any '/'

  $resourcetypes = array( new XMLElement("collection") );
  $contentlength = false;
  if ( $collection->is_calendar == 't' ) {
    add_namespace("C", "urn:ietf:params:xml:ns:caldav");
    $resourcetypes[] = new XMLElement("C:calendar", false);
    $lqry = new PgQuery("SELECT sum(length(caldav_data)) FROM caldav_data WHERE user_no = ? AND dav_name ~ ?;", $collection->user_no, $collection->dav_name.'[^/]+$' );
    if ( $lqry->Exec("PROPFIND",__LINE__,__FILE__) && $row = $lqry->Fetch() ) {
      $contentlength = $row->sum;
    }
  }
  if ( $collection->is_principal == 't' ) {
    $resourcetypes[] = new XMLElement("principal");
  }
  $prop = new XMLElement("prop");
  $not_found = new XMLElement("prop");
  $denied = new XMLElement("prop");

  /**
  * First process any static values we do support
  */
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['SUPPORTED-COLLATION-SET']) ) {
    add_namespace("C", "urn:ietf:params:xml:ns:caldav");
    $collations = array();
    $collations[] = new XMLElement("C:supported-collation", 'i;ascii-casemap');
    $collations[] = new XMLElement("C:supported-collation", 'i;octet');
    $prop->NewElement("C:supported-collation-set", $collations );
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['SUPPORTED-CALENDAR-COMPONENT-SET']) ) {
    add_namespace("C", "urn:ietf:params:xml:ns:caldav");
    $components = array();
    $components[] = new XMLElement("C:comp", '', array("name" => "VEVENT"));
    $components[] = new XMLElement("C:comp", '', array("name" => "VTODO"));
    $prop->NewElement("C:supported-calendar-component-set", $components );
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['GETCONTENTTYPE']) ) {
    $prop->NewElement("getcontenttype", "httpd/unix-directory" );
  }

  /**
  * Second process any dynamic values we do support
  */
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['GETLASTMODIFIED']) ) {
    $prop->NewElement("getlastmodified", ( isset($collection->modified)? $collection->modified : false ));
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['GETCONTENTLENGTH']) ) {
    $prop->NewElement("getcontentlength", $contentlength );
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['CREATIONDATE']) ) {
    $prop->NewElement("creationdate", $collection->created );
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['RESOURCETYPE']) ) {
    $prop->NewElement("resourcetype", $resourcetypes );
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['DISPLAYNAME']) ) {
    $displayname = ( $collection->dav_displayname == "" ? ucfirst(trim(str_replace("/"," ", $collection->dav_name))) : $collection->dav_displayname );
    if ( preg_match( '/ iCal 3\.0/', $request->user_agent ) ) {
      /** FIXME: There is a bug in iCal 3 which disables calendars with a displayname containing spaces */
      $displayname = str_replace( ' ', '_', $displayname );
    }
    $prop->NewElement("displayname", $displayname );
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['GETETAG']) ) {
    $prop->NewElement("getetag", '"'.$collection->dav_etag.'"' );
  }
  if ( isset($attribute_list['ALLPROP']) || isset($attribute_list['CURRENT-USER-PRIVILEGE-SET']) ) {
    $prop->NewElement("current-user-privilege-set", privileges($request->permissions) );
  }

  if ( isset($attribute_list['CALENDAR-FREE-BUSY-SET'] ) ) {
    add_namespace("C", "urn:ietf:params:xml:ns:caldav");
    if ( isset($collection->is_inbox) && $collection->is_inbox && $session->user_no == $collection->user_no ) {
      $fb_set = array();
      foreach( $collection->free_busy_set AS $k => $v ) {
        $fb_set[] = new XMLElement('href', $v );
      }
      $prop->NewElement("C:calendar-free-busy-set", $fb_set );
    }
    else if ( $session->user_no == $collection->user_no ) {
      $not_found->NewElement("C:calendar-free-busy-set" );
    }
    else {
      $denied->NewElement("C:calendar-free-busy-set" );
    }
  }


  /**
  * Then look at any properties related to the principal
  */
  add_principal_properties( $prop, $not_found, $denied );

  if ( count($collection->properties) > 0 ) {
    foreach( $collection->properties AS $k => $v ) {
      $prop->NewElement($k, $v );
    }
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
  $response = array($href,$propstat);

  if ( count($arbitrary_results->missing) > 0 ) {
    foreach( $arbitrary_results->missing AS $k => $v ) {
      if ( preg_match('/^(.*):([^:]+)$/', $k, $matches) ) {
        $namespace = $matches[1];
        $tag = $matches[2];
      }
      else {
        $namespace = "";
        $tag = $k;
      }
      $not_found->NewElement(strtolower($tag), '', array("xmlns" => strtolower($namespace)) );
    }
  }

  if ( is_array($not_found->content) && count($not_found->content) > 0 ) {
    $response[] = new XMLElement( "propstat", array( $not_found, new XMLElement("status", "HTTP/1.1 404 Not Found" )) );
  }

  if ( is_array($denied->content) && count($denied->content) > 0 ) {
    $response[] = new XMLElement( "propstat", array( $denied, new XMLElement("status", "HTTP/1.1 403 Forbidden" )) );
  }

  $response = new XMLElement( "response", $response );

  return $response;
}


/**
* Return XML for a single data item from the DB
*/
function item_to_xml( $item ) {
  global $attribute_list, $session, $c, $request;

  dbg_error_log("PROPFIND","Building XML Response for item '%s'", $item->dav_name );

  $item->properties = get_arbitrary_properties($item->dav_name);

  $url = $_SERVER['SCRIPT_NAME'] . $item->dav_name;
  $prop = new XMLElement("prop");
  $not_found = new XMLElement("prop");
  $denied  = new XMLElement("prop");


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

  /**
  * Then look at any properties related to the principal
  */
  add_principal_properties( $prop, $not_found, $denied );

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
  $response = array($href,$propstat);

  if ( is_array($not_found->content) && count($not_found->content) > 0 ) {
    $response[] = new XMLElement( "propstat", array( $not_found, new XMLElement("status", "HTTP/1.1 404 Not Found" )) );
  }

  if ( is_array($denied->content) && count($denied->content) > 0 ) {
    $response[] = new XMLElement( "propstat", array( $denied, new XMLElement("status", "HTTP/1.1 403 Forbidden" )) );
  }

  $response = new XMLElement( "response", $response );

  return $response;
}

/**
* Get XML response for items in the collection
* If '/' is requested, a list of visible users is given, otherwise
* a list of calendars for the user which are parented by this path.
*/
function get_collection_contents( $depth, $user_no, $collection ) {
  global $session, $request;

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
      $sql .= "fullname AS dav_displayname, FALSE AS is_calendar, TRUE AS is_principal FROM usr ";
      $sql .= "WHERE get_permissions($session->user_no,user_no) ~ '[RAW]';";
    }
    else {
      $sql = "SELECT user_no, dav_name, dav_etag, ";
      $sql .= "to_char(created at time zone 'GMT',?) AS created, ";
      $sql .= "to_char(modified at time zone 'GMT',?) AS modified, ";
      $sql .= "dav_displayname, is_calendar, FALSE AS is_principal FROM collection ";
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

  /**
  * freebusy permission is not allowed to see the items in a collection.  Must have at least read permission.
  */
  if ( $request->AllowedTo('read') ) {
    dbg_error_log("PROPFIND","Getting collection items: Depth %d, User: %d, Path: %s", $depth, $user_no, $collection->dav_name );

    $sql = "SELECT caldav_data.dav_name, caldav_data, caldav_data.dav_etag, ";
    $sql .= "to_char(coalesce(calendar_item.created, caldav_data.created) at time zone 'GMT',?) AS created, ";
    $sql .= "to_char(last_modified at time zone 'GMT',?) AS modified, ";
    $sql .= "summary AS dav_displayname ";
    $sql .= "FROM caldav_data JOIN calendar_item USING( user_no, dav_name) WHERE dav_name ~ ".qpg('^'.$collection->dav_name.'[^/]+$');
    $sql .= " AND (calendar_item.class != 'PRIVATE' OR calendar_item.class IS NULL OR get_permissions($session->user_no,caldav_data.user_no) ~ 'A') "; // Must have 'all' permissions to see confidential items
    $sql .= "ORDER BY caldav_data.dav_name ";
    $qry = new PgQuery($sql, PgQuery::Plain(iCalendar::HttpDateFormat()), PgQuery::Plain(iCalendar::HttpDateFormat()));
    if( $qry->Exec("PROPFIND",__LINE__,__FILE__) && $qry->rows > 0 ) {
      while( $item = $qry->Fetch() ) {
        $responses[] = item_to_xml( $item );
      }
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

  if ( $collection_path == null || $collection_path == '/' || $collection_path == '' ) {
    $collection->dav_name = $collection_path;
    $collection->dav_etag = md5($c->system_name . $collection_path);
    $collection->is_calendar = 'f';
    $collection->is_principal = 'f';
    $collection->dav_displayname = $c->system_name;
    $collection->created = date('Ymd\THis');
    $responses[] = collection_to_xml( $collection );
  }
  else {
    $user_no = intval($user_no);
    if ( preg_match( '#^/[^/]+/$#', $collection_path) ) {
      $sql = "SELECT user_no, '/' || username || '/' AS dav_name, md5( '/' || username || '/') AS dav_etag, ";
      $sql .= "to_char(updated at time zone 'GMT',?) AS created, ";
      $sql .= "to_char(updated at time zone 'GMT',?) AS modified, ";
      $sql .= "fullname AS dav_displayname, FALSE AS is_calendar, TRUE AS is_principal FROM usr WHERE user_no = $user_no ; ";
    }
    else {
      $sql = "SELECT user_no, dav_name, dav_etag, ";
      $sql .= "to_char(created at time zone 'GMT',?) AS created, ";
      $sql .= "to_char(modified at time zone 'GMT',?) AS modified, ";
      $sql .= "dav_displayname, is_calendar, FALSE AS is_principal FROM collection WHERE user_no = $user_no AND dav_name = ".qpg($collection_path);
    }
    $qry = new PgQuery($sql, PgQuery::Plain(iCalendar::HttpDateFormat()), PgQuery::Plain(iCalendar::HttpDateFormat()) );
    if( $qry->Exec("PROPFIND",__LINE__,__FILE__) && $qry->rows > 0 && $collection = $qry->Fetch() ) {
      $responses[] = collection_to_xml( $collection );
    }
    elseif ( $c->collections_always_exist ) {
      $collection->dav_name = $collection_path;
      $collection->dav_etag = md5($collection_path);
      $collection->is_calendar = 't';  // Everything is a calendar, if it always exists!
      $collection->is_principal = 'f';
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
  global $session;
  $responses = array();

  dbg_error_log("PROPFIND","Getting item: Path: %s", $item_path );

  $sql = "SELECT caldav_data.dav_name, caldav_data, caldav_data.dav_etag, ";
  $sql .= "to_char(coalesce(calendar_item.created, caldav_data.created) at time zone 'GMT',?) AS created, ";
  $sql .= "to_char(last_modified at time zone 'GMT',?) AS modified, ";
  $sql .= "summary AS dav_displayname ";
  $sql .= "FROM caldav_data JOIN calendar_item USING( user_no, dav_name)  WHERE dav_name = ? ";
  $sql .= "AND (calendar_item.class != 'PRIVATE' OR calendar_item.class IS NULL OR get_permissions($session->user_no,caldav_data.user_no) ~ 'A') "; // Must have 'all' permissions to see confidential items
  $qry = new PgQuery($sql, PgQuery::Plain(iCalendar::HttpDateFormat()), PgQuery::Plain(iCalendar::HttpDateFormat()), $item_path);
  if( $qry->Exec("PROPFIND",__LINE__,__FILE__) && $qry->rows > 0 ) {
    while( $item = $qry->Fetch() ) {
      $responses[] = item_to_xml( $item );
    }
  }
  return $responses;
}


$request->UnsupportedRequest($unsupported); // Won't return if there was unsupported stuff.

/**
* Something that we can handle, at least roughly correctly.
*/
$url = $c->protocol_server_port_script . $request->path ;
$url = preg_replace( '#/$#', '', $url);
if ( $request->IsCollection() ) {
  $responses = get_collection( $request->depth, $request->user_no, $request->path );
}
elseif ( $request->AllowedTo('read') ) {
  $responses = get_item( $request->path );
}
else {
  $request->DoResponse( 403, translate("You do not have appropriate rights to view that resource.") );
}

$multistatus = new XMLElement( "multistatus", $responses, $namespaces );

// dbg_log_array( "PROPFIND", "XML", $multistatus, true );
$xmldoc = $multistatus->Render(0,'<?xml version="1.0" encoding="utf-8" ?>');
$etag = md5($xmldoc);
header("ETag: \"$etag\"");
$request->DoResponse( 207, $xmldoc, 'text/xml; charset="utf-8"' );

