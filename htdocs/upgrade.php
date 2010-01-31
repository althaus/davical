<?php
if ( $_SERVER['REQUEST_METHOD'] != "GET" && $_SERVER['REQUEST_METHOD'] != "POST" ) {
  /**
  * If the request is not a GET or POST then they must really want caldav.php!
  */
  include("./caldav.php");
  exit;  // Not that it should return from that!
}

include("../inc/always.php");
include("DAViCalSession.php");
$session->LoginRequired();

include("interactive-page.php");
include("page-header.php");

  echo <<<EOBODY
<h1>Upgrade</h1>
<p>Currently this page does nothing.  Suggestions or patches to make it do something
useful will be gratefully received.
<br>&nbsp;
</p>
<h2>Upgrading DAViCal Versions</h2>
<p>The <a href="http://wiki.davical.org/w/Update-davical-database">update-davical-database</a> should be run
manually after upgrading the software to a new version of DAViCal.</p>

<p>In due course this program will implement the functionality which is currently contained in that
script, but until then I'm afraid you do need to run it.
EOBODY;

include("page-footer.php");
