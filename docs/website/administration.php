<?php
 $title = "Administration";
 include("inc/page-header.php");
?>
<h1>Administration Functions</h1>
<p>The administration of this application should be fairly simple.  You can administer:</p>
<ul>
<li>Users (or Resources or Groups) and the relationships between them</li>
<li>The types of relationships that are available</li>

</ul>
<p><i>There is no ability to view and / or maintain calendars or events from within this administrative interface.</i></p>
<p>To do that you will need to use a CalDAV capable calendaring application such as Evolution, Sunbird, Thunderbird
(with the Lightning extension) or Mulberry.</p>

<h2>Users, Resources and Groups</h2>
<p>These are the things which may have collections of calendar resources (i.e. calendars).</p>
<p>In the list of users you can click on any user to see the full detail
for that person (or group or resource - but from now we'll just call them users).</p>
<p>The primary differences between them are as follows:</p>
<ul>
<li>Users will probably have calendars, and are likely to also log on to the system.</li>

<li>Resources do have calendars, but they will not usually log on.</li>
<li>Groups provide an intermediate linking to minimise administration overhead.  They might not have calendars, and they will not usually log on.</li>
</ul>

<h2>Types of Relationships</h2>
<p>These define the structure of the relationships between users.</p>
<p>A manager might want to grant full access to their calendar to an assistant, for example, so
there should be an "Is assisted by" relationship between the manager and the assistant.</p>
<p>Relationships themselves are maintained on the User maintenance screen.</p>
<p>It can also be useful to have several people having the same kind of access to a particular
set of resources.  The "Is a member of group" relationship is used to link users to the group,
and then the group is linked to each resource with the "Administers Resource" relationship.</p>
<p>You can also define other relationship types beyond the basic ones just described.</p>
<p>Relationship links work in three ways, as follows:</p>
<ul>
<li>Where users relate to each other (i.e. links to non-group targets), the relationship type will define whether access is read only, or read &amp; write.</li>
<li>Where a set of users link to a group, which does not further link to other users/resources, they will share the same access to each other.</li>
<li>Where a set of users link to a group, which then links to other users/resources, the access restrictions will apply as the lesser of their link to that group, or the link from the group.  They will have no access to each other's calendars.</li>
</ul>

<h3>Some Examples</h3>

<h4>Several people administer a set of resources</h4>
<p>Suppose you have some resources, R1, R2 and R3 and you want to centralise the booking
of the resources through an administrative assistant, A1.  When A1 is away you want to
have a backup person, so you also want A2 to be able to do that.</p>
<p>In a case like this you should create an intermediate group "G" and set up
relationships ("Administers Resource") from "G" to each of the resources to be
administered.  For each of the people able to administer those resources you
should create relationships ("Administers Group") to the group.  Other people
who may view the resources, but not change them, can be related to the group
as "is a member of" which will grant them read only privileges to all of the
group's resources.</p>
<pre>
A1  ==>> Administers Group    ==> G
A2  ==>> Administers Group    ==> G
G   ==>> Administers Resource ==> R1
G   ==>> Administers Resource ==> R2
G   ==>> Administers Resource ==> R3
P1  ==>> is a member of       ==> G
P2  ==>> is a member of       ==> G
</pre>
<p>P1 will be able to see all of the scheduled events for R1, R2 and R3, but will
not be able to create, delete or modify them.  A1 and A2 will be able to see,
create and modify all the events.</p>

<h4>An administrative assistant has full access to a managers calendar</h4>
<p>In this case you set a relationship from the administrative assistant
to the manager of "is Assistant to"</p>
<pre>
A1  ==>> is Assistant to ==> Manager
</pre>
<p>(Actually I see there is a bug there in 0.4.0, in that the relationship type
is named the wrong way around as "Is Assisted by", so I'll rename that
in 0.4.1 :-)</p>

<h4>A team can see each others calendars</h4>
<p>In this case you should create a group "G", which all team members are
linked to as "is a member of".</p>
<pre>
P1  ==>> is a member of  ==> G
P2  ==>> is a member of  ==> G
P3  ==>> is a member of  ==> G
P4  ==>> is a member of  ==> G
</pre>

<h4>A team can modify each others calendars</h4>
<p>In this case you should create a group "G", which all team members are
linked to as "Administers Group".</p>
<pre>
P1  ==>> Administers Group  ==> G
P2  ==>> Administers Group  ==> G
P3  ==>> Administers Group  ==> G
P4  ==>> Administers Group  ==> G
</pre>


<h1>Configuring Calendar Clients for RSCDS</h1>
<p>The <a href="http://rscds.sourceforge.net/clients.php">RSCDS client setup page on sourceforge</a> has information on how
to configure Evolution, Mozilla Calendar (Sunbird &amp; Lightning) and Mulberry to use remotely hosted calendars.</p>
<p>The administrative interface has no facility for viewing or modifying calendar data.</p>

<h1>Configuring RSCDS</h1>
<p>If you can read this then things must be mostly working already.</p>
<p>The <a href="http://rscds.sourceforge.net/installation.php">RSCDS installation page</a> on sourceforge has
some further information on how to install and configure this application.</p>


<?php
 include("inc/page-footer.php");
?>
