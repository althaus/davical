<?php
 $title = "DAViCal Home";
 include("inc/page-header.php");
?>
<h1>About DAViCal</h1>
<p>DAViCal is a server for calendar sharing.  It is an implementation of the CalDAV protocol which is designed
for storing calendaring resources (in iCalendar format) on a remote shared server. </p>
<p>An increasing number of calendar clients support
the maintenance of shared remote calendars through CalDAV including Mozilla Calendar
(Sunbird/Lightning), Evolution, Mulberry, Chandler, and various other closed-source products
such as Apple's iCal and iPhone. </p>
<h2>Features</h2>
DAViCal:<ul>
<li>is <a href="http://www.gnu.org/philosophy/open-source-misses-the-point.html">Free Software</a> licensed under the <a href="http://www.gnu.org/licenses/gpl.html">General Public License</a>.</li>
<li>uses an SQL database for storage of event data</li>
<li>supports backward-compatible access via WebDAV in read-only or read-write mode (not recommended)</li>
<li>is committed to inter-operation with the widest possible CalDAV client software.</li>
</ul>

<p>DAViCal supports basic delegation of read/write access among calendar users, multiple users or clients reading
and writing the same calendar entries over time, and scheduling of meetings with free/busy time displayed. </p>

<h1>Overview of Installation and Use</h1>
<h2>Prerequisites</h2>
To install and use DAViCal you will need:<ul>
<li>a PostgreSQL database server</li>
<li>a web server which can run PHP version 5</li>
</ul>
<p>We use <a href="http://www.debian.org/">Debian GNU/Linux</a> for this, but other people use various different
versions of Linux, FreeBSD, Mac OSX and even Microsoft Windows.  We definitely do not recommend using Windows
for this unless you are completely hobbled by silly internal IT policies and have to do so.</p>

<h2>Getting DAViCal and Installing DAViCal</h2>
<p>You can download DAViCal from <a href="http://debian.mcmillan.net.nz/">http://debian.mcmillan.net.nz/</a></p>
<p>Start looking on the <a href="installation.php">DAViCal Installation Page</a> for more places to download, and
detailed instructions as to what to do when you have it.</p>
<p>There is a lot of information on the wiki about <a href="">DAViCal configuration settings</a> but in most cases the configuration
will need very few of these settings.</p>

<h2>Using DAViCal</h2>
<p>Read here about <a href="clients.php">how to configure various CalDAV Clients with DAViCal</a>. There is also
a page on the wiki which will sometimes have newer information.</p>

<h2>Getting Help</h2>
<p>Specifically: help on DAViCal!</p>
<p>The <em>best</em> place to go for help on DAViCal is the <a href="http://wiki.davical.org/">DAViCal Wiki</a>.</p>
<p>If you can't find your answer there, then the IRC channel #davical on <a href="http://irc.oftc.net/">irc.oftc.net</a> is
a great next port of call.  Many problems can be solved quickly with a short on-line chat.</p>
<p>Almost as good as the IRC channel is the <a href="https://lists.sourceforge.net/mailman/listinfo/rscds-general">DAViCal General Mailing List</a>.</p>
Other places to try include:<ul>
<li>The sourceforge forums.</li>
<li>Googling your problem.</li>
</ul>
<p>As a last resort, or in case your organisation likes that sort of thing, paid commercial support is available
through the author's company <a href="http://www.morphoss.com/products/davical/support">Morphoss</a>.</p>

<h1>Credits</h1>
<p>DAViCal CalDAV Server was conceived and written by <a href="http://andrew.mcmillan.net.nz/">Andrew McMillan</a>.</p>
Translations of the administration interface have been done by:<ul>
<li>Lorena Paoletti (Spanish)</li>
<li>Cristina Radalescu (German)</li>
<li>Guillaume Rosquin &amp; Maxime Delorme (French)</li>
<li>Nick Khazov (Russian)</li>
<li>Eelco Maljaars (Dutch)</li>
</ul>
Other contributors:<ul>
<li>Maxime Delorme (CSS, LDAP, SyncML, French translations)</li>
<li>Andrew Ruthven (Various enhancements)</li>
</ul>

<h1>Contributing to DAViCal</h1>
<p>We welcome contributions from interested people.  You don't need to be able to write code - there are lots of
small tasks around the project that can be done.
CalDAV server:</p>
Here are some things you could do that will help us to concentrate on making DAViCal a better:<ul>
<li>writing documentation</li>
<li>helping people on IRC, on the mailing list or sf.net forums</li>
<li>translating the DAViCal interface to another language</li>
<li>managing the release process</li>
<li>reviewing and tidying the Wiki updates</li>
<li>writing and reviewing patches</li>
<li>designing future functionality</li>
<li>thinking of more interesting ways to contribute to DAViCal!</li>
</ul>

<p>Can you think of more?</p>

<?php
 include("inc/page-footer.php");
