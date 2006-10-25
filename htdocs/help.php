<?php
require_once("always.php");
require_once("RSCDSSession.php");
$session->LoginRequired();

require_once("interactive-page.php");

$c->title = "Really Simple CalDAV Store - Configuration Help";
include("page-header.php");

?>
<h1>Help</h1>
<p>For initial help you should visit the <a href="http://rscds.sourceforge.net/">RSCDS Home Page</a>.  If you can't
find the answers there, then you should post your problem in the RSCDS forums on Sourceforge itself.</p>
<?php

include("page-footer.php");

?>