<h1>Cross-client Interoperability Considerations</h1>
<p>If you intend to have users accessing the Really Simple CalDAV Store with more than one client
then you should attempt to structure the URLs which they use to access the system in the way
that Mulberry does it.</p>
<p>Basically, Mulberry breaks the URL into three parts:</p>
<ul>
<li>The host name</li>
<li>The root path</li>
<li>Calendar namespace</li>
</ul>
<p>The host name is, of course, up to you.  The 'root path' should be <code>/caldav.php/</code> and anything following that is the calendar namespace.</p>
<p>Within the calendar namespace RSCDS uses the first element of the path as the user or resource name, so that a client connecting at the root path
can see all of the (accessible) users and resources available to them (Mulberry displays this hierarchy) with any calendars below that.</p>
<p>Effectively this means that in Evolution, Sunbird and Lightning you should really specify a calendar URL which is something like:</p>
<pre>
caldav://calendar.example.net/caldav.php/username/home/
</pre>
<p>Then, when more calendar client software sees it as useful to be able to browse that hierarchy, you won't be up for any heavy database manipulation.</p>
<p>I may well enforce this standard in some way before release 1.0, as well as auto-creating the <code>collection</code> records when Evolution, Lightning
or Sunbird attempt to store to a non-existent collection.</p>
