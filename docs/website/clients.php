<?php
 $title = "RSCDS Home";
 include("inc/page-header.php");
?>
<h1>Evolution</h1>
<p>Novell Evolution is available in most Linux distributions.  The CalDAV support was supposedly written
in a frenzy of hacking one day when the draft specification was at around revision 8.  As a result there
was little in the way of a client available to test against until recently.</p>
<ol>
<li><span class="prompt">Type:</span>CalDAV</li>
<li><span class="prompt">Name:</span>Give the calendar a local name</li>
<li><span class="prompt">URL:</span>caldav://server.domain.name/caldav.php/$username/</li>
<li><span class="prompt">Use SSL:</span>if your server is using SSL you should check this, but there may be bugs in Evolution's handling of SSL.</li>
<li><span class="prompt">Username:</span>$username</li>
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
<p>Hopefully those will be fixed before too long...</p>

<h1>Sunbird / Lightning</h1>
<p>The Mozilla calendar project offers their calendar under two different names: <em>Sunbird</em> is a standalone calendar
application, and <em>Lightning</em> is a Thunderbird extension.  The two are essentially the same, as far as RSCDS is
concerned, and these instructions should work for either of them.</p>
<ol>
<li>Select "New Calendar" from the "File" menu.</li>
<li>Choose "On the Network" (click "Next")</li>
<li>Choose a format of "CalDAV" and enter a URL like: "caldav://calendar.example.net/caldav.php/username/" (click "Next")</li>
<li>Give the calendar an appropriate display name, and choose a colour for events on this calendar. (click "Next")</li>
<li>click "Finish"</li>
</ol>
<p>At version 0.3 the Mozilla calendar does not automatically refresh the calendar view, so if someone else has
added a meeting you will have to manually refresh the view to see that.</p>
<p>It is early days yet for the Mozilla calendar in it's current incarnation so no doubt there are other quirks
with Mozilla's handling of CalDAV too, so perhaps take a look at their bugzilla.</p>

<h1>Mulberry</h1>
<p>Mulberry is the most well-behaved of the applications I have been able to use.  It does have some
bugs, however, and a particular annoyance around it's use of non-standard names for time zones.  Mulberry
is the only client I have used so far which can issue a MKCALENDAR command or which will display a
hierarchy of calendars from one configured URL.</p>
<ol>
<li>Select "Preferences" from the "File" menu.</li>
<li>Choose the "Accounts" tab</li>
<li>Select "New" from the "Account" drop-down and a "Create New Account" dialog will appear.</li>
<li>Enter a name for the account, choose "CalDAV Calendar" for the type and click "OK"</li>
<li>In the "Server" field enter the domain name of your CalDAV server, such as "calendar.example.net"</li>
<li>In the "Authentication" pane of the "Accounts" tab, enter your username.</li>
<li>In the "Options" pane of the "Accounts" tab, enter the path, which should be "/caldav.php/"</li>
<li>"OK" the preferences dialog</li>
<li>A list of the users and resources which you are allowed to access should appear. Some may contain calendars.</li>
<li>If you don't already have a calendar for your own user, ensure your username is highlighted and choose "Create" from the "Calendar" menu.</li>
<li>Once you have a calendar created, you need to <em>subscribe to it.  One way is to right-click on it and choose 'Subscribe'.</em></li>
</ol>

<p>Unfortunately Mulberry is not open-source, though it is free, so we must wait on the developer to fix
the user interface niggles when he gets around to it.</p>
<p>Note that Mulberry has a complex user interface.  When I wrote this I went back into Mulberry and initially thought that RSCDS had regressed
somewhat and that these instructions didn't exactly work... :-)  It turned out that these instructions worked <em>just fine</em> when I followed
them to the letter the next day.  Go figure.  I think I need to record some screenshots of this one...</p>

<?php
 include("inc/page-footer.php");
?>