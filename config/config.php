<?php
//  $c->domainname = "mycaldav.andrew";
//  $c->sysabbr     = 'rscds';
//  $c->admin_email = 'andrew@catalyst.net.nz';
//  $c->system_name = "Really Simple CalDAV Store";
//  $c->collections_always_exist = false;

  $c->pg_connect[] = 'dbname=caldav port=5433 user=general';
  $c->pg_connect[] = 'dbname=caldav port=5432 user=general';

  $c->dbg['ALL'] = 1;
  $c->dbg['propfind'] = 1;
  $c->dbg['report'] = 1;
//  $c->dbg['get'] = 1;
//  $c->dbg['put'] = 1;
//  $c->dbg['ics'] = 1;
//  $c->dbg['icalendar'] = 1;
  $c->dbg['vevent'] = 1;
  $c->dbg['caldav'] = 1;
//  $c->dbg['user'] = 1;

  $c->collections_always_exist = false;

?>