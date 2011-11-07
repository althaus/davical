<h1>Other Client Software</h1>

<p>I would love to have more client software available to test DAViCal
against, but so far these are the only ones I have access to.</p>

<p>If you want to point me at more free software that supports CalDAV, or
send me free copies of such proprietary software, then I will add it to
the list as well as make DAViCal work with it.</p>

<p>In the general CalDAV terminology, client software will want to know
several facts about the CalDAV server.  Some (like iCal and iOS) will try
and discover these facts for themselves, and others (like Lightning and
Evolution) will require you to enter some information.  When they ask for
that information they will be asking for the following things:</p>
<ol>
<li>Where is the user's "home" collection?</li>
<li>Where is the user's "calendar" collection?</li>
<li>What is the server's domain name</li>
</ol>

<p>Typically the answers, in DAViCal's case, are:</p>
<ol>
<li>.../caldav.php/username/</li>
<li>.../caldav.php/username/calendar/ (although in older versions the default calendar was called 'home' rather than 'calendar')</li>
<li>I can't help here - whatever you called it, I guess!</li>
</ol>

<p>There could well be a wider range of information about many and varied client
 software on the <a href="http://wiki.davical.org/" title="The DAViCal CalDAV Server Wiki">DAViCal Wiki</a> as well.