<h1>Cross-client Interoperability Considerations</h1>
<p>If you intend to have users accessing the DAViCal CalDAV Server with more than one client
then you should attempt to structure the URLs which they use to access the system in the way
that Mulberry does it.</p>
<p>Basically, Mulberry breaks the URL into three parts:</p>

<ul>
<li>The host name</li>
<li>The root path</li>
<li>Calendar namespace</li>
</ul>

<p>The host name is, of course, up to you.  The 'root path' should be 
<code>/caldav.php/</code> and anything following that is the calendar 
namespace.</p>

<p>Within the calendar namespace DAViCal uses the first element of the 
path as the user or 'princpal' name, so that a client connecting at the 
root path can see all of the (accessible) users and resources available 
to them (Mulberry displays this hierarchy) with any calendars below that.</p>

<p>This means that in Evolution, Lightning and other software wanting a
'calendar' URL you should specify a URL which is something like:</p>
<pre>
http://calendar.example.net/caldav.php/username/calendar/
</pre>

<p>DAViCal creates two collections automatically when a user is created.  In
recent versions these are called 'calendar' and 'addressbook'.  Some software
also makes it easy to create more calendars and addressbooks, or you can create
more through DAViCal's web interface, also.</p>

<p>In older versions of DAViCal (pre 0.9.9.5) the default calendar was named 'home'
and there was no default addressbook.</p> 
