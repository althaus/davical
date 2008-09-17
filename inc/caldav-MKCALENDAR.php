<?php
/**
* CalDAV Server - handle MKCALENDAR method
*
* @package   davical
* @subpackage   caldav
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
dbg_error_log("MKCALENDAR", "method handler");

if ( ! $request->AllowedTo('mkcalendar') ) {
  $request->DoResponse( 403, translate("You may not create a calendar there.") );
}

$displayname = $request->path;

// Enforce trailling '/' on collection name
if ( ! preg_match( '#/$#', $request->path ) ) {
  dbg_error_log( "MKCALENDAR", "Add trailling '/' to '%s'", $request->path);
  $request->path .= '/';
}

$parent_container = '/';
if ( preg_match( '#^(.*/)([^/]+)(/)?$#', $request->path, $matches ) ) {
  $parent_container = $matches[1];
  $displayname = $matches[2];
}

if ( isset($request->xml_tags) ) {
  /**
  * The MKCALENDAR request may contain XML to set some DAV properties
  */
  $position = 0;
  $xmltree = BuildXMLTree( $request->xml_tags, $position);
  // echo $xmltree->Render();
  if ( $xmltree->GetTag() != "URN:IETF:PARAMS:XML:NS:CALDAV:MKCALENDAR" ) {
    $request->DoResponse( 403, "XML is not a URN:IETF:PARAMS:XML:NS:CALDAV:MKCALENDAR document" );
  }
  $setprops = $xmltree->GetPath("/URN:IETF:PARAMS:XML:NS:CALDAV:MKCALENDAR/DAV::SET/DAV::PROP/*");

  $propertysql = "";
  foreach( $setprops AS $k => $setting ) {
    $tag = $setting->GetTag();
    $content = $setting->RenderContent();

    switch( $tag ) {

      case 'DAV::DISPLAYNAME':
        $displayname = $content;
        /**
        * TODO: This is definitely a bug in SOHO Organizer and we probably should respond
        * with an error, rather than silently doing what they *seem* to want us to do.
        */
        if ( preg_match( '/^SOHO.Organizer.6\./', $_SERVER['HTTP_USER_AGENT'] ) ) {
          dbg_error_log( "MKCALENDAR", "Displayname is '/' to '%s'", $request->path);
          $parent_container = $request->path;
          $request->path .= $content . '/';
        }
        $success[$tag] = 1;
        break;

      case 'URN:IETF:PARAMS:XML:NS:CALDAV:SUPPORTED-CALENDAR-COMPONENT-SET':  /** Ignored, since we will support all component types */
      case 'URN:IETF:PARAMS:XML:NS:CALDAV:SUPPORTED-CALENDAR-DATA':  /** Ignored, since we will support iCalendar 2.0 */
      case 'URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-DATA':  /** Ignored, since we will support iCalendar 2.0 */
      case 'URN:IETF:PARAMS:XML:NS:CALDAV:MAX-RESOURCE-SIZE':  /** Ignored, since we will support arbitrary size */
      case 'URN:IETF:PARAMS:XML:NS:CALDAV:MIN-DATE-TIME':  /** Ignored, since we will support arbitrary time */
      case 'URN:IETF:PARAMS:XML:NS:CALDAV:MAX-DATE-TIME':  /** Ignored, since we will support arbitrary time */
      case 'URN:IETF:PARAMS:XML:NS:CALDAV:MAX-INSTANCES':  /** Ignored, since we will support arbitrary instances */
      case 'DAV::RESOURCETYPE':    /** Any value for resourcetype is ignored */
        $success[$tag] = 1;
        break;

      /**
      * The following properties are read-only, so they will cause the request to fail
      */
      case 'DAV::GETETAG':
      case 'DAV::GETCONTENTLENGTH':
      case 'DAV::GETCONTENTTYPE':
      case 'DAV::GETLASTMODIFIED':
      case 'DAV::CREATIONDATE':
      case 'DAV::LOCKDISCOVERY':
      case 'DAV::SUPPORTEDLOCK':
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
        $propertysql .= awl_replace_sql_args( "SELECT set_dav_property( ?, ?, ?, ? );", $request->path, $request->user_no, $tag, $content );
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

    array_unshift( $failure, new XMLElement('href', $c->protocol_server_port_script . $request->path ) );
    $failure[] = new XMLElement('responsedescription', translate("Some properties were not able to be set.") );

    $multistatus = new XMLElement( "multistatus", new XMLElement( 'response', $failure ), array('xmlns'=>'DAV:') );
    $request->DoResponse( 207, $multistatus->Render(0,'<?xml version="1.0" encoding="utf-8" ?>'), 'text/xml; charset="utf-8"' );

  }
}

$sql = "SELECT * FROM collection WHERE user_no = ? AND dav_name = ?;";
$qry = new PgQuery( $sql, $request->user_no, $request->path );
if ( ! $qry->Exec("MKCALENDAR") ) {
  $request->DoResponse( 500, translate("Error querying database.") );
}
if ( $qry->rows != 0 ) {
  $request->DoResponse( 405, translate("A collection already exists at that location.") );
}

$sql = "BEGIN; INSERT INTO collection ( user_no, parent_container, dav_name, dav_etag, dav_displayname, is_calendar, created, modified ) VALUES( ?, ?, ?, ?, ?, ?, current_timestamp, current_timestamp ); $propertysql; COMMIT;";
$qry = new PgQuery( $sql, $request->user_no, $parent_container, $request->path, md5($request->user_no. $request->path), $displayname, ($request->method == 'MKCALENDAR') );

if ( $qry->Exec("MKCALENDAR",__LINE__,__FILE__) ) {
  dbg_error_log( "MKCALENDAR", "New calendar '%s' created named '%s' for user '%d' in parent '%s'", $request->path, $displayname, $session->user_no, $parent_container);
  header("Cache-Control: no-cache"); /** draft-caldav-15 declares this is necessary at 5.3.1 */
  $request->DoResponse( 201, "" );
}
else {
  $request->DoResponse( 500, translate("Error writing calendar details to database.") );
}

/**
* FIXME: We could also respond to the request...
*
*   <?xml version="1.0" encoding="utf-8" ?>
*   <C:mkcalendar xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
*     <D:set>
*       <D:prop>
*         <D:displayname>Lisa's Events</D:displayname>
*         <C:calendar-description xml:lang="en">Calendar restricted to events.</C:calendar-description>
*         <C:supported-calendar-component-set>
*           <C:comp name="VEVENT"/>
*         </C:supported-calendar-component-set>
*         <C:calendar-timezone><![CDATA[BEGIN:VCALENDAR
*   PRODID:-//Example Corp.//CalDAV Client//EN
*   VERSION:2.0
*   BEGIN:VTIMEZONE
*   TZID:US-Eastern
*   LAST-MODIFIED:19870101T000000Z
*   BEGIN:STANDARD
*   DTSTART:19671029T020000
*   RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10
*   TZOFFSETFROM:-0400
*   TZOFFSETTO:-0500
*   TZNAME:Eastern Standard Time (US & Canada)
*   END:STANDARD
*   BEGIN:DAYLIGHT
*   DTSTART:19870405T020000
*   RRULE:FREQ=YEARLY;BYDAY=1SU;BYMONTH=4
*   TZOFFSETFROM:-0500
*   TZOFFSETTO:-0400
*   TZNAME:Eastern Daylight Time (US & Canada)
*   END:DAYLIGHT
*   END:VTIMEZONE
*   END:VCALENDAR
*   ]]></C:calendar-timezone>
*       </D:prop>
*     </D:set>
*   </C:mkcalendar>
*
*/

?>
