<?php
/**
* Functions that are needed for iScheduling requests
*
*  - verifying Domain Key signatures
*  - delivering remote scheduling requests to local users inboxes
*  - Utility functions which we can use to decide whether this
*    is a permitted activity for this user.
*
* @package   davical
* @subpackage   iSchedule
* @author    Rob Ostensen <rob@boxacle.net>
* @copyright Rob Ostensen
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

require_once("XMLDocument.php");

/**
* A class for handling iScheduling requests.
*
* @package   davical
* @subpackage   iSchedule
*/
class iSchedule
{
  public $parsed;
  public $selector;
  public $domain;
  private $dk;
  private $DKSig;
  private $try_anyway = false;
  private $failed = false;
  private $failOnError = true;
  private $subdomainsOK = true;
  private $remote_public_key ;
  private $required_headers = Array ( 'Host',  // draft 01 section 7.1 required headers
                                      'Originator', 
                                      'Recipient', 
                                      'Content-Type' );
  private $disallowed_headers = Array ( 'Connection',  // draft 01 section 7.1 disallowed headers
                                        'Keep-Alive', 
                                        'Proxy-Authenticate', 
                                        'Proxy-Authorization', 
                                        'TE', 
                                        'Trailers', 
                                        'Transfer-Encoding', 
                                        'Upgrade' );

  function __construct ( )
  {
    global $c;
    $this->selector = 'cal';
    if ( is_object ( $c ) && isset ( $c->scheduling_dkim_selector ) )
    {
      $this->scheduling_dkim_selector = $c->scheduling_dkim_selector ;
      $this->schedule_private_key = $c->schedule_private_key ;
      if ( isset ( $c->scheduling_dkim_algo ) )
        $this->scheduling_dkim_algo = $c->scheduling_dkim_algo;
      else
        $this->scheduling_dkim_algo = 'sha256';
      if ( isset ( $c->scheduling_dkim_valid_time ) )
        $this->valid_time = $c->scheduling_dkim_valid_time;
    }
  }

  /**
  * gets the domainkey TXT record from DNS  
  */ 
  function getTxt ()
  {
    // TODO handle parents of subdomains and procuration records
    $dkim = dns_get_record ( $this->remote_selector . '._domainkey.' . $this->remote_server , DNS_TXT );
    if ( count ( $dkim ) > 0 )
      $this->dk = $dkim [ 0 ] [ 'txt' ];
    else 
    {
      $this->failed = true;
      return false;
    }
    return true;  
  }

  /**
  * strictly for testing purposes
  */
  function setTxt ( $dk )
  {
    $this->dk = $dk;
  }

  /**
  * parses DNS TXT record from domainkey lookup
  */ 
  function parseTxt ( )
  {
    if ( $this->failed == true )
      return false;
    $clean = preg_replace ( '/[\s\t]*([;=])[\s\t]*/', '$1', $this->dk );
    $pairs = preg_split ( '/;/', $clean );
    $this->parsed = array();
    foreach ( $pairs  as $v )
    {
      list($key,$value) = preg_split ( '/=/', $v, 2 );
      if ( preg_match ( '/(g|k|n|p|s|t|v)/', $key ) )
        $this->parsed [ $key ] = $value;
      else
        $this->parsed_ignored [ $key ] = $value;
    }
    return true;
  }
  
  /**
  * validates that domainkey is acceptable for the current request
  */ 
  function validateKey ( )
  {
    $this->failed = true;
    if ( isset ( $this->parsed [ 's' ] ) )
    {
      if ( ! preg_match ( '/(\*|calendar)/', $this->parsed [ 's' ] ) )
        return false; // not a wildcard or calendar key
    }
    if ( isset ( $this->parsed [ 'k' ] ) && $this->parsed [ 'k' ] != 'rsa' )
      return false; // we only speak rsa for now
    if ( isset ( $this->parsed [ 't' ] ) && ! preg_match ( '/^[y:s]+$/', $this->parsed [ 't' ] ) )
      return false;
    else
    {
      if ( preg_match ( '/y/', $this->parsed [ 't' ] ) )
        $this->failOnError = false;
      if ( preg_match ( '/s/', $this->parsed [ 't' ] ) )
        $this->subdomainsOK = false;
    }
    if ( isset ( $this->parsed [ 'g' ] ) )
      $this->remote_user_rule = $this->parsed [ 'g' ];
    else
      $this->remote_user_rule = '*';
    if ( isset ( $this->parsed [ 'p' ] ) )
    {
      $data = "-----BEGIN PUBLIC KEY-----\n" . implode ("\n",str_split ( preg_replace ( '/_/', '', $this->parsed [ 'p' ] ), 64 )) . "\n-----END PUBLIC KEY-----"; 
      if ( $data === false )
        return false;
      $this->remote_public_key = $data;
    }
    else 
      return false;
    $this->failed = false;
    return true;
  }

  /**
  * finds a remote calendar server via DNS SRV records
  */ 
  function getServer ( )
  {
    $this->remote_ssl = false;
    $r = dns_get_record ( '_ischedules._tcp.' . $this->domain , DNS_SRV );
    if ( 0 < count ( $r ) )
    {
      $remote_server            = $r [ 0 ] [ 'target' ];
      $remote_port              = $r [ 0 ] [ 'port' ];
      $this->remote_ssl = true;
    }
    if ( ! isset ( $remote_server ) )
    {
      $r = dns_get_record ( '_ischedule._tcp.' . $this->domain , DNS_SRV );
      if ( 0 < count ( $r ) )
      {
        $remote_server            = $r [ 0 ] [ 'target' ];
        $remote_port              = $r [ 0 ] [ 'port' ];
      }
    }
    elseif ( $this->try_anyway == true )
    {
      if ( ! isset ( $remote_server ) )
        $remote_server = $this->domain;
      if ( ! isset ( $remote_port ) )
        $remote_port = 80;
    }
    if ( ! isset ( $remote_server ) )
      return false;
    $this->remote_server = $remote_server;
    $this->remote_port = $remote_port;
  }

  /**
  * get capabilities from remote server 
  */ 
  function getCapabilities ( )
  {
    $remote_capabilities = file_get_contents ( 'http'. ( $this->remote_ssl ? 's' : '' ) . '://' . 
      $this->remote_server . ':' . $this->remote_port . 
      '/.well-known/ischedule?query=capabilities' );
    if ( $remote_capabilities === false )
      return false;
    $xml_parser = xml_parser_create_ns('UTF-8');
    $this->xml_tags = array();
    xml_parser_set_option ( $xml_parser, XML_OPTION_SKIP_WHITE, 1 );
    xml_parser_set_option ( $xml_parser, XML_OPTION_CASE_FOLDING, 0 );
    $rc = xml_parse_into_struct( $xml_parser, $remote_capabilities, $this->xml_tags );
    if ( $rc == false ) {
      dbg_error_log( 'ERROR', 'XML parsing error: %s at line %d, column %d',
                  xml_error_string(xml_get_error_code($xml_parser)),
                  xml_get_current_line_number($xml_parser), xml_get_current_column_number($xml_parser) );
      return false;
    }
    xml_parser_free($xml_parser); 
    $xmltree = BuildXMLTree( $this->xml_tags );
    if ( !is_object($xmltree) ) {
      $request->DoResponse( 406, translate("REPORT body is not valid XML data!") );
      return false;
    } 
    $this->capbilities_xml = $xmltree;
    return true;
  }

  /**
  * signs a POST body and headers 
  *
  * @param string $body the body of the POST
  * @param array  $headers the headers to sign as passed to header ();
  */
  function signDKIM ( $body, $headers )
  {
    $b = '';
    if ( ! is_array ( $headers ) )
      return false;
    foreach ( $headers as $key => $value )
    {
      $b .= $key . ': ' . $value . "\r\n";
    }
    $dk['v'] = '1';
    $dk['a'] = 'rsa-' . $this->scheduling_dkim_algo;
    $dk['s'] = $this->selector;
    $dk['d'] = $this->domain;
    $dk['c'] = 'simple-http'; // implied canonicalization of simple-http/simple from rfc4871 Section-3.5
    if ( isset ( $_SERVER['SERVER_NAME'] ) && strstr ( $_SERVER['SERVER_NAME'], $this->domain ) !== false ) // don't use when testing
      $dk['i'] = '@' . $_SERVER['SERVER_NAME']; //optional
    $dk['q'] = 'dns/txt'; // optional, dns/txt is the default if missing
    $dk['l'] = strlen ( $body ); //optional
    $dk['t'] = time ( ); // timestamp of signature, optional
    if ( isset ( $this->valid_time ) )
      $dk['x'] = $this->valid_time; // unix timestamp expiriation of signature, optional
    $dk['h'] = implode ( ':', array_keys ( $headers ) ); 
    $dk['bh'] = base64_encode ( hash ( 'sha256', $body , true ) );
    $value = '';
    foreach ( $dk as $key => $val )
      $value .= "$key=$val; ";
    $value .= 'b=';
    $tosign = $b . 'DKIM-Signature: ' . $value;
    openssl_sign ( $tosign, $sig, $this->schedule_private_key, $this->scheduling_dkim_algo );
    $this->tosign = $tosign;
    $value .= base64_encode ( $sig );
    return $value;
  }

  /**
  * send request to remote server
  */ 
  function sendRequest ( $address, $type, $data )
  {
    $this->domain = $address;
    if ( ! $this->getServer ( ) )
    {
      $request->DoResponse( 403, translate('Server not found') );
      return false;
    }
    if ( ! $this->getCapabilities ( ) )
    {
      $request->DoResponse( 403, translate('Server not found') );
      return false;
    }
    // find names on 'urn:ietf:params:xml:ns:ischedule::supported-scheduling-message-set' comp name='vevent'  method name='request' 
  }

  /**
  * parses and validates DK header 
  *
  * @param string $sig the value of the DKIM-Signature header
  */
  function parseDKIM ( $sig )
  {

    $this->failed = true;
    $tags = preg_split ( '/;[\s\t]/', $sig );
    foreach ( $tags as $v )
    {
      list($key,$value) = preg_split ( '/=/', $v, 2 );
      $dkim[$key] = $value;
    }
    // the canonicalization method is currently undefined as of draft-01 of the iSchedule spec
    // but it does define the value, it should be simple-http.  RFC4871 also defines two methods
    // simple and relaxed, simple is probably the same as simple http
    // relaxed allows for header case folding and whitespace folding, see section 3.4.4 of RFC4871
    if ( ! preg_match ( '{(simple|simple-http|relaxed)(/(simple|simple-http|relaxed))?}', $dkim['c'], $matches ) ) // canonicalization method
      return 'bad canonicalization:' . $dkim['c'] ;
    if ( count ( $matches ) > 2 )
      $this->body_cannon = $matches[2];
    else
      $this->body_cannon = $matches[1];
    $this->header_cannon = $matches[1];
    // signing algorythm REQUIRED
    if ( $dkim['a'] != 'rsa-sha1' && $dkim['a'] != 'rsa-sha256' ) // we only support the minimum required
      return 'bad signing algorythm:' . $dkim['a'] ;
    // query method to retrieve public key, could/should we add https to the spec?  REQUIRED 
    if ( $dkim['q'] != 'dns/txt' ) 
      return 'bad query method';
    // domain of the signing entity REQUIRED
    if ( ! isset ( $dkim['d'] ) )  
      return 'missing signing domain';
    $this->remote_server = $dkim['d'];
    // identity of signing AGENT, OPTIONAL
    if ( isset ( $dkim['i'] ) )    
    {
      // if present, domain of the signing agent must be a match or a subdomain of the signing domain
      if ( ! stristr ( $dkim['i'], $dkim['d'] ) ) // RFC4871 does not specify a case match requirement
        return 'signing domain mismatch';
      // grab the local part of the signing agent if it's an email address
      if ( strstr ( $dkim [ 'i' ], '@' ) )
        $this->remote_user = substr ( $dkim [ 'i' ], 0, strpos ( $dkim [ 'i' ], '@' ) - 1 );
    }
    // selector used to retrieve public key REQUIRED
    if ( ! isset ( $dkim['s'] ) )  
      return 'missing selector';
    $this->remote_selector = $dkim['s'];
    // signed header fields, colon seperated  REQUIRED
    if ( ! isset ( $dkim['h'] ) )  
      return 'missing list of signed headers';
    $this->signed_headers = preg_split ( '/:/', $dkim['h'] );
    
    foreach ( $this->signed_headers as $h )
      if ( strtolower ( $h ) == 'dkim-signature' )
        return "DKIM Signature is NOT allowed in signed header fields per RFC4871";
    // body hash REQUIRED
    if ( ! isset ( $dkim['bh'] ) ) 
      return 'missing body signature';
    // signed header hash REQUIRED
    if ( ! isset ( $dkim['b'] ) )  
      return 'missing signature in b field';
    // length of body used for signing
    if ( isset ( $dkim['l'] ) ) 
      $this->signed_length = $dkim['l'];
    $this->failed = false;
    $this->DKSig = $dkim;
    return true;
  } 
  
  /**
  * split up a mailto uri into domain and user components
  */ 
  function parseURI ( $uri )
  {
    if ( preg_match ( '/^mailto:([^@]+)@([^\s\t\n]+)/', $uri, $matches ) )
    {
      $this->remote_user = $matches[1];
      $this->domain = $matches[2];
    }
    else
      return false;
  }

  /**
  * verifies parsed DKIM header is valid for current message with a signature from the public key in DNS 
  */ 
  function verifySignature ( )
  {
    global $request,$c;
    $this->failed = true;
    $signed = '';
    foreach ( $this->signed_headers as $h )
      if ( isset ( $_SERVER['HTTP_' . strtoupper ( strtr ( $h, '-', '_' ) ) ] ) )
        $signed .= "$h: " . $_SERVER['HTTP_' . strtoupper ( strtr ( $h, '-', '_' ) ) ] . "\r\n";
      else
        $signed .= "$h: " . $_SERVER[ strtoupper ( strtr ( $h, '-', '_' ) ) ] . "\r\n";
    if ( ! isset ( $_SERVER['HTTP_ORIGINATOR'] ) || stripos ( $signed, 'Originator' ) === false ) //required header, must be signed
      return "missing Originator";
    if ( ! isset ( $_SERVER['HTTP_RECIPIENT'] ) || stripos ( $signed, 'Recipient' ) === false ) //required header, must be signed 
      return "missing Recipient";
    if ( ! isset ( $_SERVER['HTTP_ISCHEDULE_VERSION'] ) || $_SERVER['HTTP_ISCHEDULE_VERSION'] != '1' ) //required header and we only speak version 1 for now 
      return "missing or mismatch ischedule-version header";
    $body = $request->raw_post;
    if ( ! isset ( $this->signed_length ) )
      $this->signed_length = strlen ( $body );
    else
      $body = substr ( $body, 0, $this->signed_length );
    if ( isset ( $this->remote_user_rule ) )
      if ( $this->remote_user_rule != '*' && ! stristr ( $this->remote_user, $this->remote_user_rule ) )
        return "remote user rule failure";
    $hash_algo = preg_replace ( '/^.*(sha[1256]+).*/','$1', $this->DKSig['a'] );
    $body_hash = base64_encode ( hash ( $hash_algo, $body , true ) );
    if ( $this->DKSig['bh'] != $body_hash )
      return "body hash mismatch";
    $sig = $_SERVER['HTTP_DKIM_SIGNATURE'];
    $sig = preg_replace ( '/ b=[^;\s\n\t]+/', ' b=', $sig );
    $signed .= 'DKIM-Signature: ' . $sig;
    $verify = openssl_verify ( $signed, base64_decode ( $this->DKSig['b'] ), $this->remote_public_key, $hash_algo );
    if (  $verify != 1 )
      return "signature verification failed";
    $this->failed = false;
    return true;
  }

  /**
  * checks that current request has a valid DKIM signature signed by a currently valid key from DNS
  */ 
  function validateRequest ( )
  {
    global $request;
    if ( isset ( $_SERVER['HTTP_DKIM_SIGNATURE'] ) )
      $sig = $_SERVER['HTTP_DKIM_SIGNATURE'];
    else
    {
      $request->DoResponse( 403, translate('DKIM signature missing') );
      return false;
    }
    
    $err = $this->parseDKIM ( $sig );
    if ( $err !== true || $this->failed )
      $request->DoResponse( 403, translate('DKIM signature invalid ' ) . "\n" . $err . "\n" . $sig );
    if ( ! $this->getTxt () || $this->failed )
      $request->DoResponse( 403, translate('DKIM signature validation failed(DNS ERROR)') );
    if ( ! $this->parseTxt () || $this->failed )
      $request->DoResponse( 403, translate('DKIM signature validation failed(KEY Parse ERROR)') );
    if ( ! $this->validateKey () || $this->failed )
      $request->DoResponse( 403, translate('DKIM signature validation failed(KEY Validation ERROR)') );
    $err = $this->verifySignature ();
    if ( $err !== true || $this->failed )
      $request->DoResponse( 403, translate('DKIM signature validation failed(Signature verification ERROR)') . $this->verifySignature() );
    return true;
  }
}

$d = new iSchedule ();
//if ( $d->validateRequest ( ) )
//{
  //include ( 'caldav-POST.php' );
  // @todo handle request.
//}
