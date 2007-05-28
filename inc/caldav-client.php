<?
/**
* A Class for connecting to a caldav server
*
* @package caldav
* @author Andrew McMillan <debian@mcmillan.net.nz>
* @copyright Andrew McMillan
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

/**
* I be you find this hard to believe, but having to write this hack really
* annoys the crap out of me.  WTF!  Why does PHP/Curl not have a function which
* simply accepts a string as what the request will contain.  Oh no.  They only
* think of "POST" and "PUT a file".  Crap.
*
* So the following PoS code accepts that it will be called, and asked for $length
* bites of the $fd (which we ignore, because we get it all from the $_...data variable)
* and so it will eat it's way through the data.
*/
$__curl_read_callback_pos = 0;
$__curl_read_callback_data = "";
function __curl_init_callback( $data ) {
  global $__curl_read_callback_pos, $__curl_read_callback_data;
  $__curl_read_callback_pos = 0;
  $__curl_read_callback_data = $data;
  error_log( "CLIENT: Initialising callback data to: ". substr($__curl_read_callback_data,0,40)." ..." );
}
/**
* As documented in the comments on this page(!)
*    http://nz2.php.net/curl_setopt
*/
function __curl_read_callback( $ch, $fd, $length) {
  global $__curl_read_callback_pos, $__curl_read_callback_data;

  error_log( "CLIENT: ANSWER: Pos=$__curl_read_callback_pos Len=$length" );
  if ( $__curl_read_callback_pos < 0 ) {
    unset($fd);
    return "";
  }

  $answer = substr($__curl_read_callback_data, $__curl_read_callback_pos, $length );
  if ( strlen($answer) < $length ) $__curl_read_callback_pos = -1;
  else $__curl_read_callback_pos += $length;

  error_log( "CLIENT: ANSWER: POS=$__curl_read_callback_pos: ".$answer );
  return $answer;
}


/**
* A class for accessing RSCDS via CalDAV, as a client
*
* @package   rscds
*/
class CalDAVClient {
  /**
  * Server, username, password, calendar, $entry
  *
  * @var string
  */
  var $base_url, $user, $pass, $calendar, $entry;

  /**
  * The useragent which is send to the caldav server
  *
  * @var string
  */
  var $user_agent = 'RSCDSClient';

  var $headers = array();
  var $body = "";

  /**
  * Our cURL connection
  *
  * @var resource
  */
  var $curl;


  /**
  * Constructor, initialises the class
  *
  * @param string $base_url
  * @param string $user
  * @param string $pass
  * @param string $calendar
  */
  function CalDAVClient( $base_url, $user, $pass, $calendar ) {
    $this->base_url = $base_url;
    $this->user = $user;
    $this->pass = $pass;
    $this->calendar = $calendar;

    $this->curl = curl_init();
    curl_setopt($this->curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($this->curl, CURLOPT_USERPWD, "$user:$pass" );
    curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true );
    curl_setopt($this->curl, CURLOPT_BINARYTRANSFER, true );

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
  * Add a Depth: header.  Valid values are 1 or infinity
  *
  * @param int $depth  The depth, default to infinity
  */
  function SetDepth( $depth = 'infinity' ) {
    $this->headers[] = "Depth: ". ($depth == 1 ? "1" : "infinity" );
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
  * Send a request to the server
  *
  * @param string $relative_url The URL to make the request to, relative to $base_url
  *
  * @return string The content of the response from the server
  */
  function DoRequest( $relative_url = "" ) {

    curl_setopt($this->curl, CURLOPT_URL, $this->base_url . $relative_url );
    curl_setopt($this->curl, CURLOPT_USERAGENT, $this->user_agent );
    curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->headers );

    /**
    * So we don't get annoyed at self-signed certificates.  Should be a setup
    * configuration thing really.
    */
    curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false );

    $bodylen = strlen($this->body);
    if ( $bodylen > 0 ) {
      /**
      * Call our magic write the data function.  You'd think there would be a
      * simple setopt call where we could set the data to be written, but no,
      * we have to pass a function, which passes the data.
      */
      curl_setopt($this->curl, CURLOPT_UPLOAD, true );
      __curl_init_callback($this->body);
      curl_setopt($this->curl, CURLOPT_INFILESIZE, $bodylen );
      curl_setopt($this->curl, CURLOPT_READFUNCTION, '__curl_read_callback' );
    }

    $content = curl_exec($this->curl);

    return $content;
  }


  /**
  * Send an OPTIONS request to the server
  *
  * @param string $relative_url The URL to make the request to, relative to $base_url
  *
  * @return string The OPTIONS response
  */
  function DoOptionsRequest( $relative_url = "" ) {
    curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "OPTIONS" );
    $this->body = "";
    curl_setopt($this->curl, CURLOPT_HEADER, true);
    return $this->DoRequest($relative_url);
  }



  /**
  * Send an XML request to the server (e.g. PROPFIND, REPORT, MKCALENDAR)
  *
  * @param string $relative_url The URL to make the request to, relative to $base_url
  * @param string $xml The XML to send along with the request
  * @param string $method The method (PROPFIND, REPORT, etc) to use with the request
  *
  * @return string The content of the response from the server
  */
  function DoXMLRequest( $request_method, $xml, $relative_url = '' ) {
    $this->body = $xml;

    curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $request_method );
    curl_setopt($this->curl, CURLOPT_HEADER, true);
    $this->headers[] = "Content-type: text/xml";
    return $this->DoRequest($relative_url);
  }

}

/**
* Usage example

$cal = new CalDAVClient( "http://calendar.example.com/caldav.php/username/calendar/", "username", "password", "calendar" );
$options_headers = $cal->DoOptionsRequest();
$cal->SetDepth(1);
$folder_xml = $cal->DoXMLRequest("PROPFIND", '<?xml version="1.0" encoding="utf-8" ?><propfind xmlns="DAV:"><prop><getcontentlength/><getcontenttype/><resourcetype/><getetag/></prop></propfind>' );

*/

?>