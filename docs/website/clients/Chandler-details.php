<h1>Chandler</h1>
<p>The <a href="http://www.osafoundation.org/">Open Source Applications Foundation</a> are developing <a href="http://cosmo.osafoundation.org/">Cosmo</a>, which is a CalDAV server
written in Java and <a href="http://chandler.osafoundation.org/">Chandler</a>, which is a CalDAV capable mail and calendaring application
written in Python.</p>

<p>Basic setup is as follows:</p>

<ol>
<li>Select "Subscribe" from the "Collection" menu.</li>
<li>Enter a URL like: "http://calendar.example.net/caldav.php/username/home/" (click "Subscribe") <img src="clients/Chandler-dialog1.png" /> <br /> &nbsp;</li>
<li>You will then be prompted for a username/password with in an expanded dialog.  Enter these and click "Subscribe" again. <img src="clients/Chandler-dialog2.png" /> <br /> &nbsp;</li>
<li>You should now have a new calendar showing.</li>
</ol>

<h2>Caveats</h2>
<p><img align="right" src="clients/Chandler-dialog3.png" />At version 0.7alpha3 the calendar is subscribed and displayed, but the 'displayname' property
which the server sends is not used and the calendar is displayed with a blank name.  Double-click
on your new calendar and enter a name in the space available. </p>

<p>Chandler describes itself as 'an experimentally usable calendar', and it certainly feels that
way.  It also will only synchronise to the CalDAV server either when you press the "Sync"
button or with a default frequency of hourly.  This is quite different to the other clients I
have used which all aggressively push new and changed items to the server as soon as possible,
but which may be lazy about fetching updates.</p>

<p>Operation with RSCDS is not yet perfect but basic operation is satisfactory.  I will be
concentrating on making RSCDS interoperate with Chandler over coming releases.</p>
