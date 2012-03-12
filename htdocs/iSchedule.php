<?php

include("./always.php");
include("DAViCalSession.php");
$session->LoginRequired('Admin');

include("interactive-page.php");
require_once("AwlQuery.php");
require_once('iSchedule.php');

 $page_elements = array();
 $page_elements[] = <<<EOBODY
<h1>iSchedule Configuration</h1>
<p>iSchedule allows caldav servers to communicate directly with each other, bypassing the need to send invitations via email, for scheduled events where attendees are using different servers or providers.  Additionally it enables freebusy lookups for remote attendees.  Events and ToDos received via iSchedule will show up in the users scheduling inbox.
<br>&nbsp;
</p>
<p>The <a href="http://wiki.davical.org/w/iSchedule_configuration">iSchedule configuration</a> requires a few DNS entries. DNS SRV record(s) will need to be created for all domains you wish to accept requests for, these are the domain portion of email address on Principal records in DAViCal examples are listed below for domains found in your database. At least 1 public key must also be published if you wish to send requests from this server.</p>
EOBODY;

$status = '<h2>Status</h2>';
if (!isset($c->scheduling_dkim_domain)) {
  $status .= <<<EOBODY
<div class='error'>ERROR</div>
<p>scheduling_dkim_domain not set</p>
EOBODY;
}
elseif ( $c->scheduling_dkim_domain != $_SERVER['SERVER_NAME'] ) {
  $status .= <<<EOBODY
<div class='error'>WARNING</div>
<p>scheduling_dkim_domain does not match server name</p></h3>
EOBODY;
}
else {
  $status .= '<div >' . checkiSchedule() . '</div>';
}
$page_elements[] = $status;

function checkiSchedule () {
  global $c;
  $ret = '';

  $s = new iSchedule ();
  $s->domain = $c->scheduling_dkim_domain;
  if (!$s->getServer())
    $ret .= '<p>SRV record missing for '. $s->domain .' or DNS failure, the domain you are going to send events from should have an SRV record</p>' ;
  if ($s->remote_server != $c->scheduling_dkim_domain)
    $ret .= '<p>SRV record for '.$s->domain . ' points to wrong domain: ' . $s->remote_server . ' instead of ' . $c->scheduling_dkim_domain  .'</p>';
  $s->remote_server = $c->scheduling_dkim_domain;
  $s->remote_selector = $c->scheduling_dkim_selector;
  if (!$s->getTxt()) {
    if (isset($c->schedule_private_key))
      $ret .= '<p>TXT record missing for '.$s->remote_selector . '._domainkey.' . $s->domain .' or DNS failure, Private RSA key is configured</p>' ;
    else {
      $keys = generateKeys();
      $config = '<p>please add the following section to your DAViCal configuration file<pre>$c->schedule_private_key = &lt;&lt;&lt;ENDOFKEY' ."\n";
      $config .= $keys['private']; //implode ("\n", str_split ( base64_encode ( $keys['private'] ), 64 ));
      $config .= "ENDOFKEY\n</pre>";
      $config .= "<br/> and create a DNS TXT record for <b>" . $c->scheduling_dkim_selector . "._domainkey." . $c->scheduling_dkim_domain ;
      $config .= "</b> that contains:<pre>";
      $config .= "k=rsa; t=s; p=" . preg_replace('/-----(BEGIN|END) PUBLIC KEY-----\n/','',$keys['public']);
      $config .= '</pre></p>';
      $ret .= $config;
    }
  }
  if ( ! $s->parseTxt() )
    $ret .= '<p>TXT record corrupt for '. $s->remote_selector .'._domainkey.'. $s->domain .' or DNS failure</p>' ;
  else if ( $ret == '' )
    $ret = '<p>iSchedule OK</p>';
  return $ret;
}

function generateKeys () {
  $config = array('private_key_bits' => 512, 'private_key_type' => OPENSSL_KEYTYPE_RSA);
  $newKey = openssl_pkey_new($config);
  if ( $newKey !== false ) {
    openssl_pkey_export($newKey,$privateKey);
    $publicKey=openssl_pkey_get_details($newKey);
    $publicKey=$publicKey['key'];
    return Array('private' => $privateKey, 'public' => $publicKey);
  }
  return false;
}

include("classEditor.php");
include("classBrowser.php");

function SRVOk ( $value, $name, $row ) {
  global $BrowserCurrentRow;
  if ( $BrowserCurrentRow->domain = '' )
    return ''; // skip empty rows
  $s = new iSchedule();
  $s->domain = $BrowserCurrentRow->domain; 
  return translate( ( $s->getServer()?'OK': SRVFormat ( $s->domain ) ) );
}

function SRVFormat ( $domain ) {
  global $c;
  switch ( @$_REQUEST['srv_format'] )
  {
    case 'dnsmasq':
      return 'srv_host=_ischedules._tcp.' . $domain .','. ($c->scheduling_dkim_domain?$c->scheduling_dkim_domain:$_SERVER['SERVER_NAME']) .','. $_SERVER['SERVER_PORT'] ;
    case 'bind': //_http._tcp.example.com. IN      SRV 0    5      80   www.example.com.
      return '_ischedules._tcp.' . $domain .'. IN SRV  0 5 ' . $_SERVER['SERVER_PORT'] .' '. ($c->scheduling_dkim_domain?$c->scheduling_dkim_domain:$_SERVER['SERVER_NAME']) ;
    default:
      return '_ischedules._tcp.' . $domain .'  '. ($c->scheduling_dkim_domain?$c->scheduling_dkim_domain:$_SERVER['SERVER_NAME']) .'  '. $_SERVER['SERVER_PORT'] ;
  }
}

$browser = new Browser(translate('iSchedule Domains'));
$browser->AddColumn( "domain", translate('Domain'),'left','' );
// function AddColumn( $field, $header="", $align="", $format="", $sql="", $class="", $datatype="", $hook=null ) {
$browser->AddColumn( "srvok", translate('SRV Record'),'right','',"''",'','','SRVOk' );
$browser->SetJoins( "usr " );
$browser->SetWhere( " email is not null and email <> ''" );

$browser->SetDistinct( " split_part(email,'@',2) as " );

$sql = "select distinct split_part(email,'@',2) as domain  from usr where email is not null and email <> ''";


$page_elements[] = $browser;

$c->stylesheets[] = 'css/edit.css';

include("page-header.php");

/**
* Page elements could be an array of viewers, browsers or something else
* that supports the Render() method... or a non-object which we assume is
* just a string of text that we echo.
*/
$heading_level = null;
foreach( $page_elements AS $k => $page_element ) {
  if ( is_object($page_element) ) {
    echo $page_element->Render($heading_level);
    $heading_level = 'h2';
  }
  else {
    echo $page_element;
  }
}

if (function_exists("post_render_function")) {
  post_render_function();
}

include("page-footer.php");
