<?php
/**
* CalDAV Server - handle OPTIONS method
*
* @package   rscds
* @subpackage   caldav
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
  dbg_error_log("OPTIONS", "method handler");

  header( "Content-type: text/plain" );
  header( "Content-length: 0" );

  /**
  * As yet we only support quite a limited range of options.  When we see clients looking
  * for more than this we will work to support them further.  We should probably support
  * PROPPATCH, because I suspect that will be used.  Also HEAD and POST being fairly standard
  * should be handled.  COPY and MOVE would seem to be easy also.
  */
  header( "Allow: OPTIONS, GET, PUT, DELETE, PROPFIND, REPORT, MKCALENDAR, MKCOL");
  // header( "Allow: ACL, COPY, DELETE, GET, HEAD, LOCK, MKCALENDAR, MKCOL, MOVE, OPTIONS, POST, PROPFIND, PROPPATCH, PUT, REPORT, SCHEDULE, TRACE, UNLOCK");

  /**
  * FIXME: WTF is calendar-schedule ??  The CalDAV draft 15 doesn't mention it,
  * but some CalDAV servers seem to say they do it.  We'll leave it out for now.
  *
  * access-control is rfc3744, so we will say we do it, but I doubt if we do it
  * in all it's glory really.
  */
  header( "DAV: 1, 2, access-control, calendar-access");
  // header( "DAV: 1, 2, access-control, calendar-access, calendar-schedule");

  /**
  * FIXME: We should only return the 'calendar-access' and 'calendar-schedule' DAV headers for calendar collections.
  * We should only "Allow" the REPORT method against calendar collections.
  */
?>