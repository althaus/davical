<?php
require_once("always.php");
require_once("RSCDSSession.php");
$session->LoginRequired();

$c->title = "Really Simple CalDAV Store - Configuration Help";
include("page-header.php");

$username = ( $session->user_no > 0 ? $session->username : "username" );

echo <<<EOBODY
<h1>$c->title</h1>
<h2>Evolution</h2>
<p>
<ol>
<li><span class="prompt">Type:</span>CalDAV</li>
<li><span class="prompt">Name:</span>Give the calendar a local name</li>
<li><span class="prompt">URL:</span>caldav://server.domain.name/caldav.php/$username</li>
<li><span class="prompt">Use SSL:</span>if your server is using SSL you should check this</li>
<li><span class="prompt">Username:</span>$username</li>
</ol>
</p>
EOBODY;

include("page-header.php");

?>