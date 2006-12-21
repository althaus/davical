<?php
 $title = "RSCDS Home";
 include("inc/page-header.php");
?>
<h1>Background</h1>
<p>The CalDAV specification has been under development for a few years now, and at the same time we
have seen increasing pressure from people and organisations in the open source world to provide a
solution to their shared calendaring problems.
</p>
<p>In evaluating the possibilities for shared calendaring, there are a number of possible approaches, but
we have elected to follow the path of implementing CalDAV because we believe it is a good specification
and that it will in due course gain client implementations and provide the richest user
experience through those client implementations.</p>

<h1>Goals</h1>
<p>CalDAV is a client-server protocol specific to managing and reporting on <em>collections</em> of <em>calendar resources</em>.</p>
<p>As such, our intentions in developing this application are as follows:</p>
<ul>
<li>Simplicity of Prerequisites</li>
<li>Simplicity of Setup</li>
<li>Simplicity of Operation</li>
<li>Web-based Administration</li>
</ul>

<h2>Simplicity of Prerequisites</h2>
<p>We have chosen to write this in PHP because we believe that PHP is a widely available web scripting language.</p>
<p>We have chosen to use the Apache web server because it is also widely available.  This is not necessarily a requirement,
but no testing has been undertaken in other PHP environments to date.</p>
<p>We have chosen to use the PostgreSQL database, because it is a free, open-source database, which operates on a very wide set of
operating environments, and which is <em>fully</em> ACID compliant.</p>

<h2>Simplicity of Setup</h2>
<p>For the greatest ease use you should consider installing RSCDS on the <a href="http://www.debian.org/">Debian GNU/Linux</a>
distribution from the readily available, signed packages.</p>
<p>We expect to increase the level of automation and simplicity for the Debian target release in particular, although other
distributions might also become easier at the same time. We do expect slightly greater installation complexity in the first
few releases as we come to understand the particular problems people experience.</p>

<h2>Simplicity of Operation</h2>
<p>In general RSCDS should not need significant maintenance to keep it operating.</p>
<p>Administrative functionality will be kept as simple as possible, within the target of supporting
organisations of up to several hundred staff.</p>
<p>This is called a <em>Store</em> rather than a <em>Server</em> because the server-side smarts are intended to be
minimised to support CalDAV only in a manner sufficient to inter-operate with clients, and with the focus primarily
on the storage of calendar resources.</p>

<h2>Web-based Administration</h2>
<p>General administration of the system should be through a web-based application.</p>
<p>Calendars will not be made available in a web-based view in initial releases.  It is unlikely that calendars will ever be
maintainable through a web-based client, although the server should support the use of web-based client software which
works using the CalDAV protocol.</p>

<h1>Credits</h1>
<p>The Really Simple CalDAV Store was conceived and written by <a href="http://andrew.mcmillan.net.nz/">Andrew McMillan</a>.</p>
<p>Translations of the administration interface have been done by:</p>
<ul>
<li>Lorena Paoletti (Spanish)</li>
<li>Cristina Radalescu (German)</li>
<li>Guillaume Rosquin (French)</li>
<li>Nick Khazov (Russian)</li>
<li>Eelco Maljaars (Dutch)</li>
</ul>
<p>Other contributors:</p>
<ul>
<li>Maxime Delorme (CSS for Administration Pages)</li>
</ul>

<h1>Your Name Here!</h1>
<p>If you are interested in helping, there are several areas where I need help at the moment:</p>
<i>
<li>The project needs a better name - feel free to suggest one!</li>
<li>We need more documentation</li>
<li>We need to find more CalDAV-capable calendar clients to interoperate with</li>
<li>We would love you to write about your experiences in the project Wiki.</li>
</ul>

<?php
 include("inc/page-footer.php");
?>