<?php
//  $c->domainname = "mycaldav.andrew";
//  $c->sysabbr     = 'rscds';
//  $c->admin_email = 'andrew@catalyst.net.nz';
//  $c->system_name = "Really Simple CalDAV Store";

  $c->pg_connect[] = 'dbname=rscds port=5433 user=general';
  $c->pg_connect[] = 'dbname=rscds port=5432 user=general';

  $c->dbg['vevent'] = 1;
  $c->dbg['put'] = 1;
  $c->dbg['report'] = 1;

?>