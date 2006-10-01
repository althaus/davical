<?php
  dbg_error_log("OPTIONS", "method handler");
  header( "Content-type: text/plain");
//  header( "Allow: OPTIONS, GET, HEAD, POST, PUT, DELETE, TRACE, COPY, MOVE, PROPFIND, PROPPATCH, LOCK, UNLOCK, REPORT, ACL");
  header( "Allow: ACL, COPY, DELETE, GET, HEAD, LOCK, MKCALENDAR, MKCOL, MOVE, OPTIONS, POST, PROPFIND, PROPPATCH, PUT, REPORT, SCHEDULE, TRACE, UNLOCK");
//  header( "DAV: 1, 2, 3, access-control, calendar-access");
//  header( "DAV: 1, 2, 3, calendar-access, calendar-schedule");
  header( "DAV: 1, 2, access-control, calendar-access, calendar-schedule");
?>