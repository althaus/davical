<h1>Evolution</h1>
<p><a href="http://www.gnome.org/projects/evolution/">Evolution</a> is available in most Linux distributions.  The CalDAV support was supposedly written
in a frenzy of hacking one day when the draft specification was at around revision 8.  As a result there
was little in the way of a repository available to test against until recently.</p>

<ol>
<li>Select "File" then "New" then "Calendar" from the menus.</li>
<li>Choose a type of "CalDAV", enter a name, and a URL such as <code>caldav://server.domain.name/caldav.php/username/home/</code>, enter your user name for RSCDS and click "OK".<img src="clients/Evolution-dialog1.png" /> <br />&nbsp;</li>
<li>You should now be prompted for a password for that username.  Enter the password and your calendar should now show.</li>
</ol>
<p>If you have problems with Evolution, you will need to quit evolution, remove the cache file which will be in ~/.evolution/cache/calendar/ and
restart.  If you still have problems try doing that, but killing evolution-data-server in addition.
</p>
<p>Sometimes evolution writes error messages into the cache file, so if you have ongoing problems you may want to
take a look inside that.</p>
<p>There are some quirks with Evolution's handling of CalDAV too, so perhaps take a look at the following
bugs:</p>
<ul>
<li><a href="http://bugzilla.gnome.org/show_bug.cgi?id=355659">New appointments disappear for 1 minute, and then reappear</a></li>
<li><a href="http://bugzilla.gnome.org/show_bug.cgi?id=354855">Support Response with Relative URLs</a></li>
</ul>
<p>There may also be bugs in Evolution's handling of SSL with CalDAV - I couldn't get it to work reliably.</p>
<p>Hopefully those will be fixed before too long...</p>
