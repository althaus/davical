<?php

/**
 * Build the array of properties to include in the report output
 */
$qry_content = $xmltree->GetContent('urn:ietf:params:xml:ns:caldav:calendar-query');
$proptype = $qry_content[0]->GetTag();
$properties = array();
switch( $proptype ) {
  case 'DAV::prop':
    $qry_props = $xmltree->GetPath('/urn:ietf:params:xml:ns:caldav:calendar-query/DAV::prop/*');
    foreach( $qry_props AS $k => $v ) {
      $propertyname = preg_replace( '/^.*:/', '', $v->GetTag() );
      $properties[$propertyname] = 1;
    }
    break;

  case 'DAV::allprop':
    $properties['allprop'] = 1;
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
$qry_filters = $xmltree->GetPath('/urn:ietf:params:xml:ns:caldav:calendar-query/urn:ietf:params:xml:ns:caldav:filter/*');
if ( count($qry_filters) == 1 ) {
  $qry_filters = $qry_filters[0];  // There can only be one FILTER element
  if ( $qry_filters->GetTag() == "urn:ietf:params:xml:ns:caldav:comp-filter" && $qry_filters->GetAttribute("name") == "VCALENDAR" )
    $qry_filters = $qry_filters->GetContent();  // Everything is inside a VCALENDAR AFAICS
  else {
    dbg_error_log("calquery", "Got bizarre CALDAV:FILTER[%s=%s]] which does not contain comp-filter = VCALENDAR!!", $qry_filters->GetTag(), $qry_filters->GetAttribute("name") );
    $qry_filters = false;
  }
}
else {
  $qry_filters = false;
}


/**
* While we can construct our SQL to apply some filters in the query, other filters
* need to be checked against the retrieved record.  This is for handling those ones.
*
* @param array $filter An array of XMLElement which is the filter definition
* @param string $item The database row retrieved for this calendar item
*
* @return boolean True if the check succeeded, false otherwise.
*/
function apply_filter( $filters, $item ) {
  global $session, $c, $request;

  if ( count($filters) == 0 ) return true;

  dbg_error_log("calquery","Applying filter for item '%s'", $item->dav_name );
  $ical = new iCalendar( array( "icalendar" => $item->caldav_data) );
  return $ical->TestFilter($filters);
}


/**
 * Process a filter fragment returning an SQL fragment
 */
$need_post_filter = false;
function SqlFilterFragment( $filter, $components, $property = null, $parameter = null ) {
  global $need_post_filter;
  $sql = "";
  if ( !is_array($filter) ) {
    dbg_error_log( "calquery", "Filter is of type '%s', but should be an array of XML Tags.", gettype($filter) );
  }

  foreach( $filter AS $k => $v ) {
    $tag = $v->GetTag();
    dbg_error_log("calquery", "Processing $tag into SQL - %d, '%s', %d\n", count($components), $property, isset($parameter) );

    $not_defined = "";
    switch( $tag ) {
      case 'urn:ietf:params:xml:ns:caldav:is-not-defined':
        $not_defined = "not-"; // then fall through to IS-DEFINED case
      case 'urn:ietf:params:xml:ns:caldav:is-defined':
        if ( isset( $parameter ) ) {
          $need_post_filter = true;
          dbg_error_log("calquery", "Could not handle 'is-%sdefined' on property %s, parameter %s in SQL", $not_defined, $property, $parameter );
          return false;  // Not handled in SQL
        }
        if ( isset( $property ) ) {
          switch( $property ) {
            case 'created':
            case 'completed':  /** @todo when it can be handled in the SQL - see around line 200 below */
            case 'dtend':
            case 'dtstamp':
            case 'dtstart':
              $property_defined_match = "IS NOT NULL";
              break;

            case 'priority':
              $property_defined_match = "IS NOT NULL";
              break;

            default:
              $property_defined_match = "LIKE '_%'";  // i.e. contains a single character or more
          }
          $sql .= sprintf( "AND %s %s%s ", $property, $not_defined, $property_defined_match );
        }
        break;

      case 'urn:ietf:params:xml:ns:caldav:time-range':
        /**
        * @todo We should probably allow time range queries against other properties, since eventually some client may want to do this.
        */
        $start_column = ($components[sizeof($components)-1] == 'VTODO' ? "due" : 'dtend');     // The column we compare against the START attribute
        $finish_column = 'dtstart';  // The column we compare against the END attribute
        $start = $v->GetAttribute("start");
        $finish = $v->GetAttribute("end");
        if ( isset($start) && isset($finish) ) {
          $sql .= sprintf( "AND ( (%s >= %s::timestamp with time zone AND %s <= %s::timestamp with time zone) ",
                                  $finish_column, qpg($start), $start_column, qpg($finish));
          $sql .= sprintf( "OR calculate_later_timestamp(%s::timestamp with time zone,%s,rrule) <= %s::timestamp with time zone ", qpg($start), $finish_column, qpg($finish) );
          $sql .= sprintf( "OR calculate_later_timestamp(%s::timestamp with time zone,%s,rrule) <= %s::timestamp with time zone ", qpg($start), $start_column, qpg($finish) );
          $sql .= sprintf( "OR event_has_exceptions(caldav_data.caldav_data) )" );
        }
        else if ( isset($start) ) {
          $sql .= sprintf( "AND (%s >= %s::timestamp with time zone ", $finish_column, qpg($start));
          $sql .= sprintf( "OR calculate_later_timestamp(%s::timestamp with time zone,%s,rrule) >= %s::timestamp with time zone ", qpg($start), $finish_column, qpg($start) );
          $sql .= sprintf( "OR event_has_exceptions(caldav_data.caldav_data) )" );
        }
        else if ( isset( $finish ) ) {
          $sql .= sprintf( "AND %s <= %s::timestamp with time zone ", $start_column, qpg($finish) );
        }
        break;

      case 'urn:ietf:params:xml:ns:caldav:text-match':
        $search = $v->GetContent();
        $negate = $v->GetAttribute("negate-condition");
        $collation = $v->GetAttribute("collation");
        switch( strtolower($collation) ) {
          case 'i;octet':
            $comparison = 'LIKE';
            break;
          case 'i;ascii-casemap':
          default:
            $comparison = 'ILIKE';
            break;
        }
        dbg_error_log("calquery", " text-match: (%s IS NULL OR %s%s %s %s) ", $property, (isset($negate) && strtolower($negate) == "yes" ? "NOT ": ""),
                                          $property, $comparison, qpg("%".$search."%") );
        $sql .= sprintf( "AND (%s IS NULL OR %s%s %s %s) ", $property, (isset($negate) && strtolower($negate) == "yes" ? "NOT ": ""),
                                          $property, $comparison, qpg("%".$search."%") );
        break;

      case 'urn:ietf:params:xml:ns:caldav:comp-filter':
        $comp_filter_name = $v->GetAttribute("name");
        if ( count($components) == 0 ) {
          $sql .= "AND caldav_data.caldav_type = ".qpg($comp_filter_name)." ";
        }
        $components[] = $comp_filter_name;
        $subfilter = $v->GetContent();
        if ( is_array( $subfilter ) ) {
          $success = SqlFilterFragment( $subfilter, $components, $property, $parameter );
          if ( $success === false ) continue; else $sql .= $success;
        }
        break;

      case 'urn:ietf:params:xml:ns:caldav:prop-filter':
        $propertyname = $v->GetAttribute("name");
        switch( $propertyname ) {
          case 'PERCENT-COMPLETE':
            $property = 'percent_complete';
            break;

          case 'UID':
          case 'SUMMARY':
          case 'LOCATION':
          case 'DESCRIPTION':
          case 'CLASS':
          case 'TRANSP':
          case 'RRULE':  // Likely that this is not much use
          case 'URL':
          case 'STATUS':
          case 'CREATED':
          case 'DTSTAMP':
          case 'DTSTART':
          case 'DTEND':
          case 'DUE':
          case 'PRIORITY':
            $property = strtolower($propertyname);
            break;

          case 'COMPLETED':  /** @todo this should be moved into the properties supported in SQL. */
          default:
            $need_post_filter = true;
            dbg_error_log("calquery", "Could not handle 'prop-filter' on %s in SQL", $propertyname );
            continue;
        }
        $subfilter = $v->GetContent();
        $success = SqlFilterFragment( $subfilter, $components, $property, $parameter );
        if ( $success === false ) continue; else $sql .= $success;
        break;

      case 'urn:ietf:params:xml:ns:caldav:param-filter':
        $need_post_filter = true;
        return false; // Can't handle PARAM-FILTER conditions in the SQL
        $parameter = $v->GetAttribute("name");
        $subfilter = $v->GetContent();
        $success = SqlFilterFragment( $subfilter, $components, $property, $parameter );
        if ( $success === false ) continue; else $sql .= $success;
        break;
    }
  }
  dbg_error_log("calquery", "Generated SQL was '%s'", $sql );
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

/**
* @todo Once we are past DB version 1.2.1 we can change this query more radically.  The best performance to
* date seems to be:
*   SELECT caldav_data.*,calendar_item.* FROM collection JOIN calendar_item USING (collection_id,user_no)
*         JOIN caldav_data USING (dav_id) WHERE collection.dav_name = '/user1/home/'
*              AND caldav_data.caldav_type = 'VEVENT' ORDER BY caldav_data.user_no, caldav_data.dav_name;
*/

$where = " WHERE caldav_data.user_no = $request->user_no AND caldav_data.dav_name ~ ".qpg("^".$request->path)." ";
if ( is_array($qry_filters) ) {
  dbg_log_array( "calquery", "qry_filters", $qry_filters, true );
  $where .= BuildSqlFilter( $qry_filters );
}
if ( ! $request->AllowedTo('all') ) {
  $where .= "AND (calendar_item.class != 'PRIVATE' OR calendar_item.class IS NULL) ";
}

if ( isset($c->hide_TODO) && $c->hide_TODO && ! $request->AllowedTo('all') ) {
  $where .= "AND caldav_data.caldav_type NOT IN ('VTODO') ";
}

$sql = "SELECT * FROM caldav_data INNER JOIN calendar_item USING(dav_id,user_no,dav_name)". $where;
if ( isset($c->strict_result_ordering) && $c->strict_result_ordering ) $sql .= " ORDER BY dav_id";
$qry = new PgQuery( $sql );
if ( $qry->Exec("calquery",__LINE__,__FILE__) && $qry->rows > 0 ) {
  while( $calendar_object = $qry->Fetch() ) {
    if ( !$need_post_filter || apply_filter( $qry_filters, $calendar_object ) ) {
      $responses[] = calendar_to_xml( $properties, $calendar_object );
    }
  }
}
$multistatus = new XMLElement( "multistatus", $responses, $reply->GetXmlNsArray() );

$request->XMLResponse( 207, $multistatus );
