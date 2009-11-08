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
dbg_error_log('PROPFIND', 'method handler');

if ( isset($c->new_propfind) && $c->new_propfind ) {

}

if ( ! ($request->AllowedTo('read') || $request->AllowedTo('freebusy')) ) {
  $request->DoResponse( 403, translate('You may not access that calendar') );
}

require_once('iCalendar.php');
require_once('XMLDocument.php');

$href_list = array();
$prop_list = array();
$unsupported = array();
$arbitrary = array();

$reply = new XMLDocument( array( 'DAV:' => '' ) );

foreach( $request->xml_tags AS $k => $v ) {

  $ns_tag = $v['tag'];
  dbg_error_log( 'PROPFIND', ' Handling Tag "%s" => "%s" ', $k, $ns_tag );

  switch ( $ns_tag ) {
    case 'DAV::propfind':
    case 'DAV::prop':
      dbg_error_log( 'PROPFIND', ':Request: %s -> %s', $v['type'], $ns_tag );
      break;

    case 'DAV::href':
      $href_list[] = $v['value'];
      dbg_error_log( 'PROPFIND', 'Adding href "%s"', $v['value'] );
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
    case 'DAV::current-user-principal':         /** should work fine */
    case 'DAV::getcontentlanguage':             /** should return the user's chosen locale, or default locale */
    case 'DAV::current-user-privilege-set':     /** only vaguely supported */
    case 'DAV::allprop':                        /** limited support, needs to be checked for correctness at some point */
    case 'DAV::group-member-set':               /** limited support, at the moment used for caldav proxy */
    case 'DAV::group-membership':				/** limited support, at the moment used for caldav proxy */
    case 'DAV::supported-method-set':           /** Should work fine */
    case 'DAV::supported-report-set':           /** Should work fine */

    /**
    * Handled CalDAV properties
    */
    case 'urn:ietf:params:xml:ns:caldav:calendar-home-set':                /** Should work fine */
    case 'urn:ietf:params:xml:ns:caldav:calendar-user-address-set':        /** Should work fine */
    case 'urn:ietf:params:xml:ns:caldav:schedule-inbox-URL':               /** Support in development */
    case 'urn:ietf:params:xml:ns:caldav:schedule-outbox-URL':              /** Support in development */
    case 'urn:ietf:params:xml:ns:caldav:calendar-free-busy-set':           /** Deprecated, but should work fine */
    case 'urn:ietf:params:xml:ns:caldav:supported-calendar-component-set': /** Should work fine */

    /**
    * Handled calendarserver properties
    */
    case 'http://calendarserver.org/ns/:getctag':                        /** Calendar Server extension like etag - should work fine (we just return etag) */
    case 'http://calendarserver.org/ns/:calendar-proxy-read-for':	       /** Calendar Server Delegation readonly */
    case 'http://calendarserver.org/ns/:calendar-proxy-write-for':       /** Calendar Server Delegation read-write */

      $prop_list[$ns_tag] = $ns_tag;
      dbg_error_log( 'PROPFIND', 'Adding attribute "%s"', $ns_tag );
      break;

    /** fixed server definitions - should work fine */
    case 'DAV::supported-collation-set':
    case 'DAV::principal-collection-set':
    case 'DAV::supported-privilege-set':
      if ( $_SERVER['PATH_INFO'] == '/' || $_SERVER['PATH_INFO'] == '' ) {
        $arbitrary[$ns_tag] = $ns_tag;
        dbg_error_log( 'PROPFIND', 'Adding arbitrary DAV property "%s"', $ns_tag );
      }
      else {
        $prop_list[$ns_tag] = $ns_tag;
        dbg_error_log( 'PROPFIND', 'Adding attribute "%s"', $ns_tag );
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
      $unsupported[$ns_tag] = '';
      dbg_error_log( 'PROPFIND', 'Unsupported tag >>%s<< ', $ns_tag);
      break;

    /**
    * Arbitrary DAV properties may also be reported
    */
    case 'http://calendarserver.org/ns/:dropbox-home-URL':    // Not yet supported (what is it supposed to do?)
    case 'http://calendarserver.org/ns/:notifications-URL':   // Not yet supported (what is it supposed to do?)
    case 'urn:ietf:params:xml:ns:caldav:calendar-description':        // Supported purely as an arbitrary property
    default:
      $arbitrary[$ns_tag] = $ns_tag;
      dbg_error_log( 'PROPFIND', 'Adding arbitrary property "%s"', $ns_tag );
      break;
  }
}

 /**
  * Returns a set of hrefs from a set of urls
  */
function href_set_from_paths( $path_set ) {
  global $reply;
  $href_set = array();
  foreach ($path_set AS $k => $v) {
     $href_set[] = $reply->href( $v );
  }
  return $href_set;
}

/**
* Returns the array of privilege names converted into XMLElements
*/
function privileges($privilege_names, $container='privilege') {
  global $reply;
  $privileges = array();
  foreach( $privilege_names AS $k => $v ) {
    $privilege = $reply->NewXMLElement($container);
    $reply->NSElement($privilege, $k);
    $privileges[] = $privilege;
  }
  return $privileges;
}


/**
* Adds any arbitrary properties that were requested by the PROPFIND into the response.
*
* @param reference $prop An XMLElement of the partly constructed response
* @param reference $denied An XMLElement of properties to which access is denied
* @param object $record A record with a dav_name attribute which the properties apply to
*/
function add_arbitrary_properties(&$prop, $record) {
  global $arbitrary, $reply;

  if ( count($arbitrary) > 0 ) {
    $sql = '';
    foreach( $arbitrary AS $k => $v ) {
      $sql .= ($sql == '' ? '' : ', ') . qpg($k);
    }
    $qry = new PgQuery('SELECT property_name, property_value FROM property WHERE dav_name=? AND property_name IN ('.$sql.')', $record->dav_name );
    while( $qry->Exec('PROPFIND') && $property = $qry->Fetch() ) {
      $reply->NSElement($prop, $property->property_name, $property->property_value);
    }
  }
}


/**
* Handles any properties related to the DAV::PRINCIPAL in the request
*/
function add_principal_properties( &$prop, &$denied ) {
  global $prop_list, $session, $c, $request, $reply;

  dbg_error_log('PROPFIND', 'Adding principal properties');

  $allprop = isset($prop_list['DAV::allprop']);

  if ( isset($prop_list['DAV::principal-URL'] ) ) {
    $reply->DAVElement( $prop, 'principal-URL', $reply->href( $request->principal->principal_url ) );
  }
  if ( isset($prop_list['DAV::alternate-URI-set'] ) ) {
    $reply->DAVElement( $prop, 'alternate-URI-set' );  // Empty - there are no alternatives!
  }

  if ( isset($prop_list['urn:ietf:params:xml:ns:caldav:calendar-home-set'] ) ) {
    $reply->CalDAVElement( $prop, 'calendar-home-set', href_set_from_paths( $request->principal->calendar_home_set ) );
  }
  if ( isset($prop_list['urn:ietf:params:xml:ns:caldav:schedule-inbox-URL'] ) ) {
    $reply->CalDAVElement( $prop, 'schedule-inbox-URL', $reply->href( $request->principal->schedule_inbox_url) );
  }
  if ( isset($prop_list['urn:ietf:params:xml:ns:caldav:schedule-outbox-URL'] ) ) {
    $reply->CalDAVElement( $prop, 'schedule-outbox-URL', $reply->href( $request->principal->schedule_outbox_url) );
  }
/*  Not supported - don't pretend we do!
  if ( isset($prop_list['http://calendarserver.org/ns/:dropbox-home-URL'] ) ) {
    $reply->CalendarserverElement($prop, 'dropbox-home-URL', $reply->href( $request->principal->dropbox_url) );
  }
  if ( isset($prop_list['http://calendarserver.org/ns/:notifications-URL'] ) ) {
    $reply->CalendarserverElement($prop, 'notifications-URL', $reply->href( $request->principal->notifications_url) );
  }
*/
  if ( isset($prop_list['urn:ietf:params:xml:ns:caldav:calendar-user-address-set'] ) ) {
    $reply->CalDAVElement( $prop, 'calendar-user-address-set', href_set_from_paths( $request->principal->user_address_set ) );
  }
}


/**
* Handles any properties related to the DAV::PRINCIPAL in the request
*/
function add_general_properties( &$prop, &$denied, $record ) {
  global $prop_list, $session, $c, $request, $reply;

  $allprop = isset($prop_list['DAV::allprop']);

  if ( $allprop || isset($prop_list['DAV::getlastmodified']) ) {
    $reply->DAVElement( $prop, 'getlastmodified', ( isset($record->modified)? $record->modified : false ));
  }
  if ( $allprop || isset($prop_list['DAV::creationdate']) ) {
    $reply->DAVElement( $prop, 'creationdate', $record->created );
  }
  if ( $allprop || isset($prop_list['DAV::getetag']) ) {
    $reply->DAVElement( $prop, 'getetag', '"'.$record->dav_etag.'"' );
  }

  if ( isset($prop_list['DAV::owner']) ) {
    // After a careful reading of RFC3744 we see that this must be the principal-URL of the owner
    $reply->DAVElement( $prop, 'owner', $reply->href( $request->principal->url ) );
  }
  if ( isset($prop_list['DAV::principal-collection-set']) ) {
    $reply->DAVElement( $prop, 'principal-collection-set', $reply->href( ConstructURL('/') ) );
  }
  if ( isset($prop_list['DAV::current-user-principal']) ) {
    $reply->DAVElement( $prop, 'current-user-principal', $request->current_user_principal_xml);
  }

 // caldav proxy
  // as per 5.1 paragraph 5
  // TODO: this duplicates code below. if possible, do said code only once.
  if ( preg_match('#/[^/]+/calendar-proxy-(read|write)/?#', $record->dav_displayname, $matches) && isset($prop_list['DAV::group-member-set']) ) {
  	if ($matches[1] == 'read') {
        $reply->DAVElement($prop, 'group-member-set', href_set_from_paths( $request->principal->ReadProxyGroup() ) );
  	} else /* if ($matches[1] == 'write') */ {
  		$reply->DAVElement($prop, 'group-member-set', href_set_from_paths( $request->principal->WriteProxyGroup() ) );
  	}
  }

  if ( isset($prop_list['DAV::acl']) ) {
    /**
    * @todo This information is semantically valid but presents an incorrect picture.
    */
    $principal = $reply->NewXMLElement('principal');
    $reply->DAVElement( $principal, 'authenticated');
    $grant = $reply->NewXMLElement( 'grant', array(privileges($request->permissions)) );
    $reply->DAVElement( $prop, 'acl', $reply->NewXMLElement( 'ace', array( $principal, $grant ) ) );
  }

  if ( $allprop || isset($prop_list['DAV::getcontentlanguage']) ) {
    $reply->DAVElement( $prop, 'getcontentlanguage', (isset($c->current_locale) ? $c->current_locale : '') );
  }

  if ( isset($prop_list['DAV::supportedlock']) ) {
    $reply->DAVElement( $prop, 'supportedlock',
       $reply->NewXMLElement( 'lockentry',
         array(
           $reply->NewXMLElement('lockscope', $reply->NewXMLElement('exclusive')),
           $reply->NewXMLElement('locktype',  $reply->NewXMLElement('write')),
         )
       )
     );
  }

  if ( isset($prop_list['DAV::current-user-privilege-set']) ) {
    $reply->DAVElement( $prop, 'current-user-privilege-set', privileges($request->permissions) );
  }

  if ( isset($prop_list['DAV::supported-privilege-set']) ) {
    $reply->DAVElement( $prop, 'supported-privilege-set', privileges( $request->SupportedPrivileges(), 'supported-privilege') );
  }

  if ( isset($prop_list['DAV::supported-method-set']) ) {
    $reply->DAVElement( $prop, 'supported-method-set', $request->BuildSupportedMethods() );
  }
  if ( isset($prop_list['DAV::supported-report-set']) ) {
    $reply->DAVElement( $prop, 'supported-report-set', $request->BuildSupportedReports() );
  }


}


/**
* Build the <propstat><prop></prop><status></status></propstat> part of the response
*/
function build_propstat_response( $prop, $denied, $url ) {
  global $reply, $arbitrary, $prop_list;

  $response = array( $reply->href($url),
                     $reply->NewXMLElement( 'propstat', array( $prop,
                                                               $reply->NewXMLElement('status', 'HTTP/1.1 200 OK' )
                                                              )
                                           )
                    );

  $missed = array_merge($prop_list, $arbitrary);
  if ( isset($missed['DAV::allprop']) ) unset($missed['DAV::allprop']);
  foreach( $prop->content AS $k => $v ) {
    if ( isset($missed[$v->GetNSTag()]) ) unset($missed[$v->GetNSTag()]);
  }
  if ( isset($denied->content) && is_array($denied->content) ) {
    foreach( $denied->content AS $k => $v ) {
      if ( isset($missed[$v->GetNSTag()]) ) unset($missed[$v->GetNSTag()]);
    }
  }
  if ( count($missed) > 0 ) {
    $not_found = $reply->NewXMLElement('prop', false, false, 'DAV:');
    foreach( $missed AS $tag => $v ) {
      $reply->NSElement($not_found, $tag);
    }
    $response[] = $reply->NewXMLElement( 'propstat',
                                         array( $not_found,
                                                $reply->NewXMLElement('status', 'HTTP/1.1 404 Not Found', false, 'DAV:' )
                                              ), false, 'DAV:'
                                        );
  }

  if ( is_array($denied->content) && count($denied->content) > 0 ) {
    $response[] = $reply->NewXMLElement( 'propstat',
                                         array( $denied,
                                                $reply->NewXMLElement('status', 'HTTP/1.1 403 Forbidden', false, 'DAV:' )
                                              ), false, 'DAV:'
                                        );
  }

  $response = $reply->NewXMLElement( 'response', $response, false, 'DAV:' );

  return $response;
}

/**
 * Add the calendar-proxy-read/write pseudocollections
 * @param responses array of responses to which to add the collections
 */
function add_proxy_response( &$responses, $which, $parent_path ) {
	global $request, $c, $session;

  if ($parent_path != '/'.$request->principal->username.'/') {
    return; // Nothing to proxy for
  }

  $collection = (object) '';
  if ( $which == 'read' ) {
    $proxy_group = $request->principal->ReadProxyGroup();
  } else if ( $which == 'write' ) {
    $proxy_group = $request->principal->WriteProxyGroup();
  }

  $collection->dav_name = $parent_path.'calendar-proxy-'.$which.'/';
  $collection->is_calendar  = 'f';
  $collection->is_principal = 't';
  $collection->is_proxy     = 't';
  $collection->proxy_type   = $which;
  $collection->dav_displayname = $collection->dav_name;
  $collection->collection_id = 0;
  $collection->user_no = $session->user_no;
  $collection->username = $session->username;
  $collection->email = $session->email;
  $collection->created = date('Ymd\THis');
  $collection->dav_etag = md5($c->system_name . $collection->dav_name . implode($proxy_group) );
  $collection->proxy_for = $proxy_group;

  $responses[] = collection_to_xml( $collection );
}


/**
* Returns an XML sub-tree for a single collection record from the DB
*/
function collection_to_xml( $collection ) {
  global $arbitrary, $prop_list, $session, $c, $request, $reply;

  dbg_error_log('PROPFIND','Building XML Response for collection "%s" (%d)', $collection->dav_name, $collection->collection_id );

  $allprop = isset($prop_list['DAV::allprop']);

  $url = ConstructURL($collection->dav_name);

  $prop = $reply->NewXMLElement( 'prop', false, false, 'DAV:');
  $denied = $reply->NewXMLElement( 'prop', false, false, 'DAV:');

  $collection->type = ($collection->is_calendar == 't' ? 'calendar' :
                        (isset($collection->is_addressbook) && $collection->is_addressbook == 't' ? 'addressbook' : '') );
  if ( preg_match( '#^((/[^/]+/)\.(in|out)/)[^/]*$#', $collection->dav_name, $matches ) ) {
    $collection->type = 'schedule-'.$matches[3].'box';
  }
  dbg_error_log('PROPFIND','Collection "%s" is type "%s"', $collection->dav_name, $collection->type );

  /**
  * First process any static values we do support
  */
  if ( isset($prop_list['urn:ietf:params:xml:ns:caldav:supported-collation-set']) ) {
    $collations = array();
    $collations[] = $reply->NewXMLElement($reply->Caldav('supported-collation'), 'i;ascii-casemap');
    $collations[] = $reply->NewXMLElement($reply->Caldav('supported-collation'), 'i;octet');
    $prop->NewElement($reply->Caldav('supported-collation-set'), $collations );
  }
  if ( isset($prop_list['urn:ietf:params:xml:ns:caldav:supported-calendar-component-set']) && ($collection->type == 'calendar' || $collection->type == 'schedule-inbox' ||$collection->type == 'schedule-outbox') ) {
    $components = array();
    if ( $collection->type == 'calendar' )
      $set_of_components = array( 'VEVENT', 'VTODO', 'VJOURNAL', 'VTIMEZONE', 'VFREEBUSY' );
    else
      $set_of_components = array( 'VEVENT', 'VTODO', 'VFREEBUSY' );
    foreach( $set_of_components AS $v ) {
      $components[] = $reply->NewXMLElement( 'comp', '', array('name' => $v), 'urn:ietf:params:xml:ns:caldav');
    }
    $reply->CalDAVElement($prop, 'supported-calendar-component-set', $components );
  }
  if ( $allprop || isset($prop_list['DAV::getcontenttype']) ) {
    $reply->DAVElement( $prop, 'getcontenttype', 'httpd/unix-directory' );  // Strictly text/icalendar perhaps
  }

  /**
  * Process any dynamic values we do support
  */
  if ( $allprop || isset($prop_list['DAV::getcontentlength'])
                || isset($prop_list['DAV::resourcetype']) ) {
    $resourcetypes = array( $reply->NewXMLElement('collection', false, false, 'DAV:') );
    $contentlength = false;
    if ( $collection->type == 'schedule-inbox' || $collection->type == 'schedule-outbox' ) {
      $resourcetypes[] = $reply->NewXMLElement( $collection->type, false, false, 'urn:ietf:params:xml:ns:caldav');
    }
    else if ( $collection->is_calendar == 't' ) {
      $resourcetypes[] = $reply->NewXMLElement( 'calendar', false, false,'urn:ietf:params:xml:ns:caldav');
      $lqry = new PgQuery('SELECT sum(length(caldav_data)) FROM caldav_data WHERE user_no = ? AND dav_name ~ ?', $collection->user_no, $collection->dav_name.'[^/]+$' );
      if ( $lqry->Exec('PROPFIND',__LINE__,__FILE__) && $row = $lqry->Fetch() ) {
        $contentlength = $row->sum;
      }
    }

    if ( isset($collection->is_proxy) && $collection->is_proxy == 't' ) {
      // As per Caldav Proxy 5.1 par. 3
      $resourcetypes[] = $reply->NewXMLElement('calendar-proxy-'.$collection->proxy_type, false, false, 'http://calendarserver.org/ns/');
    }

    if ( $allprop || isset($prop_list['DAV::getcontentlength']) ) {
      $reply->DAVElement( $prop, 'getcontentlength', $contentlength );  // Not strictly correct as a GET on this URL would be longer
    }
    if ( $allprop || isset($prop_list['DAV::resourcetype']) ) {
      $reply->DAVElement( $prop, 'resourcetype', $resourcetypes );
    }
  }

  if ( isset($collection->is_proxy) && $collection->is_proxy == 't' ) {
    // Caldav proxy (not described in rfc, but CalendarServer has it)
    if ( isset($prop_list['http://calendarserver.org/ns/:calendar-proxy-'.$collection->proxy_type.'-for'] ) ) {
      if ( $collection->proxy_type == 'read' ) {
        $proxy_group = $request->principal->ReadProxyFor();
      } else if ( $collection->proxy_type == 'write' ) {
        $proxy_group = $request->principal->WriteProxyFor();
      }
      $reply->CalendarserverElement($prop, 'calendar-proxy-'.$collection->proxy_type.'-for', $reply->href( $proxy_group ) );
    }

    if ( isset($prop_list['DAV::group-member-set']) ) {
      if ( $collection->proxy_type == 'read' ) {
        $proxy_group = $request->principal->ReadProxyGroup();
      } else if ( $collection->proxy_type == 'write' ) {
        $proxy_group = $request->principal->WriteProxyGroup();
      }
      $reply->DAVElement($prop, 'group-member-set', $reply->href( $proxy_group ) );
    }

    if (isset($prop_list['DAV::group-membership'])) {
      $reply->DAVElement($prop, 'group-membership', $reply->href( $request->principal->GroupMembership() ));
    }

  }


  if ( $allprop || isset($prop_list['DAV::displayname']) ) {
    $displayname = ( $collection->dav_displayname == '' ? ucfirst(trim(str_replace('/',' ', $collection->dav_name))) : $collection->dav_displayname );
    $reply->DAVElement( $prop, 'displayname', $displayname );
  }
  if ( isset($prop_list['http://calendarserver.org/ns/:getctag']) ) {
    // Calendar Server extension which only applies to collections.  We return the etag, which does the needful.
    $reply->CalendarserverElement($prop, 'getctag', '"'.$collection->dav_etag.'"');
  }

  if ( isset($prop_list['urn:ietf:params:xml:ns:caldav:calendar-free-busy-set'] ) ) {
    if ( $session->user_no != $collection->user_no ) {
      $reply->CalDAVElement( $denied, 'calendar-free-busy-set');
    }
    else if ( $collection->type == 'schedule-inbox' ) {
      $fb_set = array();
      foreach( $request->principal->calendar_free_busy_set AS $k => $v ) {
        $fb_set[] = $reply->href( $v, false, 'DAV:' );
      }
      $reply->CalDAVElement( $prop, 'calendar-free-busy-set', $fb_set );
    }
  }

  /**
  * Then look at any properties related to the principal
  */
  add_principal_properties( $prop, $denied );

  /**
  * And any properties that are server/request related, or standard fields
  * from our query.
  */
  add_general_properties( $prop, $denied, $collection );

  /**
  * Arbitrary collection properties
  */
  add_arbitrary_properties($prop, $collection);

  return build_propstat_response( $prop, $denied, $url );
}


/**
* Return XML for a single data item from the DB
*/
function item_to_xml( $item ) {
  global $prop_list, $session, $c, $request, $reply;

  dbg_error_log('PROPFIND','Building XML Response for item "%s"', $item->dav_name );

  $allprop = isset($prop_list['DAV::allprop']);

  $url = ConstructURL($item->dav_name);

  $prop = $reply->NewXMLElement('prop', false, false, 'DAV:');
  $denied  = $reply->NewXMLElement('prop', false, false, 'DAV:');


  if ( $allprop || isset($prop_list['DAV::getcontentlength']) ) {
    $contentlength = strlen($item->caldav_data);
    $reply->DAVElement( $prop, 'getcontentlength', $contentlength );
  }
  if ( $allprop || isset($prop_list['DAV::getcontenttype']) ) {
    $reply->DAVElement( $prop, 'getcontenttype', 'text/calendar' );
  }
  if ( $allprop || isset($prop_list['DAV::displayname']) ) {
    $reply->DAVElement( $prop, 'displayname', $item->dav_displayname );
  }

  /**
  * Non-collections should return an empty resource type, it appears from RFC2518 8.1.2
  */
  if ( $allprop || isset($prop_list['DAV::resourcetype']) ) {
    $reply->DAVElement( $prop, 'resourcetype');
  }

  /**
  * Then look at any properties related to the principal
  */
  add_principal_properties( $prop, $denied );

  /**
  * And any properties that are server/request related.
  */
  add_general_properties( $prop, $denied, $item );

  add_arbitrary_properties($prop, $item );

  return build_propstat_response( $prop, $denied, $url );
}


/**
* Get XML response for items in the collection
* If '/' is requested, a list of visible users is given, otherwise
* a list of calendars for the user which are parented by this path.
*/
function get_collection_contents( $depth, $user_no, $collection ) {
  global $c, $session, $request, $reply, $prop_list, $arbitrary;

  dbg_error_log('PROPFIND','Getting collection contents: Depth %d, User: %d, Path: %s', $depth, $user_no, $collection->dav_name );

  $responses = array();
  if ( $collection->is_calendar != 't' ) {
    /**
    * Calendar collections may not contain calendar collections.
    */
    if ( $collection->dav_name == '/' ) {
      $sql = "SELECT usr.*, '/' || username || '/' AS dav_name, md5(username || updated::text) AS dav_etag, ";
      $sql .= "to_char(joined at time zone 'GMT',?) AS created, ";
      $sql .= "to_char(updated at time zone 'GMT',?) AS modified, ";
      $sql .= 'fullname AS dav_displayname, FALSE AS is_calendar, TRUE AS is_principal, ';
      $sql .= '0 AS collection_id ';
      $sql .= 'FROM usr ';
      $sql .= "WHERE get_permissions($session->user_no,user_no) ~ '[RAW]' ";
      $sql .= 'ORDER BY user_no';
    }
    else {
      $sql = 'SELECT user_no, dav_name, dav_etag, ';
      $sql .= "to_char(created at time zone 'GMT',?) AS created, ";
      $sql .= "to_char(modified at time zone 'GMT',?) AS modified, ";
      $sql .= 'dav_displayname, is_calendar, FALSE AS is_principal, ';
      $sql .= 'collection_id ';
      $sql .= 'FROM collection ';
      $sql .= 'WHERE parent_container='.qpg($collection->dav_name);
      $sql .= "AND NOT dav_name ~ '/\.(in|out)/$'";
      $sql .= ' ORDER BY collection_id';
    }
    $qry = new PgQuery($sql, PgQuery::Plain(iCalendar::HttpDateFormat()), PgQuery::Plain(iCalendar::HttpDateFormat()));

    if( $qry->Exec('PROPFIND',__LINE__,__FILE__) && $qry->rows > 0 ) {
      while( $subcollection = $qry->Fetch() ) {
        if ( $subcollection->is_principal == 't' ) {
          $principal = new CalDAVPrincipal($subcollection);
          $responses[] = $principal->RenderAsXML(array_merge($prop_list,$arbitrary), $reply);
        }
        else {
          $responses[] = collection_to_xml( $subcollection );
        }
        if ( $depth > 0 ) {
          $responses = array_merge( $responses, get_collection_contents( $depth - 1,  $user_no, $subcollection ) );
        }
      }
    }
    if ( $collection->is_principal == 't' ) {
      // Caldav Proxy: 5.1 par. 2: Add child resources calendar-proxy-(read|write)
      dbg_error_log('PROPFIND','Adding calendar-proxy-read and write. Path: %s', $collection->dav_name);
      add_proxy_response($responses, 'read', $collection->dav_name);
      add_proxy_response($responses, 'write', $collection->dav_name);
    }
  }

  /**
  * freebusy permission is not allowed to see the items in a collection.  Must have at least read permission.
  */
  if ( $request->AllowedTo('read') ) {
    dbg_error_log('PROPFIND','Getting collection items: Depth %d, User: %d, Path: %s', $depth, $user_no, $collection->dav_name );
    $privacy_clause = ' ';
    if ( ! $request->AllowedTo('all') ) {
      $privacy_clause = " AND (calendar_item.class != 'PRIVATE' OR calendar_item.class IS NULL) ";
    }

    $sql = 'SELECT caldav_data.dav_name, caldav_data, caldav_data.dav_etag, ';
    $sql .= "to_char(coalesce(calendar_item.created, caldav_data.created) at time zone 'GMT',?) AS created, ";
    $sql .= "to_char(last_modified at time zone 'GMT',?) AS modified, ";
    $sql .= 'summary AS dav_displayname ';
    $sql .= 'FROM caldav_data JOIN calendar_item USING( dav_id, user_no, dav_name) ';
    $sql .= 'WHERE dav_name ~ '.qpg('^'.$collection->dav_name.'[^/]+$'). $privacy_clause;
    if ( isset($c->strict_result_ordering) && $c->strict_result_ordering ) $sql .= " ORDER BY dav_id";
    $qry = new PgQuery($sql, PgQuery::Plain(iCalendar::HttpDateFormat()), PgQuery::Plain(iCalendar::HttpDateFormat()));
    if( $qry->Exec('PROPFIND',__LINE__,__FILE__) && $qry->rows > 0 ) {
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
  global $session, $c, $request, $prop_list, $arbitrary, $reply;
  $responses = array();

  dbg_error_log('PROPFIND','Getting collection: Depth %d, User: %d, Path: %s', $depth, $user_no, $collection_path );

  if (preg_match('#/[^/]+/calendar-proxy-(read|write)/?#',$collection_path, $match) ) {
  	// this should be a direct query to /<somewhere>/calendar-proxy-<something>
  	dbg_error_log('PROPFIND','Simulating calendar-proxy-read or write. Path: %s', $collection_path);
       add_proxy_response($responses, $match[1], $collection_path);
  }

  if ( $collection_path == null || $collection_path == '/' || $collection_path == '' ) {
    $collection->dav_name = $collection_path;
    $collection->dav_etag = md5($c->system_name . $collection_path);
    $collection->is_calendar = 'f';
    $collection->is_principal = 'f';
    $collection->user_no = 0;
    $collection->collection_id = 0;
    $collection->dav_displayname = $c->system_name;
    $collection->created = date('Ymd\THis');
    $responses[] = collection_to_xml( $collection );
  }
  else {
    $user_no = intval($user_no);
    if ( preg_match( '#^/[^/]+/$#', $collection_path) ) {
      $sql = "SELECT usr.*, '/' || username || '/' AS dav_name, md5( username || updated::text ) AS dav_etag, ";
      $sql .= "to_char(joined at time zone 'GMT',?) AS created, ";
      $sql .= "to_char(updated at time zone 'GMT',?) AS modified, ";
      $sql .= 'fullname AS dav_displayname, FALSE AS is_calendar, TRUE AS is_principal, 0 AS collection_id ';
      $sql .= "FROM usr WHERE user_no = $user_no ";
      $sql .= "AND get_permissions($session->user_no,user_no) ~ '[RAW]' ";
      $sql .= 'ORDER BY user_no';
    }
    else {
      $sql = 'SELECT user_no, dav_name, dav_etag, ';
      $sql .= "to_char(created at time zone 'GMT',?) AS created, ";
      $sql .= "to_char(modified at time zone 'GMT',?) AS modified, ";
      $sql .= 'dav_displayname, is_calendar, FALSE AS is_principal, collection_id ';
      $sql .= 'FROM collection WHERE dav_name = '.qpg($collection_path);
      $sql .= ' ORDER BY collection_id';
    }
    $qry = new PgQuery($sql, PgQuery::Plain(iCalendar::HttpDateFormat()), PgQuery::Plain(iCalendar::HttpDateFormat()) );
    if( $qry->Exec('PROPFIND',__LINE__,__FILE__) && $qry->rows > 0 && $collection = $qry->Fetch() ) {
      if ( $collection->is_principal == 't' ) {
        $principal = new CalDAVPrincipal($collection);
        $responses[] = $principal->RenderAsXML(array_merge($prop_list,$arbitrary), $reply);
      }
      else {
        $responses[] = collection_to_xml( $collection );
      }

    }
    elseif ( $c->collections_always_exist && preg_match( "#^/$session->username/#", $collection_path) ) {
      dbg_error_log('PROPFIND',"Using $c->collections_always_exist setting is deprecated" );
      $collection->dav_name = $collection_path;
      $collection->dav_etag = md5($collection_path);
      $collection->is_calendar = 't';  // Everything is a calendar, if it always exists!
      $collection->is_principal = 'f';
      $collection->user_no = $user_no;
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

  dbg_error_log('PROPFIND','Getting item: Path: %s', $item_path );

  $privacy_clause = ' ';
  if ( ! $request->AllowedTo('all') ) {
    $privacy_clause = " AND (calendar_item.class != 'PRIVATE' OR calendar_item.class IS NULL) ";
  }

  $sql = 'SELECT caldav_data.dav_name, caldav_data, caldav_data.dav_etag, ';
  $sql .= "to_char(coalesce(calendar_item.created, caldav_data.created) at time zone 'GMT',?) AS created, ";
  $sql .= "to_char(last_modified at time zone 'GMT',?) AS modified, ";
  $sql .= 'summary AS dav_displayname ';
  $sql .= 'FROM caldav_data JOIN calendar_item USING( user_no, dav_name)  WHERE dav_name = ? '.$privacy_clause;
  $qry = new PgQuery($sql, PgQuery::Plain(iCalendar::HttpDateFormat()), PgQuery::Plain(iCalendar::HttpDateFormat()), $item_path);
  if( $qry->Exec('PROPFIND',__LINE__,__FILE__) && $qry->rows > 0 ) {
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
$url = ConstructURL( $request->path );
$url = preg_replace( '#/$#', '', $url);
$responses = array();
if ( $request->IsPrincipal() ) {
  if ( $request->principal->Exists() !== true ) {
    $request->DoResponse( 404, translate('That resource is not present on this server.') );
  }
  $responses[] = $request->principal->RenderAsXML(array_merge($prop_list,$arbitrary), $reply);
  if ( $request->depth > 0 ) {
    $collection = (object) array( 'dav_name' => '/'.$request->username.'/', 'is_calendar' => 'f', 'is_principal' => 't' );
    $responses = array_merge($responses, get_collection_contents( $request->depth - 1,  $request->user_no, $collection ) );
  }
}
elseif ( $request->IsProxyRequest() ) {
  add_proxy_response($responses, $request->proxy_type, '/' . $request->principal->username . '/' );
  /** Nothing inside these, as yet. */
}
elseif ( $request->IsCollection() ) {
  $responses = get_collection( $request->depth, $request->user_no, $request->path );
  if ( count($responses) < 1 ) {
    $request->DoResponse( 404, translate('That resource is not present on this server.') );
  }
}
elseif ( $request->AllowedTo('read') ) {
  $responses = get_item( $request->path );
  if ( count($responses) < 1 ) {
    $request->DoResponse( 404, translate('That resource is not present on this server.') );
  }
}
else {
  $request->DoResponse( 403, translate('You do not have appropriate rights to view that resource.') );
}

$xmldoc = $reply->Render('multistatus', $responses);
$etag = md5($xmldoc);
header('ETag: "'.$etag.'"');
$request->DoResponse( 207, $xmldoc, 'text/xml; charset="utf-8"' );

