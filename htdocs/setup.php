<?php

include("../inc/always.php");
include("DAViCalSession.php");
$session->LoginRequired();

include("interactive-page.php");
include("page-header.php");

include("AwlQuery.php");

/** @TODO: work out something more than true/false returns for dependency checks */
function check_pdo() {
  return class_exists('PDO');
}

function check_pdo_pgsql() {
  global $_awl_dbconn;
  if ( !class_exists('PDO') ) return false;

  if ( !isset($_awl_dbconn) || $_awl_dbconn === false ) _awl_connect_configured_database();
  if ( $_awl_dbconn === false ) return false;
/*  $version = $_awl_dbconn->GetVersion();
  if ( !isset($version) ) return false;*/
  return true;
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

function check_davical_version() {
  global $c;
  $url = 'http://www.davical.org/current_davical_version';
  $version_file = @fopen($url, 'r');
  if ( ! $version_file ) return "Could not retrieve '$url'";
  $current_version = trim(fread( $version_file,12));
  fclose($version_file);
  return ( $c->version_string == $current_version );
}


$dependencies = array(
  'Current DAViCal version '. $c->version_string => 'check_davical_version',
  'DAViCal DB Schema version '. implode('.',$c->want_dbversion) => 'check_schema_version',
  'PHP PDO module available' => 'check_pdo',
  'PDO PostgreSQL divers' => 'check_pdo_pgsql',
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
  $dependencies_table .= sprintf( $dep_tpl, ($ok === true ? 'dep_ok' : 'dep_fail'), $k,  (is_string($ok) ? $ok : ($ok ? 'OK' : 'Failed')) );
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
