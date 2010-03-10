<?php
/**
* A Class for connecting to a caldav server
*
* @package   awl
*
* @subpackage   caldav
* @author Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Andrew McMillan
* @license   http://www.gnu.org/licenses/lgpl-3.0.txt  GNU LGPL version 3 or later
*/

require_once('XMLDocument.php');

/**
* A class for holding basic calendar information
* @package awl
*/
class CalendarInfo {
  public $url, $displayname, $getctag;

  function __construct( $url, $displayname = null, $getctag = null ) {
    $this->url = $url;
    $this->displayname = $displayname;
    $this->getctag = $getctag;
  }

  function __toString() {
    return( '(URL: '.$this->url.'   Ctag: '.$this->getctag.'   Displayname: '.$this->displayname .')'. "\n" );
  }
}


/**
* A class for accessing DAViCal via CalDAV, as a client
*
* @package   awl
*/
class CalDAVClient {
  /**
  * Server, username, password, calendar
  *
  * @var string
  */
  protected $base_url, $user, $pass, $entry, $protocol, $server, $port;

  /**
  * The principal-URL we're using
  */
  protected $principal_url;

  /**
  * The calendar-URL we're using
  */
  protected $calendar_url;

  /**
  * The calendar-home-set we're using
  */
  protected $calendar_home_set;

  /**
  * The calendar_urls we have discovered
  */
  protected $calendar_urls;

  /**
  * The useragent which is send to the caldav server
  *
  * @var string
  */
  public $user_agent = 'DAViCalClient';

  protected $headers = array();
  protected $body = "";
  protected $requestMethod = "GET";
  protected $httpRequest = "";  // for debugging http headers sent
  protected $xmlRequest = "";   // for debugging xml sent
  protected $httpResponse = ""; // http headers received
  protected $xmlResponse = "";  // xml received

  protected $parser; // our XML parser object

  /**
  * Constructor, initialises the class
  *
  * @param string $base_url  The URL for the calendar server
  * @param string $user      The name of the user logging in
  * @param string $pass      The password for that user
  */
  function __construct( $base_url, $user, $pass ) {
    $this->user = $user;
    $this->pass = $pass;
    $this->headers = array();

    if ( preg_match( '#^(https?)://([a-z0-9.-]+)(:([0-9]+))?(/.*)$#', $base_url, $matches ) ) {
      $this->server = $matches[2];
      $this->base_url = $matches[5];
      if ( $matches[1] == 'https' ) {
        $this->protocol = 'ssl';
        $this->port = 443;
      }
      else {
        $this->protocol = 'tcp';
        $this->port = 80;
      }
      if ( $matches[4] != '' ) {
        $this->port = intval($matches[4]);
      }
    }
    else {
      trigger_error("Invalid URL: '".$base_url."'", E_USER_ERROR);
    }
  }

  /**
  * Adds an If-Match or If-None-Match header
  *
  * @param bool $match to Match or Not to Match, that is the question!
  * @param string $etag The etag to match / not match against.
  */
  function SetMatch( $match, $etag = '*' ) {
    $this->headers[] = sprintf( "%s-Match: %s", ($match ? "If" : "If-None"), $etag);
  }

  /**
  * Add a Depth: header.  Valid values are 0, 1 or infinity
  *
  * @param int $depth  The depth, default to infinity
  */
  function SetDepth( $depth = '0' ) {
    $this->headers[] = 'Depth: '. ($depth == '1' ? "1" : ($depth == 'infinity' ? $depth : "0") );
  }

  /**
  * Add a Depth: header.  Valid values are 1 or infinity
  *
  * @param int $depth  The depth, default to infinity
  */
  function SetUserAgent( $user_agent = null ) {
    if ( !isset($user_agent) ) $user_agent = $this->user_agent;
    $this->user_agent = $user_agent;
  }

  /**
  * Add a Content-type: header.
  *
  * @param int $type  The content type
  */
  function SetContentType( $type ) {
    $this->headers[] = "Content-type: $type";
  }

  /**
  * Split response into httpResponse and xmlResponse
  *
  * @param string Response from server
   */
  function ParseResponse( $response ) {
    $pos = strpos($response, '<?xml');
    if ($pos === false) {
      $this->httpResponse = trim($response);
    }
    else {
      $this->httpResponse = trim(substr($response, 0, $pos));
      $this->xmlResponse = trim(substr($response, $pos));
      $parser = xml_parser_create_ns('UTF-8');
      xml_parser_set_option ( $parser, XML_OPTION_SKIP_WHITE, 1 );
      xml_parser_set_option ( $parser, XML_OPTION_CASE_FOLDING, 0 );

      if ( 0 == xml_parse_into_struct( $parser, $this->xmlResponse, $this->xmlnodes, $this->xmltags ) ) {
        printf( "XML parsing error: %s - %s\n", xml_get_error_code($this->parser), xml_error_string(xml_get_error_code($this->parser)) );
      }
/*      else {
        echo "\nNodes array............................................................\n";
        print_r( $this->xmlnodes );
        echo "\nTags array............................................................\n";
        print_r( $this->xmltags );
      }
*/
      xml_parser_free($parser);
    }
  }

  /**
   * Output http request headers
   *
   * @return HTTP headers
   */
  function GetHttpRequest() {
      return $this->httpRequest;
  }
  /**
   * Output http response headers
   *
   * @return HTTP headers
   */
  function GetHttpResponse() {
      return $this->httpResponse;
  }
  /**
   * Output xml request
   *
   * @return raw xml
   */
  function GetXmlRequest() {
      return $this->xmlRequest;
  }
  /**
   * Output xml response
   *
   * @return raw xml
   */
  function GetXmlResponse() {
      return $this->xmlResponse;
  }

  /**
  * Send a request to the server
  *
  * @param string $url The URL to make the request to
  *
  * @return string The content of the response from the server
  */
  function DoRequest( $url = null ) {
    if(!defined("_FSOCK_TIMEOUT")){ define("_FSOCK_TIMEOUT", 10); }
    $headers = array();

    if ( !isset($url) ) $url = $this->base_url;
    $this->request_url = $url;
    $headers[] = $this->requestMethod." ". $this->request_url . " HTTP/1.1";
    $headers[] = "Authorization: Basic ".base64_encode($this->user .":". $this->pass );
    $headers[] = "Host: ".$this->server .":".$this->port;

    foreach( $this->headers as $ii => $head ) {
      $headers[] = $head;
    }
    $headers[] = "Content-Length: " . strlen($this->body);
    $headers[] = "User-Agent: " . $this->user_agent;
    $headers[] = 'Connection: close';
    $this->httpRequest = join("\r\n",$headers);
    $this->xmlRequest = $this->body;

    $fip = fsockopen( $this->protocol . '://' . $this->server, $this->port, $errno, $errstr, _FSOCK_TIMEOUT); //error handling?
    if ( !(get_resource_type($fip) == 'stream') ) return false;
    if ( !fwrite($fip, $this->httpRequest."\r\n\r\n".$this->body) ) { fclose($fip); return false; }
    $rsp = "";
    while( !feof($fip) ) { $rsp .= fgets($fip,8192); }
    fclose($fip);

    $this->headers = array();  // reset the headers array for our next request
    $this->ParseResponse($rsp);
    return $rsp;
  }


  /**
  * Send an OPTIONS request to the server
  *
  * @param string $url The URL to make the request to
  *
  * @return array The allowed options
  */
  function DoOptionsRequest( $url = null ) {
    $this->requestMethod = "OPTIONS";
    $this->body = "";
    $headers = $this->DoRequest($url);
    $options_header = preg_replace( '/^.*Allow: ([a-z, ]+)\r?\n.*/is', '$1', $headers );
    $options = array_flip( preg_split( '/[, ]+/', $options_header ));
    return $options;
  }



  /**
  * Send an XML request to the server (e.g. PROPFIND, REPORT, MKCALENDAR)
  *
  * @param string $method The method (PROPFIND, REPORT, etc) to use with the request
  * @param string $xml The XML to send along with the request
  * @param string $url The URL to make the request to
  *
  * @return array An array of the allowed methods
  */
  function DoXMLRequest( $request_method, $xml, $url = null ) {
    $this->body = $xml;
    $this->requestMethod = $request_method;
    $this->SetContentType("text/xml");
    return $this->DoRequest($url);
  }



  /**
  * Get a single item from the server.
  *
  * @param string $url The URL to GET
  */
  function DoGETRequest( $url ) {
    $this->body = "";
    $this->requestMethod = "GET";
    return $this->DoRequest( $url );
  }


  /**
  * PUT a text/icalendar resource, returning the etag
  *
  * @param string $url The URL to make the request to
  * @param string $icalendar The iCalendar resource to send to the server
  * @param string $etag The etag of an existing resource to be overwritten, or '*' for a new resource.
  *
  * @return string The content of the response from the server
  */
  function DoPUTRequest( $url, $icalendar, $etag = null ) {
    $this->body = $icalendar;

    $this->requestMethod = "PUT";
    if ( $etag != null ) {
      $this->SetMatch( ($etag != '*'), $etag );
    }
    $this->SetContentType("text/icalendar");
    $headers = $this->DoRequest($url);

    /**
    * RSCDS will always return the real etag on PUT.  Other CalDAV servers may need
    * more work, but we are assuming we are running against RSCDS in this case.
    */
    $etag = preg_replace( '/^.*Etag: "?([^"\r\n]+)"?\r?\n.*/is', '$1', $headers );
    return $etag;
  }


  /**
  * DELETE a text/icalendar resource
  *
  * @param string $url The URL to make the request to
  * @param string $etag The etag of an existing resource to be deleted, or '*' for any resource at that URL.
  *
  * @return int The HTTP Result Code for the DELETE
  */
  function DoDELETERequest( $url, $etag = null ) {
    $this->body = "";

    $this->requestMethod = "DELETE";
    if ( $etag != null ) {
      $this->SetMatch( true, $etag );
    }
    $this->DoRequest($url);
    return $this->resultcode;
  }


  /**
  * Get a single item from the server.
  *
  * @param string $url The URL to PROPFIND on
  */
  function DoPROPFINDRequest( $url, $props, $depth = 0 ) {
    $this->SetDepth($depth);
    $xml = new XMLDocument( array( 'DAV:' => '', 'urn:ietf:params:xml:ns:caldav' => 'C' ) );
    $prop = new XMLElement('prop');
    foreach( $props AS $v ) {
      $xml->NSElement($prop,$v);
    }

    $this->body = $xml->Render('propfind',$prop );

    $this->requestMethod = "PROPFIND";
    $this->SetContentType("text/xml");
    $this->DoRequest($url);
    return $this->GetXmlResponse();
  }


  /**
  * Get/Set the Principal URL
  *
  * @param $url string The Principal URL to set
  */
  function PrincipalURL( $url = null ) {
    if ( isset($url) ) {
      $this->principal_url = $url;
    }
    return $this->principal_url;
  }


  /**
  * Get/Set the calendar-home-set URL
  *
  * @param $url array of string The calendar-home-set URLs to set
  */
  function CalendarHomeSet( $urls = null ) {
    if ( isset($urls) ) {
      if ( ! is_array($urls) ) $urls = array($urls);
      $this->calendar_home_set = $urls;
    }
    return $this->calendar_home_set;
  }


  /**
  * Get/Set the calendar-home-set URL
  *
  * @param $urls array of string The calendar URLs to set
  */
  function CalendarUrls( $urls = null ) {
    if ( isset($urls) ) {
      if ( ! is_array($urls) ) $urls = array($urls);
      $this->calendar_urls = $urls;
    }
    return $this->calendar_urls;
  }


  /**
  * Return the first occurrence of an href inside the named tag.
  *
  * @param string $tagname The tag name to find the href inside of
  */
  function HrefValueInside( $tagname ) {
    foreach( $this->xmltags[$tagname] AS $k => $v ) {
      $j = $v + 1;
      if ( $this->xmlnodes[$j]['tag'] == 'DAV::href' ) {
        return $this->xmlnodes[$j]['value'];
      }
    }
    return null;
  }


  /**
  * Return the href which has a resourcetype of the specified type
  *
  * @param string $tagname The tag name of the resourcetype to find the href for
  * @param integer $which Which instance of the tag should we use
  */
  function HrefForResourcetype( $tagname, $i = 0 ) {
    if ( isset($this->xmltags[$tagname]) && isset($this->xmltags[$tagname][$i]) ) {
      $j = $this->xmltags[$tagname][$i];
      while( $j-- > 0 && $this->xmlnodes[$j]['tag'] != 'DAV::resourcetype' );
      if ( $j > 0 ) {
        while( $j-- > 0 && $this->xmlnodes[$j]['tag'] != 'DAV::href' );
        if ( $j > 0 && isset($this->xmlnodes[$j]['value']) ) {
          return $this->xmlnodes[$j]['value'];
        }
      }
    }
    return null;
  }


  /**
  * Return the <prop> ... </prop> of a propstat where the status is OK
  *
  * @param string $nodenum The node number in the xmlnodes which is the href
  */
  function GetOKProps( $nodenum ) {
    $props = null;
    $level = $this->xmlnodes[$nodenum]['level'];
    $status = '';
    while ( $this->xmlnodes[++$nodenum]['level'] >= $level ) {
      if ( $this->xmlnodes[$nodenum]['tag'] == 'DAV::propstat' ) {
        if ( $this->xmlnodes[$nodenum]['type'] == 'open' ) {
          $props = array();
          $status = '';
        }
        else {
          if ( $status == 'HTTP/1.1 200 OK' ) break;
        }
      }
      elseif ( $this->xmlnodes[$nodenum]['tag'] == 'DAV::status' ) {
        $status = $this->xmlnodes[$nodenum]['value'];
      }
      else {
        $props[] = $this->xmlnodes[$nodenum];
      }
    }
    return $props;
  }


  /**
  * Attack the given URL in an attempt to find a principal URL
  *
  * @param string $url The URL to find the principal-URL from
  */
  function FindPrincipal( $url ) {
    $xml = $this->DoPROPFINDRequest( $url, array('resourcetype', 'current-user-principal', 'owner', 'principal-URL',
                                  'urn:ietf:params:xml:ns:caldav:calendar-home-set'), 1);

    $principal_url = $this->HrefForResourcetype('DAV::principal');

    foreach( array('DAV::current-user-principal', 'DAV::principal-URL', 'DAV::owner') AS $v ) {
      if ( !isset($principal_url) ) {
        $principal_url = $this->HrefValueInside($v);
      }
    }

    return $this->PrincipalURL($principal_url);
  }


  /**
  * Attack the given URL in an attempt to find a principal URL
  *
  * @param string $url The URL to find the calendar-home-set from
  */
  function FindCalendarHome( $recursed=false ) {
    if ( !isset($this->principal_url) ) {
      $this->FindPrincipal();
    }
    if ( $recursed ) {
      $this->DoPROPFINDRequest( $this->principal_url, array('urn:ietf:params:xml:ns:caldav:calendar-home-set'), 0);
    }

    $calendar_home = array();
    foreach( $this->xmltags['urn:ietf:params:xml:ns:caldav:calendar-home-set'] AS $k => $v ) {
      if ( $this->xmlnodes[$v]['type'] != 'open' ) continue;
      while( $this->xmlnodes[++$v]['type'] != 'close' && $this->xmlnodes[$v]['tag'] != 'urn:ietf:params:xml:ns:caldav:calendar-home-set' ) {
//        printf( "Tag: '%s' = '%s'\n", $this->xmlnodes[$v]['tag'], $this->xmlnodes[$v]['value']);
        if ( $this->xmlnodes[$v]['tag'] == 'DAV::href' && isset($this->xmlnodes[$v]['value']) )
          $calendar_home[] = $this->xmlnodes[$v]['value'];
      }
    }

    if ( !$recursed && count($calendar_home) < 1 ) {
      $calendar_home = $this->FindCalendarHome(true);
    }

    return $this->CalendarHomeSet($calendar_home);
  }


  /**
  * Find the calendars, from the calendar_home_set
  */
  function FindCalendars( $recursed=false ) {
    if ( !isset($this->calendar_home_set[0]) ) {
      $this->FindCalendarHome();
    }
    $this->DoPROPFINDRequest( $this->calendar_home_set[0], array('resourcetype','displayname','http://calendarserver.org/ns/:getctag'), 1);

    $calendars = array();
    if ( isset($this->xmltags['urn:ietf:params:xml:ns:caldav:calendar']) ) {
      $calendar_urls = array();
      foreach( $this->xmltags['urn:ietf:params:xml:ns:caldav:calendar'] AS $k => $v ) {
        $calendar_urls[$this->HrefForResourcetype('urn:ietf:params:xml:ns:caldav:calendar', $k)] = 1;
      }

      foreach( $this->xmltags['DAV::href'] AS $i => $hnode ) {
        $href = $this->xmlnodes[$hnode]['value'];

        if ( !isset($calendar_urls[$href]) ) continue;

//        printf("Seems '%s' is a calendar.\n", $href );

        $calendar = new CalendarInfo($href);
        $ok_props = $this->GetOKProps($hnode);
        foreach( $ok_props AS $v ) {
//          printf("Looking at: %s[%s]\n", $href, $v['tag'] );
          switch( $v['tag'] ) {
            case 'http://calendarserver.org/ns/:getctag':
              $calendar->getctag = $v['value'];
              break;
            case 'DAV::displayname':
              $calendar->displayname = $v['value'];
              break;
          }
        }
        $calendars[] = $calendar;
      }
    }

    return $this->CalendarUrls($calendars);
  }


  /**
  * Given XML for a calendar query, return an array of the events (/todos) in the
  * response.  Each event in the array will have a 'href', 'etag' and '$response_type'
  * part, where the 'href' is relative to the calendar and the '$response_type' contains the
  * definition of the calendar data in iCalendar format.
  *
  * @param string $filter XML fragment which is the <filter> element of a calendar-query
  * @param string $relative_url The URL relative to the base_url specified when the calendar was opened.  Default ''.
  * @param string $report_type Used as a name for the array element containing the calendar data. @deprecated
  *
  * @return array An array of the relative URLs, etags, and events from the server.  Each element of the array will
  *               be an array with 'href', 'etag' and 'data' elements, corresponding to the URL, the server-supplied
  *               etag (which only varies when the data changes) and the calendar data in iCalendar format.
  */
  function DoCalendarQuery( $filter, $relative_url = '' ) {

    $xml = <<<EOXML
<?xml version="1.0" encoding="utf-8" ?>
<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
  <D:prop>
    <C:calendar-data/>
    <D:getetag/>
  </D:prop>$filter
</C:calendar-query>
EOXML;

    $this->DoXMLRequest( 'REPORT', $xml, $relative_url );
    $xml_parser = xml_parser_create_ns('UTF-8');
    $this->xml_tags = array();
    xml_parser_set_option ( $xml_parser, XML_OPTION_SKIP_WHITE, 1 );
    xml_parse_into_struct( $xml_parser, $this->xmlResponse, $this->xml_tags );
    xml_parser_free($xml_parser);

    $report = array();
    foreach( $this->xml_tags as $k => $v ) {
      switch( $v['tag'] ) {
        case 'DAV::RESPONSE':
          if ( $v['type'] == 'open' ) {
            $response = array();
          }
          elseif ( $v['type'] == 'close' ) {
            $report[] = $response;
          }
          break;
        case 'DAV::HREF':
          $response['href'] = basename( $v['value'] );
          break;
        case 'DAV::GETETAG':
          $response['etag'] = preg_replace('/^"?([^"]+)"?/', '$1', $v['value']);
          break;
        case 'URN:IETF:PARAMS:XML:NS:CALDAV:CALENDAR-DATA':
          $response['data'] = $v['value'];
          break;
      }
    }
    return $report;
  }


  /**
  * Get the events in a range from $start to $finish.  The dates should be in the
  * format yyyymmddThhmmssZ and should be in GMT.  The events are returned as an
  * array of event arrays.  Each event array will have a 'href', 'etag' and 'event'
  * part, where the 'href' is relative to the calendar and the event contains the
  * definition of the event in iCalendar format.
  *
  * @param timestamp $start The start time for the period
  * @param timestamp $finish The finish time for the period
  * @param string    $relative_url The URL relative to the base_url specified when the calendar was opened.  Default ''.
  *
  * @return array An array of the relative URLs, etags, and events, returned from DoCalendarQuery() @see DoCalendarQuery()
  */
  function GetEvents( $start = null, $finish = null, $relative_url = '' ) {
    $filter = "";
    if ( isset($start) && isset($finish) )
        $range = "<C:time-range start=\"$start\" end=\"$finish\"/>";
    else
        $range = '';

    $filter = <<<EOFILTER
  <C:filter>
    <C:comp-filter name="VCALENDAR">
      <C:comp-filter name="VEVENT">
        $range
      </C:comp-filter>
    </C:comp-filter>
  </C:filter>
EOFILTER;

    return $this->DoCalendarQuery($filter, $relative_url);
  }


  /**
  * Get the todo's in a range from $start to $finish.  The dates should be in the
  * format yyyymmddThhmmssZ and should be in GMT.  The events are returned as an
  * array of event arrays.  Each event array will have a 'href', 'etag' and 'event'
  * part, where the 'href' is relative to the calendar and the event contains the
  * definition of the event in iCalendar format.
  *
  * @param timestamp $start The start time for the period
  * @param timestamp $finish The finish time for the period
  * @param boolean   $completed Whether to include completed tasks
  * @param boolean   $cancelled Whether to include cancelled tasks
  * @param string    $relative_url The URL relative to the base_url specified when the calendar was opened.  Default ''.
  *
  * @return array An array of the relative URLs, etags, and events, returned from DoCalendarQuery() @see DoCalendarQuery()
  */
  function GetTodos( $start, $finish, $completed = false, $cancelled = false, $relative_url = "" ) {

    if ( $start && $finish ) {
$time_range = <<<EOTIME
                <C:time-range start="$start" end="$finish"/>
EOTIME;
    }

    // Warning!  May contain traces of double negatives...
    $neg_cancelled = ( $cancelled === true ? "no" : "yes" );
    $neg_completed = ( $cancelled === true ? "no" : "yes" );

    $filter = <<<EOFILTER
  <C:filter>
    <C:comp-filter name="VCALENDAR">
          <C:comp-filter name="VTODO">
                <C:prop-filter name="STATUS">
                        <C:text-match negate-condition="$neg_completed">COMPLETED</C:text-match>
                </C:prop-filter>
                <C:prop-filter name="STATUS">
                        <C:text-match negate-condition="$neg_cancelled">CANCELLED</C:text-match>
                </C:prop-filter>$time_range
          </C:comp-filter>
    </C:comp-filter>
  </C:filter>
EOFILTER;

    return $this->DoCalendarQuery($filter, $relative_url);
  }


  /**
  * Get the calendar entry by UID
  *
  * @param uid
  * @param string    $relative_url The URL relative to the base_url specified when the calendar was opened.  Default ''.
  *
  * @return array An array of the relative URL, etag, and calendar data returned from DoCalendarQuery() @see DoCalendarQuery()
  */
  function GetEntryByUid( $uid, $relative_url = '' ) {
    $filter = "";
    if ( $uid ) {
      $filter = <<<EOFILTER
  <C:filter>
    <C:comp-filter name="VCALENDAR">
          <C:comp-filter name="VEVENT">
                <C:prop-filter name="UID">
                        <C:text-match icollation="i;octet">$uid</C:text-match>
                </C:prop-filter>
          </C:comp-filter>
    </C:comp-filter>
  </C:filter>
EOFILTER;
    }

    return $this->DoCalendarQuery($filter, $relative_url);
  }


  /**
  * Get the calendar entry by HREF
  *
  * @param string    $href         The href from a call to GetEvents or GetTodos etc.
  * @param string    $relative_url The URL relative to the base_url specified when the calendar was opened.  Default ''.
  *
  * @return string The iCalendar of the calendar entry
  */
  function GetEntryByHref( $href, $relative_url = '' ) {
    return $this->DoGETRequest( $relative_url . $href );
  }

}

/**
* Usage example
*
* $cal = new CalDAVClient( "http://calendar.example.com/caldav.php/username/calendar/", "username", "password", "calendar" );
* $options = $cal->DoOptionsRequest();
* if ( isset($options["PROPFIND"]) ) {
*   // Fetch some information about the events in that calendar
*   $cal->SetDepth(1);
*   $folder_xml = $cal->DoXMLRequest("PROPFIND", '<?xml version="1.0" encoding="utf-8" ?><propfind xmlns="DAV:"><prop><getcontentlength/><getcontenttype/><resourcetype/><getetag/></prop></propfind>' );
* }
* // Fetch all events for February
* $events = $cal->GetEvents("20070101T000000Z","20070201T000000Z");
* foreach ( $events AS $k => $event ) {
*   do_something_with_event_data( $event['data'] );
* }
* $acc = array();
* $acc["google"] = array(
* "user"=>"kunsttherapie@gmail.com",
* "pass"=>"xxxxx",
* "server"=>"ssl://www.google.com",
* "port"=>"443",
* "uri"=>"https://www.google.com/calendar/dav/kunsttherapie@gmail.com/events/",
* );
*
* $acc["davical"] = array(
* "user"=>"some_user",
* "pass"=>"big secret",
* "server"=>"calendar.foo.bar",
* "port"=>"80",
* "uri"=>"http://calendar.foo.bar/caldav.php/some_user/home/",
* );
* //*******************************
*
* $account = $acc["davical"];
*
* //*******************************
* $cal = new CalDAVClient( $account["uri"], $account["user"], $account["pass"], "", $account["server"], $account["port"] );
* $options = $cal->DoOptionsRequest();
* print_r($options);
*
* //*******************************
* //*******************************
*
* $xmlC = <<<PROPP
* <?xml version="1.0" encoding="utf-8" ?>
* <D:propfind xmlns:D="DAV:" xmlns:C="http://calendarserver.org/ns/">
*     <D:prop>
*             <D:displayname />
*             <C:getctag />
*             <D:resourcetype />
*
*     </D:prop>
* </D:propfind>
* PROPP;
* //if ( isset($options["PROPFIND"]) ) {
*   // Fetch some information about the events in that calendar
* //  $cal->SetDepth(1);
* //  $folder_xml = $cal->DoXMLRequest("PROPFIND", $xmlC);
* //  print_r( $folder_xml);
* //}
*
* // Fetch all events for February
* $events = $cal->GetEvents("20090201T000000Z","20090301T000000Z");
* foreach ( $events as $k => $event ) {
*     print_r($event['data']);
*     print "\n---------------------------------------------\n";
* }
*
* //*******************************
* //*******************************
*/
