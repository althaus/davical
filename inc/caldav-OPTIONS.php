<?php
  dbg_error_log("OPTIONS", "method handler");
  header( "Content-type: text/plain");
  header( "Allow: OPTIONS, GET, HEAD, POST, PUT, DELETE, TRACE, COPY, MOVE, PROPFIND, PROPPATCH, LOCK, UNLOCK, REPORT, ACL");
  header( "DAV: 1, 2, 3, access-control, calendar-access");
?>