<?php
//  $c->domainname = "mycaldav.andrew";
//  $c->sysabbr     = 'rscds';
//  $c->admin_email = 'andrew@catalyst.net.nz';
//  $c->system_name = "Really Simple CalDAV Store";

  if ( ! $dbconn = pg_Connect("port=5433 dbname=caldav user=general") ) {
    if ( ! $dbconn = pg_Connect("port=5432 dbname=caldav user=general") ) {
      echo "<html><head><title>Database Error</title></head><body>
  <h1>Database Error</h1>
  <h3>Could not connect to PGPool or to Postgres</h3>
  </body>
  </html>";
      exit;
    }
  }

?>