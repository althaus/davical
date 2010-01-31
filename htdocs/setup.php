<?php

include("../inc/always.php");
include("DAViCalSession.php");
$session->LoginRequired();

include("interactive-page.php");
include("page-header.php");

/** @TODO: work out something more than true/false returns for dependency checks */
function check_pdo() {
  return class_exists('PDO');
}

function check_pgsql() {
  return function_exists('pg_connect');
}

function check_schema_version() {
  global $c;
  if ( $c->want_dbversion[0] == $c->schema_major
    && $c->want_dbversion[1] == $c->schema_minor
    && $c->want_dbversion[2] == $c->schema_patch ) {
    return true;
  }
  return false;
}

$dependencies = array(
  'DAViCal DB Schema version '. implode('.',$c->want_dbversion) => 'check_schema_version',
  'PHP PDO module available' => 'check_pdo',
  'PHP PostgreSQL available' => 'check_pgsql' /*,
  'YAML' => 'php5-syck' */
);

$dependencies_table = '';
$dep_tpl = '<tr class="%s">
  <td>%s</td>
  <td>%s</td>
</tr>
';
foreach( $dependencies AS $k => $v ) {
  $ok = $v();
  $dependencies_table .= sprintf( $dep_tpl, ($ok ? 'dep_ok' : 'dep_fail'), $k,  ($ok ? 'OK' : 'Failed') );
}

$want_dbversion = implode('.',$c->want_dbversion);

  echo <<<EOBODY
<style>
tr.dep_ok {
  background-color:#80ff80;
}
tr.dep_fail {
  background-color:#ffc0c0;
}
table.dependencies {
  border: 1px grey solid;
  border-collapse: collapse;
  padding: 0.1em;
  margin-left: 3px;
}
table.dependencies tr td, table.dependencies tr th {
  border: 1px grey solid;
  padding: 0.1em 0.2em;
}
p {
  padding: 0.3em 0.2em 0.7em;
}
</style>

<h1>Setup</h1>
<p>Currently this page does very little.  Suggestions or patches to make it do something
useful will be gratefully received.

<h2>Current Versions</h2>
<p>You are currently running DAViCal version $c->version_string.
The database schema should be at version $want_dbversion and it is at version $c->schema_major.$c->schema_minor.$c->schema_patch.
<br>&nbsp;
</p>

<h2>Dependencies</h2>
<p>
<table class="dependencies">
<tr>
<th>Dependency</th>
<th>Status</th>
</tr>
$dependencies_table
</table>
<br>&nbsp;
</p>

<h2>Configuring Calendar Clients for DAViCal</h2>
<p>The <a href="http://rscds.sourceforge.net/clients.php">DAViCal client setup page on sourceforge</a> have information on how
to configure Evolution, Sunbird, Lightning and Mulberry to use remotely hosted calendars.</p>
<p>The administrative interface has no facility for viewing or modifying calendar data.</p>

<h2>Configuring DAViCal</h2>
<p>If you can read this then things must be mostly working already.</p>
<p>The <a href="http://rscds.sourceforge.net/installation.php">DAViCal installation page</a> on sourceforge has
some further information on how to install and configure this application.</p>
EOBODY;

include("page-footer.php");
