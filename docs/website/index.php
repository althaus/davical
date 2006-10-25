<?php
 $title = "RSCDS Home";
 include("inc/page-header.php");
?>
<h1>Background</h1>
<p>The CalDAV specification has been under development for a few years now, and at the same time I
have seen increasing pressure from clients to provide a solution to their shared calendaring problems.
</p>
<p>In evaluating the possibilities for shared calendaring, there are a number of possible approaches, but
I have elected to follow the path of implementing CalDAV because I believe it is a good specification
and that it will in due course gain client implementations and provide the richest user
experience through those client implementations.</p>

<h1>Goals</h1>
<p>CalDAV is a client-server protocol specific to managing and reporting on <em>collections</em> of <em>calendar resources</em>.</p>
<p>As such, my intentions in developing this application are as follows:
<ul>
<li>Simplicity of Prerequisites</li>
<li>Simplicity of Setup</li>
<li>Simplicity of Operation</li>
<li>Web-based Administration</li>
</ul>
</p>

<h2>Simplicity of Prerequisites</h2>
<p>I have chosen to write this in PHP because I believe that PHP is a widely available web scripting language.</p>
<p>I have chosen to use the Apache web server because it is also widely available.  This is not necessarily a requirement,
but I do not have other PHP environments available to me at this time.</p>
<p>I have chosen to use the PostgreSQL database, because it is a free, open-source database, which operates on a very wide set of
operating environments, and which is <em>fully</em> ACID compliant.</p>

<h2>Simplicity of Setup</h2>
<p>I use the <a href="http://www.debian.org/">Debian GNU/Linux</a> distribution, so at this stage I have made Debian packages available.  If
I find willing people I will make packages available in other forms.</p>
<p>This goal is not expected to be achieved in the first few releases.</p>

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


<h1>Your Name Here!</h1>
<p>If you are interested in helping, there are several areas where I need help at the moment:
<ul>
<li>The project needs a better name - feel free to suggest one!</li>
<li>We need more documentation</li>
<li>The graphic and UI design for the web-based administration is teh suck (but it is basically functional :-)</li>
<li>We need more documentation</li>
<li>I need to find more CalDAV-capable calendar clients to interoperate with</li>
</ul>
</p>

<?php
 include("inc/page-footer.php");
?>
