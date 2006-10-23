<?php
require_once("always.php");
require_once("RSCDSSession.php");
$session->LoginRequired();

require_once("interactive-page.php");

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
<li><span class="prompt">URL:</span>caldav://server.domain.name/caldav.php/$username/</li>
<li><span class="prompt">Use SSL:</span>if your server is using SSL you should check this, but there may be bugs in Evolution's handling of SSL.</li>
<li><span class="prompt">Username:</span>$username</li>
</ol>
</p>
<p>If you have problems with Evolution, you will need to quit evolution, remove the cache file which will be in ~/.evolution/cache/calendar/ and
restart.  If you still have problems try doing that, but killing evolution-data-server in addition.
</p>
<p>Sometimes evolution writes error messages into the cache file, so if you have ongoing problems you may want to
take a look inside that.</p>
<p>There are some quirks with Evolution's handling of CalDAV too, so perhaps take a look at the following
bugs:
<ul>
<li><a href="http://bugzilla.gnome.org/show_bug.cgi?id=355659">New appointments disappear for 1 minute, and then reappear</a></li>
<li><a href="http://bugzilla.gnome.org/show_bug.cgi?id=354855">Support Response with Relative URLs</a></li>
</ul>
</p>
EOBODY;

include("page-footer.php");

?>