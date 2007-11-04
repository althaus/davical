<h1>Mulberry</h1>
<p><a href="http://www.mulberrymail.com/">Mulberry</a> is the most well-behaved of the applications I have been
able to use.  It does have some bugs, however, and a particular annoyance around it's use of non-standard names
for time zones.  Mulberry is the only client I have used so far which can issue a MKCALENDAR command or which
will display a hierarchy of calendars from a single configured URL, dicovering the calendars through recursive
PROPFIND requests.</p>

<ol>
<li>Select "Preferences" from the "File" menu.</li>
<li>Choose the "Accounts" tab</li>
<li>Select "New" from the "Account" drop-down and a "Create New Account" dialog will appear.</li>
<li>Enter a name for the account, choose "CalDAV Calendar" for the type and click "OK"</li>
<li>In the "Server" field enter the domain name of your CalDAV server, such as "calendar.example.net"</li>
<li>In the "Authentication" pane of the "Accounts" tab, enter your username.<img src="clients/Mulberry-dialog1.png" /> <br /> &nbsp;</li>
<li>In the "Options" pane of the "Accounts" tab, enter the path, which should be "/caldav.php/"<img src="clients/Mulberry-dialog2.png" /> <br /> &nbsp;</li>
<li>"OK" the preferences dialog</li>
<li>A list of the users and resources which you are allowed to access should appear. Some may contain calendars.</li>
<li>If you don't already have a calendar for your own user, ensure your username is highlighted and choose "Create" from the "Calendar" menu.</li>
<li>Once you have a calendar created, you need to <em>subscribe to it.  One way is to right-click on it and choose 'Subscribe'.</em></li>
</ol>

<h2>Caveats</h2>
<p>Unfortunately Mulberry is not (yet) open-source, though it is free, so we must wait on the developer to fix
the user interface niggles when he gets around to it.</p>
<p>Note that Mulberry has a complex user interface.  When I wrote this I went back into Mulberry and initially
thought that DAViCal had regressed somewhat and that these instructions didn't exactly work... :-)  It turned out
that these instructions worked <em>just fine</em> when I followed them to the letter the next day.  Go figure.
I think I need to record some screenshots of this one...</p>

