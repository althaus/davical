<?php

include("../inc/always.php");
include("DAViCalSession.php");
$session->LoginRequired();

include("interactive-page.php");
include("page-header.php");

  echo <<<EOBODY
<h1>Setup</h1>
<p>Currently this page does nothing.  Suggestions or patches to make it do something
useful will be gratefully received.

<h2>Configuring Calendar Clients for DAViCal</h2>
<p>The <a href="http://rscds.sourceforge.net/clients.php">DAViCal client setup page on sourceforge</a> have information on how
to configure Evolution, Sunbird, Lightning and Mulberry to use remotely hosted calendars.</p>
<p>The administrative interface has no facility for viewing or modifying calendar data.</p>

<h2>Configuring DAViCal</h2>
<p>If you can read this then things must be mostly working already.</p>
<p>The <a href="http://rscds.sourceforge.net/installation.php">DAViCal installation page</a> on sourceforge has
some further information on how to install and configure this application.</p>
EOBODY;

include("page-footer.php");
