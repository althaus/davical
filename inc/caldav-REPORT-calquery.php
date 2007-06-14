<?php

/**
 * Build the array of properties to include in the report output
 */
$qry_content = $xmltree->GetContent('URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-QUERY');
$proptype = $qry_content[0]->GetTag();
$properties = array();
switch( $proptype ) {
  case 'DAV::PROP':
    $qry_props = $xmltree->GetPath('/URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-QUERY/DAV::PROP/*');
    foreach( $qry_props AS $k => $v ) {
      $propertyname = preg_replace( '/^.*:/', '', $v->GetTag() );
      $properties[$propertyname] = 1;
    }
    break;

  case 'DAV::ALLPROP':
    $properties['ALLPROP'] = 1;
    break;

  default:
    $propertyname = preg_replace( '/^.*:/', '', $proptype );
    $properties[$propertyname] = 1;
}

/**
 * There can only be *one* FILTER element, and it must contain *one* COMP-FILTER
 * element.  In every case I can see this contained COMP-FILTER element will be a
 * VCALENDAR, but perhaps there are others.  In our case we strip it if that is
 * the case and leave it alone otherwise.
 */
$qry_filters = $xmltree->GetPath('/URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-QUERY/URN:IETF:PARAMS:XML:NS:CALDAV:FILTER/*');
$qry_filters = $qry_filters[0];  // There can only be one FILTER element
if ( $qry_filters->GetTag() == "URN:IETF:PARAMS:XML:NS:CALDAV:COMP-FILTER" && $qry_filters->GetAttribute("NAME") == "VCALENDAR" )
  $qry_filters = $qry_filters->GetContent();  // Everything is inside a VCALENDAR AFAICS

/**
* While we can construct our SQL to apply some filters in the query, other filters
* need to be checked against the retrieved record.  This is for handling those ones.
*
* @param array $filter An array of XMLElement which is the filter definition
* @param string $item The database row retrieved for this calendar item
*
* @return boolean True if the check succeeded, false otherwise.
*/
function apply_filter( $filter, $item ) {
  global $session, $c, $request;

  if ( count($filter) == 0 ) return true;

  dbg_error_log("REPORT","Applying filter for item '%s'", $item->dav_name );
  $ical = new iCalendar( array( "icalendar" => $item->caldav_data) );
  return $ical->TestFilter($filter);
}


/**
 * Process a filter fragment returning an SQL fragment
 */
$need_post_filter = false;
function SqlFilterFragment( $filter, $components, $property = null, $parameter = null ) {
  $sql = "";
  foreach( $filter AS $k => $v ) {
    $tag = $v->GetTag();
    dbg_error_log("REPORT", "Processing $tag into SQL - %d, '%s', %d\n", count($components), $property, isset($parameter) );

    $not_defined = "";
    switch( $tag ) {
      case 'URN:IETF:PARAMS:XML:NS:CALDAV:IS-NOT-DEFINED':
        $not_defined = "NOT "; // then fall through to IS-DEFINED case
      case 'URN:IETF:PARAMS:XML:NS:CALDAV:IS-DEFINED':
        if ( isset( $parameter ) ) {
          $need_post_filter = true;
          return false;  // Not handled in SQL
        }
        if ( isset( $property ) ) {
          switch( $property ) {
            case "xxx":
            default:
              $need_post_filter = true;
              return false;  // Not handled in SQL
          }
        }
        elseif ( count($components) > 0 ) {
          $filters = "";
          foreach( $components AS $ck => $cv ) {
            switch ( $cv ) {
              case 'VCALENDAR':
                continue;
              default:
                $filters .= ($filters == "" ? "" : ", ") . qpg($cv);
            }
          }
          if ( $filters != "" ) {
            $sql .= "AND caldav_data.caldav_type ".$not_defined."IN ( $filters ) ";
          }
        }
        break;

      case 'URN:IETF:PARAMS:XML:NS:CALDAV:TIME-RANGE':
        /**
        * TODO: We should probably allow time range queries against other properties, since eventually some client may want to do this.
        */
        $start_column = 'dtend';     // The column we compare against the START attribute
        $finish_column = 'dtstart';  // The column we compare against the END attribute
        $start = $v->GetAttribute("START");
        $finish = $v->GetAttribute("END");
        if ( isset($start) ) {
          $sql .= sprintf( "AND (%s >= %s::timestamp with time zone ", $start_column, qpg($start));
          $sql .= sprintf( "OR calculate_later_timestamp(%s::timestamp with time zone,%s,rrule) >= %s::timestamp with time zone) ", qpg($start), $start_column, qpg($start) );
        }
        if ( isset( $report[$i]['end'] ) ) {
          $sql .= sprintf( "AND %s <= %s::timestamp with time zone ", $finish_column, qpg($finish) );
        }
        break;

      case 'URN:IETF:PARAMS:XML:NS:CALDAV:TEXT-MATCH':
        $search = $v->GetContent();
        $negate = $v->GetAttribute("NEGATE-CONDITION");
        if ( isset($negate) && strtolower($negate) == "yes" ) $match = !$match;
        $sql .= sprintf( "AND %s LIKE %s ", $property, qpg("%".$search."%") );
        break;

      case 'URN:IETF:PARAMS:XML:NS:CALDAV:COMP-FILTER':
        $components[] = $v->GetAttribute("NAME");
        $subfilter = $v->GetContent();
        $success = SqlFilterFragment( $subfilter, $components, $property, $parameter );
        if ( $success === false ) continue; else $sql .= $success;
        break;

      case 'URN:IETF:PARAMS:XML:NS:CALDAV:PROP-FILTER':
        $propertyname = $v->GetAttribute("NAME");
        switch( $propertyname ) {
          case 'UID':
          case 'SUMMARY':
          case 'LOCATION':
          case 'DESCRIPTION':
          case 'CLASS':
          case 'TRANSP':
          case 'URL':
          case 'TZID':  // May not work as expected.  Perhaps this should be checked later.
          case 'STATUS':
            $property = strtolower($propertyname);
            break;
          default:
            $need_post_filter = true;
            return false; // Can't handle PARAM-FILTER conditions in the SQL
        }
        $subfilter = $v->GetContent();
        $success = SqlFilterFragment( $subfilter, $components, $property, $parameter );
        if ( $success === false ) continue; else $sql .= $success;
        break;

      case 'URN:IETF:PARAMS:XML:NS:CALDAV:PARAM-FILTER':
        $need_post_filter = true;
        return false; // Can't handle PARAM-FILTER conditions in the SQL
        $parameter = $v->GetAttribute("NAME");
        $subfilter = $v->GetContent();
        $success = SqlFilterFragment( $subfilter, $components, $property, $parameter );
        if ( $success === false ) continue; else $sql .= $success;
        break;
    }
  }
  return $sql;
}

/**
 * Build an SQL 'WHERE' clause which implements (parts of) the filter. The
 * elements of the filter which are implemented in the SQL will be removed.
 *
 * @param arrayref &$filter A reference to an array of XMLElement defining the filter
 *
 * @return string A string suitable for use as an SQL 'WHERE' clause selecting the desired records.
 */
function BuildSqlFilter( $filter ) {
  $components = array();
  $sql = SqlFilterFragment( $filter, $components );
  if ( $sql === false ) return "";
  return $sql;
}


/**
* Something that we can handle, at least roughly correctly.
*/

$responses = array();

$where = " WHERE caldav_data.dav_name ~ ".qpg("^".$request->path)." ";
$where .= BuildSqlFilter( $qry_filters);

$where .= "AND (calendar_item.class != 'PRIVATE' OR calendar_item.class IS NULL OR get_permissions($session->user_no,caldav_data.user_no) ~ 'A') "; // Must have 'all' permissions to see confidential items
if ( isset($c->hide_TODO) && $c->hide_TODO ) {
  $where .= "AND (caldav_data.caldav_type NOT IN ('VTODO') OR get_permissions($session->user_no,caldav_data.user_no) ~ 'A') ";
}
$qry = new PgQuery( "SELECT * , get_permissions($session->user_no,caldav_data.user_no) as permissions FROM caldav_data INNER JOIN calendar_item USING(user_no, dav_name)". $where );
if ( $qry->Exec("REPORT",__LINE__,__FILE__) && $qry->rows > 0 ) {
  while( $calendar_object = $qry->Fetch() ) {
    if ( !$need_post_filter || apply_filter( $qry_filters, $calendar_object ) ) {
      $responses[] = calendar_to_xml( $properties, $calendar_object );
    }
  }
}
$multistatus = new XMLElement( "multistatus", $responses, array('xmlns'=>'DAV:') );

$request->XMLResponse( 207, $multistatus );

?>