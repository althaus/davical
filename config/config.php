<?php
//  $c->domainname = "mycaldav.andrew";
//  $c->sysabbr     = 'rscds';
//  $c->admin_email = 'andrew@catalyst.net.nz';
//  $c->system_name = "Really Simple CalDAV Store";

  $c->pg_connect[] = 'dbname=caldav port=5433 user=general';
  $c->pg_connect[] = 'dbname=caldav port=5432 user=general';

  $c->dbg['ALL'] = 1;
  $debuggroups['querystring'] = 1;

?>