<h1>Sunbird / Lightning (Mozilla Calendar)</h1>
<p>The <a href="http://www.mozilla.org/projects/calendar/">Mozilla Calendar</a> project offers their calendar under two different names:
 <em><a href="http://www.mozilla.org/projects/calendar/sunbird/">Sunbird</a></em> is a standalone calendar
 application, and <em><a href="http://www.mozilla.org/projects/calendar/lightning/">Lightning</a></em> is a Thunderbird extension.
 The two are essentially the same, as far as DAViCal is
 concerned, and these instructions should work for either of them.</p>

<ol>
<li>Select "New Calendar" from the "File" menu.</li>
<li>Choose "On the Network" (click "Next")<img src="clients/Mozilla-dialog1.png" /> <br /> &nbsp;</li>
<li>Choose a format of "CalDAV" and enter a URL like: "http://calendar.example.net/caldav.php/username/calendar/" (click "Next")<img src="clients/Mozilla-dialog2.png" /> <br /> &nbsp;</li>
<li>Give the calendar an appropriate display name, and choose a colour for events on this calendar. (click "Next")<img src="clients/Mozilla-dialog3.png" /> <br /> &nbsp;</li>
<li>click "Finish"</li>
</ol>

<h2>Caveats</h2>
<p>At version 0.3 the Mozilla calendar does not automatically refresh the calendar view, so if someone else has
added a meeting you will have to manually refresh the view to see that.</p>
<p>It is early days yet for the Mozilla calendar in it's current incarnation so no doubt there are other quirks
with Mozilla's handling of CalDAV too, so perhaps take a look at their bugzilla.</p>
<p>As at version 0.3, you should be aware of this <a href="https://bugzilla.mozilla.org/show_bug.cgi?id=360076">bug with empty CalDAV calendars</a> which can be
confusing.  Add your calendar, create an event and then re-start the program before saying that things
are not working!</p>
