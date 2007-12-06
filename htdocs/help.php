<?php
require_once("../inc/always.php");
require_once("RSCDSSession.php");
$session->LoginRequired();

require_once("interactive-page.php");

$c->page_title = "DAViCal CalDAV Server - Configuration Help";
include("page-header.php");

?>
<h1>Help</h1>
<p>For initial help you should visit the <a href="http://rscds.sourceforge.net/">DAViCal Home Page</a>.  If you can't
find the answers there, visit the #davical IRC channel on irc.oftc.net, send a question to the mailing list or
post your problem in the DAViCal forums on Sourceforge itself.</p>
<?php

include("page-footer.php");

?>