<?php
/**
* CalDAV Server - handle PROPPATCH method
*
* @package   davical
* @subpackage   caldav
* @author    Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Morphoss Ltd - http://www.morphoss.com/
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("PROPPATCH", "method handler");

require_once('iCalendar.php');

if ( ! $request->AllowedTo('write-properties') ) {
  $request->DoResponse( 403 );
}

$position = 0;
$xmltree = BuildXMLTree( $request->xml_tags, $position);

// echo $xmltree->Render();

if ( $xmltree->GetTag() != "DAV::propertyupdate" ) {
  $request->DoResponse( 403 );
}

/**
* Find the properties being set, and the properties being removed
*/
$setprops = $xmltree->GetPath("/DAV::propertyupdate/DAV::set/DAV::prop/*");
$rmprops  = $xmltree->GetPath("/DAV::propertyupdate/DAV::remove/DAV::prop/*");

/**
* We build full status responses for failures.  For success we just record
* it, since the multistatus response only applies to failure.  While it is
* not explicitly stated in RFC2518, from reading between the lines (8.2.1)
* a success will return 200 OK [with an empty response].
*/
$failure   = array();
$success   = array();

/**
* Not much for it but to process the incoming settings in a big loop, doing
* the special-case stuff as needed and falling through to a default which
* stuffs the property somewhere we will be able to retrieve it from later.
*/
$sql = "BEGIN;";
foreach( $setprops AS $k => $setting ) {
  $tag = $setting->GetTag();
  $content = $setting->RenderContent();

  switch( $tag ) {

    case 'DAV::displayname':
      /**
      * Can't set displayname on resources - only collections or principals
      */
      if ( $request->IsCollection() || $request->IsPrincipal() ) {
        if ( $request->IsCollection() ) {
          $sql .= sprintf( "UPDATE collection SET dav_displayname = %s, modified = current_timestamp WHERE dav_name = %s;",
                                            qpg($content), qpg($request->path) );
        }
        else {
          $sql .= sprintf( "UPDATE usr SET fullname = %s, updated = current_timestamp WHERE user_no = %s;",
                                            qpg($content), $request->user_no );
        }
        $success[$tag] = 1;
      }
      else {
        $failure['set-'.$tag] = new XMLElement( 'propstat', array(
            new XMLElement( 'prop', new XMLElement($tag)),
            new XMLElement( 'status', 'HTTP/1.1 409 Conflict' ),
            new XMLElement( 'responsedescription', translate("The displayname may only be set on collections or principals.") )
        ));
      }
      break;

    case 'DAV::resourcetype':
      /**
      * We don't allow a collection to change to/from a resource.  Only collections may be CalDAV calendars.
      */
      $setcollection = count($setting->GetPath('DAV::resourcetype/DAV::collection'));
      $setcalendar   = count($setting->GetPath('DAV::resourcetype/urn:ietf:params:xml:ns:caldav:calendar'));
      if ( $request->IsCollection() && ($setcollection || $setcalendar) ) {
        if ( $setcalendar ) {
          $sql .= sprintf( "UPDATE collection SET is_calendar = TRUE WHERE dav_name = %s;", qpg($request->path) );
        }
        $success[$tag] = 1;
      }
      else {
        $failure['set-'.$tag] = new XMLElement( 'propstat', array(
            new XMLElement( 'prop', new XMLElement($tag)),
            new XMLElement( 'status', 'HTTP/1.1 409 Conflict' ),
            new XMLElement( 'responsedescription', translate("Resources may not be changed to / from collections.") )
        ));
      }
      break;

    case 'urn:ietf:params:xml:ns:caldav:calendar-timezone':
      $tzcomponent = $setting->GetPath('urn:ietf:params:xml:ns:caldav:calendar-timezone');
      $tzstring = $tzcomponent[0]->GetContent();
      $calendar = new iCalendar( array( 'icalendar' => $tzstring) );
      $timezones = $calendar->component->GetComponents('VTIMEZONE');
      if ( $timezones === false || count($timezones) == 0 ) break;
      $tz = $timezones[0];  // Backward compatibility
      $tzid = $tz->GetPValue('TZID');
      $sql .= sprintf( 'UPDATE collection SET timezone = %s WHERE dav_name = %s;', qpg($tzid), qpg($request->path) );
      break;

    /**
    * The following properties are read-only, so they will cause the request to fail
    */
    case 'http://calendarserver.org/ns/:getctag':
    case 'DAV::owner':
    case 'DAV::principal-collection-set':
    case 'urn:ietf:params:xml:ns:caldav:calendar-user-address-set':
    case 'urn:ietf:params:xml:ns:caldav:schedule-inbox-URL':
    case 'urn:ietf:params:xml:ns:caldav:schedule-outbox-URL':
    case 'DAV::getetag':
    case 'DAV::getcontentlength':
    case 'DAV::getcontenttype':
    case 'DAV::getlastmodified':
    case 'DAV::creationdate':
    case 'DAV::lockdiscovery':
    case 'DAV::supportedlock':
      $failure['set-'.$tag] = new XMLElement( 'propstat', array(
          new XMLElement( 'prop', new XMLElement($tag)),
          new XMLElement( 'status', 'HTTP/1.1 409 Conflict' ),
          new XMLElement('responsedescription', translate("Property is read-only") )
      ));
      break;

    /**
    * If we don't have any special processing for the property, we just store it verbatim (which will be an XML fragment).
    */
    default:
      $sql .= awl_replace_sql_args( "SELECT set_dav_property( ?, ?, ?, ? );", $request->path, $request->user_no, $tag, $content );
      $success[$tag] = 1;
      break;
  }
}



foreach( $rmprops AS $k => $setting ) {
  $tag = $setting->GetTag();
  $content = $setting->RenderContent();

  switch( $tag ) {

    case 'DAV::resourcetype':
      /**
      * We don't allow a collection to change to/from a resource.  Only collections may be CalDAV calendars.
      */
      $rmcollection = (count($setting->GetPath('DAV::resourcetype/DAV::collection')) > 0);
      $rmcalendar   = (count($setting->GetPath('DAV::resourcetype/urn:ietf:params:xml:ns:caldav:calendar')) > 0);
      if ( $request->IsCollection() && !$rmcollection ) {
        dbg_error_log( 'PROPPATCH', ' RMProperty %s : IsCollection=%d, rmcoll=%d, rmcal=%d', $tag, $request->IsCollection(), $rmcollection, $rmcalendar );
        if ( $rmcalendar ) {
          $sql .= sprintf( "UPDATE collection SET is_calendar = FALSE WHERE dav_name = %s;", qpg($request->path) );
        }
        $success[$tag] = 1;
      }
      else {
        $failure['rm-'.$tag] = new XMLElement( 'propstat', array(
            new XMLElement( 'prop', new XMLElement($tag)),
            new XMLElement( 'status', 'HTTP/1.1 409 Conflict' ),
            new XMLElement( 'responsedescription', translate("Resources may not be changed to / from collections.") )
        ));
        dbg_error_log( 'PROPPATCH', ' RMProperty %s Resources may not be changed to / from Collections.', $tag);
      }
      break;

    case 'urn:ietf:params:xml:ns:caldav:calendar-timezone':
      $sql .= sprintf( "UPDATE collection SET timezone = NULL WHERE dav_name = %s;", qpg($request->path) );
      break;

    /**
    * The following properties are read-only, so they will cause the request to fail
    */
    case 'http://calendarserver.org/ns/:getctag':
    case 'DAV::owner':
    case 'DAV::principal-collection-set':
    case 'urn:ietf:params:xml:ns:caldav:CALENDAR-USER-ADDRESS-SET':
    case 'urn:ietf:params:xml:ns:caldav:schedule-inbox-URL':
    case 'urn:ietf:params:xml:ns:caldav:schedule-outbox-URL':
    case 'DAV::getetag':
    case 'DAV::getcontentlength':
    case 'DAV::getcontenttype':
    case 'DAV::getlastmodified':
    case 'DAV::creationdate':
    case 'DAV::displayname':
    case 'DAV::lockdiscovery':
    case 'DAV::supportedlock':
      $failure['rm-'.$tag] = new XMLElement( 'propstat', array(
          new XMLElement( 'prop', new XMLElement($tag)),
          new XMLElement( 'status', 'HTTP/1.1 409 Conflict' ),
          new XMLElement('responsedescription', translate("Property is read-only") )
      ));
      dbg_error_log( 'PROPPATCH', ' RMProperty %s is read only and cannot be removed', $tag);
      break;

    /**
    * If we don't have any special processing then we must have to just delete it.  Nonexistence is not failure.
    */
    default:
      $sql .= awl_replace_sql_args( "DELETE FROM property WHERE dav_name=? AND property_name=?;", $request->path, $tag );
      $success[$tag] = 1;
      break;
  }
}


/**
* If we have encountered any instances of failure, the whole damn thing fails.
*/
if ( count($failure) > 0 ) {
  foreach( $success AS $tag => $v ) {
    // Unfortunately although these succeeded, we failed overall, so they didn't happen...
    $failure[] = new XMLElement( 'propstat', array(
        new XMLElement( 'prop', new XMLElement($tag)),
        new XMLElement( 'status', 'HTTP/1.1 424 Failed Dependency' ),
    ));
  }

  $url = ConstructURL($request->path);
  array_unshift( $failure, new XMLElement('href', $url ) );
  $failure[] = new XMLElement('responsedescription', translate("Some properties were not able to be changed.") );

  $multistatus = new XMLElement( "multistatus", new XMLElement( 'response', $failure ), array('xmlns'=>'DAV:') );
  $request->DoResponse( 207, $multistatus->Render(0,'<?xml version="1.0" encoding="utf-8" ?>'), 'text/xml; charset="utf-8"' );

}

/**
* Otherwise we will try and do the SQL. This is inside a transaction, so PostgreSQL guarantees the atomicity
*/
$sql .= "COMMIT;";
$qry = new PgQuery( $sql );
if ( $qry->Exec() ) {
  $url = ConstructURL($request->path);
  $href = new XMLElement('href', $url );
  $desc = new XMLElement('responsedescription', translate("All requested changes were made.") );

  $multistatus = new XMLElement( "multistatus", new XMLElement( 'response', array( $href, $desc ) ), array('xmlns'=>'DAV:') );
  $request->DoResponse( 200, $multistatus->Render(0,'<?xml version="1.0" encoding="utf-8" ?>'), 'text/xml; charset="utf-8"' );
}

/**
* Or it was all crap.
*/
$request->DoResponse( 500 );

exit(0);

