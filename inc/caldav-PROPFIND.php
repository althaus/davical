<?php
/**
* CalDAV Server - handle PROPFIND method
*
* @package   davical
* @subpackage   propfind
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd, Andrew McMillan
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
*/
dbg_error_log("PROPFIND", "method handler");

if ( ! ($request->AllowedTo('read') || $request->AllowedTo('freebusy')) ) {
  $request->DoResponse( 403, translate("You may not access that calendar") );
}

require_once("iCalendar.php");
require_once("XMLDocument.php");

$href_list = array();
$prop_list = array();
$unsupported = array();
$arbitrary = array();

$reply = new XMLDocument( array( "DAV:" => "" ) );

foreach( $request->xml_tags AS $k => $v ) {

  $ns_tag = $v['tag'];
  dbg_error_log( "PROPFIND", " Handling Tag '%s' => '%s' ", $k, $ns_tag );

  switch ( $ns_tag ) {
    case 'DAV::propfind':
    case 'DAV::prop':
      dbg_error_log( "PROPFIND", ":Request: %s -> %s", $v['type'], $ns_tag );
      break;

    case 'DAV::href':
      $href_list[] = $v['value'];
      dbg_error_log( "PROPFIND", "Adding href '%s'", $v['value'] );
      break;


    /**
    * Handled DAV properties
    */
    case 'DAV::acl':                            /** Only vaguely supported as yet.  Will need work.*/
    case 'DAV::creationdate':                   /** should work fine */
    case 'DAV::getlastmodified':                /** should work fine */
    case 'DAV::displayname':                    /** should work fine */
    case 'DAV::getcontentlength':               /** should work fine */
    case 'DAV::getcontenttype':                 /** should work fine */
    case 'DAV::getetag':                        /** should work fine */
    case 'DAV::supportedlock':                  /** should work fine */
    case 'DAV::principal-URL':                  /** should work fine */
    case 'DAV::owner':                          /** should work fine */
    case 'DAV::resourcetype':                   /** should work fine */
    case 'DAV::getcontentlanguage':             /** should return the user's chosen locale, or default locale */
    case 'DAV::current-user-privilege-set':     /** only vaguely supported */
    case 'DAV::allprop':                        /** limited support, needs to be checked for correctness at some point */

    /**
    * Handled CalDAV properties
    */
    case 'urn:ietf:params:xml:ns:caldav:calendar-home-set':                /** Should work fine */
    case 'urn:ietf:params:xml:ns:caldav:calendar-user-address-set':        /** Should work fine */
    case 'urn:ietf:params:xml:ns:caldav:schedule-inbox-URL':               /** Support in development */
    case 'urn:ietf:params:xml:ns:caldav:schedule-outbox-URL':              /** Support in development */

    /**
    * Handled calendarserver properties
    */
    case 'http://calendarserver.org/ns/:getctag':                        /** Calendar Server extension like etag - should work fine (we just return etag) */

      $prop_list[$ns_tag] = $ns_tag;
      dbg_error_log( "PROPFIND", "Adding attribute '%s'", $ns_tag );
      break;

    /** fixed server definitions - should work fine */
    case 'DAV::supported-collation-set':
    case 'DAV::supported-calendar-component-set':
    case 'DAV::principal-collection-set':
    case 'DAV::supported-privilege-set':

//    case 'dropbox-home-URL':   // HTTP://CALENDARSERVER.ORG/NS/
//    case 'notifications-URL':  // HTTP://CALENDARSERVER.ORG/NS/
      if ( $_SERVER['PATH_INFO'] == '/' || $_SERVER['PATH_INFO'] == '' ) {
        $arbitrary[$ns_tag] = $ns_tag;
        dbg_error_log( "PROPFIND", "Adding arbitrary DAV property '%s'", $ns_tag );
      }
      else {
        $prop_list[$ns_tag] = $ns_tag;
        dbg_error_log( "PROPFIND", "Adding attribute '%s'", $ns_tag );
      }
      break;


    case 'urn:ietf:params:xml:ns:caldav:calendar-timezone':           // Ignored
    case 'urn:ietf:params:xml:ns:caldav:supported-calendar-data':     // Ignored
    case 'urn:ietf:params:xml:ns:caldav:max-resource-size':           // Ignored - should be a server setting
    case 'urn:ietf:params:xml:ns:caldav:min-date-time':               // Ignored - should be a server setting
    case 'urn:ietf:params:xml:ns:caldav:max-date-time':               // Ignored - should be a server setting
    case 'urn:ietf:params:xml:ns:caldav:max-instances':               // Ignored - should be a server setting
    case 'urn:ietf:params:xml:ns:caldav:max-attendees-per-instance':  // Ignored - should be a server setting
      /** These are ignored specifically */
      break;

    /**
    * Add the ones that are specifically unsupported here.
    */
//    case 'DAV::checked-out':   // DAV:
//    case 'DAV::checked-in':    // DAV:
//    case 'DAV::source':        // DAV:
//    case 'DAV::lockdiscovery': // DAV:
//    case 'http://apache.org/dav/props/:executable':         //
    case 'This is not a supported property':  // an impossible example
      $unsupported[$ns_tag] = "";
      dbg_error_log( "PROPFIND", "Unsupported tag >>%s<< ", $ns_tag);
      break;

    /**
    * Arbitrary DAV properties may also be reported
    */
    case 'urn:ietf:params:xml:ns:caldav:calendar-description':        // Supported purely as an arbitrary property
    default:
      $arbitrary[$ns_tag] = $ns_tag;
      dbg_error_log( "PROPFIND", "Adding arbitrary property '%s'", $ns_tag );
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
* Adds any arbitrary properties that were requested by the PROPFIND into the response.
*
* @param reference $prop An XMLElement of the partly constructed response
* @param reference $not_found An XMLElement of properties which are not found
* @param reference $denied An XMLElement of properties to which access is denied
* @param object $record A record with a dav_name attribute which the properties apply to
*/
function add_arbitrary_properties(&$prop, &$not_found, $record) {
  global $arbitrary, $reply;

  $missing = $arbitrary;

  if ( count($arbitrary) > 0 ) {
    $sql = "";
    foreach( $arbitrary AS $k => $v ) {
      $sql .= ($sql == "" ? "" : ", ") . qpg($k);
    }
    $qry = new PgQuery("SELECT property_name, property_value FROM property WHERE dav_name=? AND property_name IN ($sql)", $record->dav_name );
    while( $qry->Exec("PROPFIND") && $property = $qry->Fetch() ) {
      $prop->NewElement($reply->Tag($property->property_name), $property->property_value);
      unset($missing[$property->property_name]);
    }
  }

  if ( count($missing) > 0 ) {
    foreach( $missing AS $k => $v ) {
      $not_found->NewElement($reply->Tag($k), '');
    }
  }

}


/**
* Handles any properties related to the DAV::PRINCIPAL in the request
*/
function add_principal_properties( &$prop, &$not_found, &$denied ) {
  global $prop_list, $session, $c, $request, $reply;

  $allprop = isset($prop_list['DAV::allprop']);

  if ( isset($prop_list['DAV::principal-URL'] ) ) {
    $prop->NewElement("principal-URL", new XMLElement('href', $request->principal->url ) );
  }
  if ( isset($prop_list['DAV::alternate-URI-set'] ) ) {
    $prop->NewElement("alternate-URI-set" );  // Empty - there are no alternatives!
  }

  if ( isset($prop_list['urn:ietf:params:xml:ns:caldav:calendar-home-set'] ) ) {
    $home_set = array();
    $chs = $request->principal->calendar_home_set;
    foreach( $chs AS $k => $url ) {
      $home_set[] = new XMLElement('href', $url );
    }
    $prop->NewElement($reply->Caldav("calendar-home-set"), $home_set );
  }
  if ( isset($prop_list['urn:ietf:params:xml:ns:caldav:schedule-inbox-URL'] ) ) {
    $prop->NewElement($reply->Caldav("schedule-inbox-URL"), new XMLElement('href', $request->principal->schedule_inbox_url) );
  }
  if ( isset($prop_list['urn:ietf:params:xml:ns:caldav:schedule-outbox-URL'] ) ) {
    $prop->NewElement($reply->Caldav("schedule-outbox-URL"), new XMLElement('href', $request->principal->schedule_outbox_url) );
  }

  if ( isset($prop_list['http://calendarserver.org/ns/:dropbox-home-URL'] ) ) {
    $prop->NewElement($reply->Calendarserver("dropbox-home-URL"), new XMLElement('href', $request->principal->dropbox_url) );
  }
  if ( isset($prop_list['http://calendarserver.org/ns/:notifications-URL'] ) ) {
    $prop->NewElement($reply->Calendarserver("notifications-URL"), new XMLElement('href', $request->principal->notifications_url) );
  }

  if ( isset($prop_list['urn:ietf:params:xml:ns:caldav:calendar-user-address-set'] ) ) {
    $addr_set = array();
    $uas = $request->principal->user_address_set;
    foreach( $uas AS $k => $v ) {
      $addr_set[] = new XMLElement('href', $v );
    }
    $prop->NewElement($reply->Caldav("calendar-user-address-set"), $addr_set );
  }
}


/**
* Handles any properties related to the DAV::PRINCIPAL in the request
*/
function add_general_properties( &$prop, &$not_found, &$denied, $record ) {
  global $prop_list, $session, $c, $request, $reply;

  $allprop = isset($prop_list['DAV::allprop']);

  if ( $allprop || isset($prop_list['DAV::getlastmodified']) ) {
    $prop->NewElement("getlastmodified", ( isset($record->modified)? $record->modified : false ));
  }
  if ( $allprop || isset($prop_list['DAV::creationdate']) ) {
    $prop->NewElement("creationdate", $record->created );
  }
  if ( $allprop || isset($prop_list['DAV::getetag']) ) {
    $prop->NewElement("getetag", '"'.$record->dav_etag.'"' );
  }

  if ( isset($prop_list['DAV::owner']) ) {
    $prop->NewElement("owner", new XMLElement('href', 'mailto:'.$request->principal->email ) );
  }
  if ( isset($prop_list['DAV::principal-collection-set']) ) {
    $prop->NewElement("principal-collection-set", new XMLElement('href', ConstructURL('/') ) );
  }

  if ( isset($prop_list['DAV::acl']) ) {
    /**
    * FIXME: This information is semantically valid but presents an incorrect picture.
    */
    $principal = new XMLElement("principal");
    $principal->NewElement("authenticated");
    $grant = new XMLElement( "grant", array(privileges($request->permissions)) );
    $prop->NewElement("acl", new XMLElement( "ace", array( $principal, $grant ) ) );
  }

  if ( $allprop || isset($prop_list['DAV::getcontentlanguage']) ) {
    $contentlength = strlen($item->caldav_data);
    $prop->NewElement("getcontentlanguage", $c->current_locale );
  }

  if ( isset($prop_list['DAV::supportedlock']) ) {
    $prop->NewElement("supportedlock",
       new XMLElement( "lockentry",
         array(
           new XMLElement("lockscope", new XMLElement("exclusive")),
           new XMLElement("locktype",  new XMLElement("write")),
         )
       )
     );
  }

  if ( isset($prop_list['DAV::current-user-privilege-set']) ) {
    $prop->NewElement("current-user-privilege-set", privileges($request->permissions) );
  }

  if ( isset($prop_list['DAV::supported-privilege-set']) ) {
    $prop->NewElement("supported-privilege-set", privileges( $request->SupportedPrivileges(), "supported-privilege") );
  }

}


/**
* Build the <propstat><prop></prop><status></status></propstat> part of the response
*/
function build_propstat_response( $prop, $not_found, $denied, $url ) {

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
* Returns an XML sub-tree for a single collection record from the DB
*/
function collection_to_xml( $collection ) {
  global $arbitrary, $prop_list, $session, $c, $request, $reply;

  dbg_error_log("PROPFIND","Building XML Response for collection '%s'", $collection->dav_name );

  $allprop = isset($prop_list['DAV::allprop']);

  $url = ConstructURL($collection->dav_name);

  $prop = new XMLElement("prop");
  $not_found = new XMLElement("prop");
  $denied = new XMLElement("prop");

  /**
  * First process any static values we do support
  */
  if ( isset($prop_list['urn:ietf:params:xml:ns:caldav:supported-collation-set']) ) {
    $collations = array();
    $collations[] = new XMLElement($reply->Caldav("supported-collation"), 'i;ascii-casemap');
    $collations[] = new XMLElement($reply->Caldav("supported-collation"), 'i;octet');
    $prop->NewElement($reply->Caldav("supported-collation-set"), $collations );
  }
  if ( isset($prop_list['urn:ietf:params:xml:ns:caldav:supported-calendar-component-set']) ) {
    $components = array();
    $components[] = new XMLElement($reply->Caldav("comp"), '', array("name" => "VEVENT"));
    $components[] = new XMLElement($reply->Caldav("comp"), '', array("name" => "VTODO"));
    $prop->NewElement($reply->Caldav("supported-calendar-component-set"), $components );
  }
  if ( $allprop || isset($prop_list['DAV::getcontenttype']) ) {
    $prop->NewElement("getcontenttype", "httpd/unix-directory" );  // Strictly text/icalendar perhaps
  }

  /**
  * Process any dynamic values we do support
  */
  if ( $allprop || isset($prop_list['DAV::getcontentlength'])
                || isset($prop_list['DAV::resourcetype']) ) {
    $resourcetypes = array( new XMLElement("collection") );
    $contentlength = false;
    if ( $collection->is_calendar == 't' ) {
      $resourcetypes[] = new XMLElement($reply->Caldav("calendar"), false);
      $lqry = new PgQuery("SELECT sum(length(caldav_data)) FROM caldav_data WHERE user_no = ? AND dav_name ~ ?;", $collection->user_no, $collection->dav_name.'[^/]+$' );
      if ( $lqry->Exec("PROPFIND",__LINE__,__FILE__) && $row = $lqry->Fetch() ) {
        $contentlength = $row->sum;
      }
    }
    if ( $collection->is_principal == 't' ) {
      $resourcetypes[] = new XMLElement("principal");
    }
    if ( $allprop || isset($prop_list['DAV::getcontentlength']) ) {
      $prop->NewElement("getcontentlength", $contentlength );  // Not strictly correct as a GET on this URL would be longer
    }
    if ( $allprop || isset($prop_list['DAV::resourcetype']) ) {
      $prop->NewElement("resourcetype", $resourcetypes );
    }
  }

  if ( $allprop || isset($prop_list['DAV::displayname']) ) {
    $displayname = ( $collection->dav_displayname == "" ? ucfirst(trim(str_replace("/"," ", $collection->dav_name))) : $collection->dav_displayname );
    $prop->NewElement("displayname", $displayname );
  }
  if ( isset($prop_list['http://calendarserver.org/ns/:getctag']) ) {
    // Calendar Server extension which only applies to collections.  We return the etag, which does the needful.
    $prop->NewElement($reply->Calendarserver('getctag'),$collection->dav_etag );
  }

  if ( isset($prop_list['urn:ietf:params:xml:ns:caldav:calendar-free-busy-set'] ) ) {
    if ( isset($collection->is_inbox) && $collection->is_inbox && $session->user_no == $collection->user_no ) {
      $fb_set = array();
      foreach( $collection->free_busy_set AS $k => $v ) {
        $fb_set[] = new XMLElement('href', $v );
      }
      $prop->NewElement($reply->Caldav("calendar-free-busy-set"), $fb_set );
    }
    else if ( $session->user_no == $collection->user_no ) {
      $not_found->NewElement($reply->Caldav("calendar-free-busy-set") );
    }
    else {
      $denied->NewElement($reply->Caldav("calendar-free-busy-set") );
    }
  }

  /**
  * Then look at any properties related to the principal
  */
  add_principal_properties( $prop, $not_found, $denied );

  /**
  * And any properties that are server/request related, or standard fields
  * from our query.
  */
  add_general_properties( $prop, $not_found, $denied, $collection );

  /**
  * Arbitrary collection properties
  */
  add_arbitrary_properties($prop, $not_found, $collection);

  return build_propstat_response( $prop, $not_found, $denied, $url );
}


/**
* Return XML for a single data item from the DB
*/
function item_to_xml( $item ) {
  global $prop_list, $session, $c, $request, $reply;

  dbg_error_log("PROPFIND","Building XML Response for item '%s'", $item->dav_name );

  $allprop = isset($prop_list['DAV::allprop']);

  $item->properties = get_arbitrary_properties($item->dav_name);

  $url = ConstructURL($item->dav_name);

  $prop = new XMLElement("prop");
  $not_found = new XMLElement("prop");
  $denied  = new XMLElement("prop");


  if ( $allprop || isset($prop_list['DAV::getcontentlength']) ) {
    $contentlength = strlen($item->caldav_data);
    $prop->NewElement("getcontentlength", $contentlength );
  }
  if ( $allprop || isset($prop_list['DAV::getcontenttype']) ) {
    $prop->NewElement("getcontenttype", "text/calendar" );
  }
  if ( $allprop || isset($prop_list['DAV::displayname']) ) {
    $prop->NewElement("displayname", $item->dav_displayname );
  }

  /**
  * Non-collections should return an empty resource type, it appears from RFC2518 8.1.2
  */
  if ( $allprop || isset($prop_list['DAV::resourcetype']) ) {
    $prop->NewElement("resourcetype");
  }

  /**
  * Then look at any properties related to the principal
  */
  add_principal_properties( $prop, $not_found, $denied );

  /**
  * And any properties that are server/request related.
  */
  add_general_properties( $prop, $not_found, $denied, $item );

  return build_propstat_response( $prop, $not_found, $denied, $url );
}

/**
* Get XML response for items in the collection
* If '/' is requested, a list of visible users is given, otherwise
* a list of calendars for the user which are parented by this path.
*/
function get_collection_contents( $depth, $user_no, $collection ) {
  global $session, $request, $reply, $prop_list;

  dbg_error_log("PROPFIND","Getting collection contents: Depth %d, User: %d, Path: %s", $depth, $user_no, $collection->dav_name );

  $responses = array();
  if ( $collection->is_calendar != 't' ) {
    /**
    * Calendar collections may not contain calendar collections.
    */
    if ( $collection->dav_name == '/' ) {
      $sql = "SELECT usr.*, '/' || username || '/' AS dav_name, md5( '/' || username || '/') AS dav_etag, ";
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
        if ( $subcollection->is_principal == "t" ) {
          $principal = new CalDAVPrincipal($subcollection);
          $responses[] = $principal->RenderAsXML($prop_list, &$reply);
        }
        else {
          $responses[] = collection_to_xml( $subcollection );
        }
        if ( $depth > 0 ) {
          $responses = array_merge( $responses, get_collection_contents( $depth - 1,  $user_no, $subcollection ) );
        }
      }
    }
  }

  /**
  * freebusy permission is not allowed to see the items in a collection.  Must have at least read permission.
  */
  if ( $request->AllowedTo('read') ) {
    dbg_error_log("PROPFIND","Getting collection items: Depth %d, User: %d, Path: %s", $depth, $user_no, $collection->dav_name );
    $privacy_clause = " ";
    if ( ! $request->AllowedTo('all') ) {
      $privacy_clause = " AND (calendar_item.class != 'PRIVATE' OR calendar_item.class IS NULL) ";
    }

    $sql = "SELECT caldav_data.dav_name, caldav_data, caldav_data.dav_etag, ";
    $sql .= "to_char(coalesce(calendar_item.created, caldav_data.created) at time zone 'GMT',?) AS created, ";
    $sql .= "to_char(last_modified at time zone 'GMT',?) AS modified, ";
    $sql .= "summary AS dav_displayname ";
    $sql .= "FROM caldav_data JOIN calendar_item USING( dav_id, user_no, dav_name) ";
    $sql .= "WHERE dav_name ~ ".qpg('^'.$collection->dav_name.'[^/]+$'). $privacy_clause;
    if ( isset($c->strict_result_ordering) && $c->strict_result_ordering ) $sql .= " ORDER BY dav_id";
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
  global $session, $request;
  $responses = array();

  dbg_error_log("PROPFIND","Getting item: Path: %s", $item_path );

  $privacy_clause = " ";
  if ( ! $request->AllowedTo('all') ) {
    $privacy_clause = " AND (calendar_item.class != 'PRIVATE' OR calendar_item.class IS NULL) ";
  }

  $sql = "SELECT caldav_data.dav_name, caldav_data, caldav_data.dav_etag, ";
  $sql .= "to_char(coalesce(calendar_item.created, caldav_data.created) at time zone 'GMT',?) AS created, ";
  $sql .= "to_char(last_modified at time zone 'GMT',?) AS modified, ";
  $sql .= "summary AS dav_displayname ";
  $sql .= "FROM caldav_data JOIN calendar_item USING( user_no, dav_name)  WHERE dav_name = ? $privacy_clause";
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


$multistatus = new XMLElement( "multistatus", $responses, $reply->GetXmlNsArray() );

// dbg_log_array( "PROPFIND", "XML", $multistatus, true );
$xmldoc = $multistatus->Render(0,'<?xml version="1.0" encoding="utf-8" ?>');
$etag = md5($xmldoc);
header("ETag: \"$etag\"");
$request->DoResponse( 207, $xmldoc, 'text/xml; charset="utf-8"' );

