<?
/**
* A Class for connecting to a caldav server
*
* @package caldav
* @author Jeppe Bob Dyrby <jeppe.dyrby@gmail.com>
* @copyright Bob
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
class caldav {
    /**
     * Server, username, password, calendar
     *
     * @var string
     */
    var $server, $user, $pass, $calendar;
    /**
     * Port number
     *
     * @var int
     */
    var $port;
    /**
     * socket
     *
     * @var socket
     */
    var $stream;
    /**
     * The useragent which is send to the caldav server
     *
     * @var string
     */
    var $user_agent = 'ARTICMEDIA PULP Calendaring System';
    /**
     * array of options, what is allowed on the server
     *
     * @var array
     */
    var $options = array();
    /**
     * Is access granted on this server? if false,
     * we wont try to log in.
     * This is automatic set, if the first login failes.
     *
     * @var bool
     */
    var $granted = true;
    /**
     * Timeout for connection
     *
     * @var int
     */
    var $timeout = 10;
    /**
     * The last error description
     *
     * @var mixed
     */
    var $last_error = "";
    
    var $header = "";
    var $body = "";
    
    /**
     * Constructer, sets the server, username etc, and connects for the first time
     * to check for permissions and options
     *
     * @param string $server
     * @param string $calendar
     * @return bool
     */
    function caldav($server,$calendar) {
        return $this->__construct($server,$calendar);
    }
    
    /**
     * Constructer, sets the server, username etc, and connects for the first time
     * to check for permissions and options
     *
     * @param string $server
     * @param string $calendar
     * @return bool
     */
    function __construct($server,$calendar) {
        $this->server = parse_url($server);
        $this->user = $this->server['user'];
        $this->pass = $this->server['pass'];
        $this->port = $port = isset($this->server['port']) ? $this->server['port'] : 80;
        $this->setCalendar($calendar);
        $test = $this->sendRequest('OPTIONS');
        if ($test) {
            $this->granted = true;
        } else {
            $this->granted = false;
        }
        return true;
    }
    
    /**
     * Sets the calender, if it is changed
     *
     * @param string $calendar
     */
    function setCalendar($calendar) {
        $this->calendar = $calendar;
    }
    
    /**
     * Initiases the connection
     *
     * @param string $do
     * @param string $content
     * @param string $mime
     * @return mixed
     */
    function sendRequest($do,$content=null,$mime=null) {
        $headers = $this->createHeader($do,$content,$mime);
        //echo $headers;
        $return = $this->open($headers);
        if ($return != false) {
            $return = split("\r\n\r\n",$return);
            $headers = $return[0];
            unset($return[0]);
            $body = implode("\r\n\r\n",$return);
            $result = $this->parseResult($do,$headers,$body);
            $this->close();
            if (!is_array($result)) return false;
            $this->header = $result[0];
            $this->body = $result[1];
            return true;
        } else {
            $this->close();
            return false;
        }
    }
     function sendPROPFIND() {
 	$xml = '<?xml version="1.0"?><D:propfind xmlns:D="DAV:"><D:prop><D:resourcetype/></D:prop></D:propfind>';
	$this->sendRequest('PROPFIND',$xml,'text/xml');
	$urlList = Array();
	$xml = new SimpleXMLElement($this->body,LIBXML_NOWARNING);
	foreach ($xml->response as $rep) {
  	 $urlList[] = (string)$rep->href;
	}
	return $urlList;
    }
     function sendPUT_CREATION($content) {
	$uid = $this->getUniqueUID();
	$this->setCalendar((string)($this->calendar.$uid.'.ics'));
	echo $this->calendar;
	$this->sendRequest('PUT',$content,'text/icalendar');

    }
     function sendPUT_UPDATE($content,$uid) {
	$this->setCalendar((string)($this->calendar.$uid));
	echo $this->calendar;
	$this->sendRequest('PUT',$content,'text/icalendar');

    }
	function getUniqueUID(){ 
	//return (string)('uuid'.time().rand(100,999));
	return md5(uniqid('',true));
    } 
    /**
     * Trims array values
     *
     * @param unknown_type $item
     * @param unknown_type $key
     */
    function aTrim(&$item,$key) {
        if (is_array($item)) {
            array_walk($item,array(&$this,'aTrim'));
        } else {
            $item = trim($item);
        }
    }
    
    /**
     * Parses the returned headers, and the body
     *
     * @param string $do
     * @param string $headers
     * @param string $body
     * @return mixed
     */
    function parseResult($do,$headers,$body) {
        //echo "\r\n".$headers."\r\n\r\n";
        $headers = explode("\r\n",$headers);
        if (!strpos($headers[0],'401') && !strpos($headers[0],'404')) {
            $nh = array();
            foreach ($headers as $line) {
                $tmp = explode(":",$line);
                $nh[$tmp[0]] = $tmp[1];
            }
            $headers = $nh;
            $headers['Content-Type'] = explode("; ",$headers['Content-Type']);
            if (strtoupper($do) == 'OPTIONS') $headers['Allow'] = explode(",",$headers['Allow']);
            $t = $this;
            if (function_exists("array_walk_recursive")) {
                array_walk_recursive($headers,array(&$this,'aTrim'));
            } else {
                array_walk($headers,array(&$this,'aTrim'));
            }
            if ($headers['Allow']) {
                $this->options = $headers['Allow'];
            }
            //print_r($headers);
            $body = utf8_decode($body);
            return array($headers,$body);
        } else {
            $this->last_error = $headers[0] ." - ".$body;
            return false;
        }
    }
    
    /**
     * Opens the connection, and writes to the server
     *
     * @param string $headers
     * @return string
     */
    function open($headers) {
        if ($this->granted == true) {
            $this->stream = fsockopen($this->server['host'],$this->port,&$errno,&$errstr,$this->timeout);
            if (!$this->stream) {
                $this->last_error = "$errno:\t$errstr";
                $this->granted = false;
                $this->close();
                return false;
            }
            if (function_exists("stream_get_contents"))
                stream_set_timeout($this->stream, $this->timeout);
            $test = fwrite($this->stream, $headers, strlen($headers));
            if ($test != strlen($headers)) {
                $this->close();
                return false;
            } else {
                if (function_exists("stream_get_contents")) {
                    return stream_get_contents($this->stream);
                } else {
                    $c = "";
                    while (!feof($this->stream)) {
                        $c .= fread($this->stream, 1024);
                    }
                    return $c;
                }
            }
        } else {
            return false;
        }
    }
    
    /**
     * Closes connection, should clean up too
     *
     */
    function close() {
        fclose($this->stream);
    }
    
    /**
     * Creates headers for the Caldav server, including content
     *
     * @param string $do
     * @param string $content
     * @param string $mime
     * @return string
     */
    function createHeader($do,$content=null,$mime=null) {
        if (strtoupper($do) != 'OPTIONS') {
            if (!in_array(strtoupper($do),$this->options)) return;
        }
        $headers = array();    
        $headers[] = strtoupper($do)." ".$this->calendar." HTTP/1.0";
        $headers[] = "Host: ".$this->server['host'];
        $headers[] = "User-Agent: ".$this->user_agent;
        if ($mime) $headers[] = "Content-Type: ".$mime."; charset=\"utf-8\"";
        if ($content) {
            $content .= "\r\n";
            $headers[] .= "Content-Length: ".strlen($content);
        }
        if ($this->user && $this->pass) $headers[] = "Authorization: Basic ".base64_encode("$this->user:$this->pass");
        $headers[] = "Connection: close";
        $headers[] = "";
        $headers[] = "";
        if ($content) $headers[] = $content;
        //return wordwrap(implode("\r\n",$headers),76);
	return implode("\r\n",$headers);
    }
    
}

$c = new caldav('http://jonathan:phoenix@10.0.1.30','/caldav/caldav.php/jonathan/');
$icsStream = 
"BEGIN:VCALENDAR
PRODID:-//Mozilla Calendar//NONSGML Sunbird//EN
VERSION:2.0
BEGIN:VEVENT
CREATED:20070524T090157Z
LAST-MODIFIED:20070524T090208Z
DTSTAMP:20070524T090208Z
UID:uuid1179997317576
SUMMARY:nox updated
DTSTART;TZID=/mozilla.org/20070129_1/Africa/Ceuta:20070524T090000
DTEND;TZID=/mozilla.org/20070129_1/Africa/Ceuta:20070524T100000
X-MOZ-LOCATIONPATH:uuid1179997317576.ics
LOCATION;LANGUAGE=fr;ENCODING=QUOTED-PRINTABLE:ii
END:VEVENT
BEGIN:VTIMEZONE
TZID:/mozilla.org/20070129_1/Africa/Ceuta
X-LIC-LOCATION:Africa/Ceuta
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=3
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=10
END:STANDARD
END:VTIMEZONE
END:VCALENDAR";
 $xml = 
'<?xml version="1.0"?><D:propfind xmlns:D="DAV:"><D:prop><D:resourcetype/></D:prop></D:propfind>';
//$c->sendRequest('PUT',$icsStream,'text/icalendar');
//$c->sendRequest('PUT',$icsStream,'text/icalendar');
//echo(print_r($c->header).'****'.$c->body);
$c->sendPUT_UPDATE($icsStream,'uuid1180017338347.ics');
//$c->sendRequest('PUT',$icsStream,'text/icalendar');
//$c->sendPUT()
//echo($c->getUniqueUID());
?> 
