<?php
 $title = "Administration";
 include("inc/page-header.php");
?>
<h1>Administration Functions</h1>
<p>The administration of this application should be fairly simple.  You can administer:</p>
<ul>
<li>Principals (Users, Resources or Groups)</li>
<li>Membership of groups</li>
<li>Privileges granted by a principal or collection to another principal</li>

</ul>
<p><i>There is no ability to view and / or maintain calendars or events from within this administrative interface.</i></p>
<p>To do that you will need to use a CalDAV capable calendaring application such as Evolution, Sunbird, Thunderbird
(with the Lightning extension), Mulberry, Apple iCal, an iPhone or something else.</p>

<h2>Users, Resources and Groups</h2>
<p>These are the things which may have collections of calendar resources (i.e. calendars).</p>
<p>In the lists of principals you can click on any principal to see the full detail
for that record.</p>
<p>The primary differences between the types of principal are as follows:</p>
<ul>
<li>Users will probably have calendars, and are likely to also log on to the system.</li>

<li>Resources do have calendars, but they will not usually log on.</li>
<li>Groups provide an intermediate linking to minimise administration overhead.  They might not have calendars, and they will not usually log on.</li>
</ul>
<p>These differences are more conceptual than actual, however: in the DAV specification they are really all 'principals' and all equal.</p>

<h2>Groups</h2>
<p>Groups exist to simplify the maintenance of privileges.  Rather than assigning a write privilege
 to each individual with write access, you can create a group with the members being the people
 needing write access, and assign the write privilege to that group.</p>
<p>In this way as people come and go you can maintain the members of the group and it is easier to see
 who has the desired level of access.  If the needed level of access changes, you can change the grant
 to the individual group, rather than to each member of the group</p>

<h2>Privileges</h2>
<p>The basic DAV permissions are as follows:</p>
<p>read, write-properties, write-content, unlock, read-acl, read-current-user-privilege-set, write-acl, bind &amp; unbind</p>

<p>There are also a couple of useful aggregates of those, which are:</p>
<ul>
<li> write - aggregate of write-properties, write-content, bind &amp; unbind</li>
<li> all   - aggregate of all permissions</li>
</ul>
<p>Since none of those covered publication of Free/Busy information, CalDAV introduced an additional <em>read-free-busy</em></p>
<p>Unfortunately that didn't cover all of the possibilities of scheduling privileges, so the
 CalDAV Scheduling Extensions to WebDAV has added several further permissions:</p>
<p>schedule-deliver-invite, schedule-deliver-reply, schedule-query-freebusy, schedule-send-invite,
 schedule-send-reply, schedule-send-freebusy
 </p>
<p>Two more aggregate permissions are also added with this RFC:</p>
<ul>
<li> CALDAV:schedule-deliver - CALDAV:schedule-deliver-invite, CALDAV:schedule-deliver-reply and CALDAV:schedule-query-freebusy</li>
<li> CALDAV:schedule-send - CALDAV:schedule-send-invite, CALDAV:schedule-send-reply and CALDAV:schedule-send-freebusy</li>
</ul>
<p>That's all way too complicated, even if it does need to be there under the covers.  Mostly you just need to know
 about <em>read</em>, <em>write</em> &amp; <em>free-busy</em></p>

<h2>Some Examples</h2>

<h3>Several people administer a set of resources</h3>
<p>Suppose you have some resources, R1, R2 and R3 and you want to centralise the booking
of the resources through an administrative assistant, A1.  When A1 is away you want to
have a backup person, so you also want A2 to be able to do that.</p>
<p>In a case like this you should create an intermediate group "G" and make each
 of the people you want to be able to administer those resources members of that
 group.</p>
<p>Each of the resources should be set up to grant default privileges to everyone
 to see the full schedule (<em>read</em> privilege), and the resources should be
 set up to grant <em>write</em> (or possibly <em>all</em>) privileges to the group "G".</p>
<p>In this case you might only set up a single principal for the resources, and have
 multiple calendars, one for each resource.</p>
<pre>
A1  ==>> is a member of    ==> G
A2  ==>> is a member of    ==> G
R1  ==>> grants write privilege to ==> G
R2  ==>> grants write privilege to ==> G
R3  ==>> grants write privilege to ==> G
P1  is a different principal with no specifically granted privilege
</pre>
<p>P1 will be able to see all of the scheduled events for R1, R2 and R3, but will
not be able to create, delete or modify them.  A1 and A2 will be able to see,
create and modify all the events.</p>

<h4>An administrative assistant has full access to a managers calendar</h4>
<p>In this case the manager will simply grant the desired specific privileges to their assistant.</p>

<h4>A team wish to see each others calendars</h4>
<p>In this case you should create a group "G", which all team members are
members of, and each team member will grant whatever privileges they wish to that group.</p>
<pre>
P1  ==>> is a member of  ==> G
P1  ==>> grants read privilege to ==> G
P2  ==>> is a member of  ==> G
P2  ==>> grants read privilege to ==> G
P3  ==>> is a member of  ==> G
P3  ==>> grants write privilege to ==> G
P4  ==>> is a member of  ==> G
P4  ==>> grants read-free-busy privilege to ==> G
</pre>

<h4>A team can modify each others calendars</h4>
<p>Similar to above, you should create a group "G", which all team members are
members of, and each team member will grant <em>write</em> privileges to that group.</p>
<pre>
P1  ==>> is a member of  ==> G
P1  ==>> grants write privilege to ==> G
P2  ==>> is a member of  ==> G
P2  ==>> grants write privilege to ==> G
P3  ==>> is a member of  ==> G
P3  ==>> grants write privilege to ==> G
P4  ==>> is a member of  ==> G
P4  ==>> grants write privilege to ==> G
</pre>

<p>Also see the Permissions page on the DAViCal Wiki: <a href="http://wiki.davical.org/w/Permissions" title="The Permissions page on the DAViCal Wiki">http://wiki.davical.org/w/Permissions</a>.</p>

<h1>Configuring Calendar Clients for DAViCal</h1>
<p>The <a href="clients.php">DAViCal client setup page on sourceforge</a> has information on how
to configure Evolution, Mozilla Calendar (Sunbird &amp; Lightning) and Mulberry to use remotely hosted calendars.</p>
<p>The administrative interface has no facility for viewing or modifying calendar data.</p>

<h1>Configuring DAViCal</h1>
<p>If you can read this then things must be mostly working already.</p>
<p>The <a href="installation.php">DAViCal installation page</a> on sourceforge has
some further information on how to install and configure this application.</p>


<?php
 include("inc/page-footer.php");
