<h1>iCal</h1>
<p><a href="http://www.apple.com/macosx/what-is-macosx/mail-ical-address-book.html">iCal</a>, from version 3.0 (released with OS 10.5) is generally well-behaved
  and will discover your own calendars when configured.  It will not allow you to manipulate other calendars on
  the same server, however, unless you use different credentials to access them.</p>

<ol>
  <li>Open the "Preferences" dialog.</li>
  <li>Choose the "Accounts" tab</li>
  <li>Click on the "+" and a new panel will appear.</li>
  <li>Enter a "Description" for the account.</li>
  <li>The "Username" and "Password" are the relevant ones for your CalDAV server.</li>
  <li>Open the "Server Options" area and set your account URL to point to http://host.../caldav.php/username/.<img src="clients/iCal-dialog.png" /> <br /> &nbsp;</li>
  <li>Click "Add" to confirm the new account</li>
  <li>Your own calendars will be automatically discovered.</li>
  <li>If you don't already have a calendar for your own user, go to the calendar view and long-click on the "+" will display a menu letting you create a new one.</li>
</ol>

<h2>Caveats</h2>
<p>DAViCal does not fully support the draft scheduling extensions to CalDAV, so you will not see the full functionality
  of iCal.</p>
<p>iCal does not let you browse the calendar hierarchy to find other calendars you could view, so you will not
  see the full functionality of DAViCal either.</p>

