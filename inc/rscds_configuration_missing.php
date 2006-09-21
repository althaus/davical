<?php
include("page-header.php");

  echo <<<EOBODY
<h1>RSCDS Not Configured</h1>
<h2>The Bad News</h2>
<p>There is no configuration file present in <b>/etc/rscds/$_SERVER[SERVER_NAME]-conf.php</b> so
   your installation is not fully set up.</p>
<h2>The Good News</h2>
<p>Well, you're seeing this! At least you have RSCDS <i>installed</i> :-) You also have Apache and PHP working
   and so really you are well on the road to success!</p>
<h2>The Dubious News</h2>
<p>You could try and <a href="http://$_SERVER[SERVER_NAME]/docs/rscds/configuring.html">click here</a> and
   see if that enlightens you at all.  Odds are it's a fairly broken link, but it might work sooner
   or later so keep downloading new versions and trying again.  Or make some guesses.  Or bug Andrew :-)</p>
<h2>The Really Basic Help</h2>
<p>The configuration file should look something like this:</p>
<pre>
&lt;?php
//  \$c->domainname  = 'rscds.example.com';
//  \$c->sysabbr     = 'rscds';
//  \$c->system_name = 'Really Simple CalDAV Store';

  \$c->admin_email  = 'admin@example.com';
  \$c->pg_connect[] = 'dbname=rscds port=5432 user=general';

?&gt;
</pre>
<p>The only really <em>essential</em> thing there is that connect string for the database, although
configuring someone for the admin e-mail is a really good idea.</p>
EOBODY;

include("page-footer.php");
?>