<?php
/**
* Functions that are needed for all CalDAV Requests
*
*  - Ascertaining the paths
*  - Ascertaining the current user's permission to those paths.
*  - Utility functions which we can use to decide whether this
*    is a permitted activity for this user.
*
* @package   rscds
* @subpackage   CalDAVRequest
* @author    Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Andrew McMillan
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

/**
* A class for collecting things to do with this request.
*
* @package   rscds
*/
class CalDAVRequest
{

  /**
  * Create a new CalDAVRequest object.
  */
  function CalDAVRequest( ) {
    global $session, $c, $debugging;

    $this->raw_post = file_get_contents ( 'php://input');

    if ( isset($debugging) && isset($_GET['method']) ) {
      $_SERVER['REQUEST_METHOD'] = $_GET['method'];
    }
    $this->method = $_SERVER['REQUEST_METHOD'];

    /**
    * A variety of requests may set the "Depth" header to control recursion
    */
    $this->depth = ( isset($_SERVER['HTTP_DEPTH']) ? $_SERVER['HTTP_DEPTH'] : 0 );
    if ( $this->depth == 'infinity' ) $this->depth = 99;
    $this->depth = intval($this->depth);

    /**
    * MOVE/COPY use a "Destination" header and (optionally) an "Overwrite" one.
    */
    if ( isset($_SERVER['HTTP_DESTINATION']) ) $this->destination = $_SERVER['HTTP_DESTINATION'];
    $this->overwrite = ( isset($_SERVER['HTTP_OVERWRITE']) ? $_SERVER['HTTP_OVERWRITE'] : 'T' ); // RFC2518, 9.6 says default True.

    /**
    * LOCK things use an "If" header to hold the lock in some cases, and "Lock-token" in others
    */
    if ( isset($_SERVER['HTTP_IF']) ) $this->if_clause = $_SERVER['HTTP_IF'];
    if ( isset($_SERVER['HTTP_LOCK-TOKEN']) ) $this->lock_token = $_SERVER['HTTP_LOCK-TOKEN'];

    /**
    * Our path is /<script name>/<user name>/<user controlled> if it ends in
    * a trailing '/' then it is referring to a DAV 'collection' but otherwise
    * it is referring to a DAV data item.
    *
    * Permissions are controlled as follows:
    *  1. if there is no <user name> component, the request has read privileges
    *  2. if the requester is an admin, the request has read/write priviliges
    *  3. if there is a <user name> component which matches the logged on user
    *     then the request has read/write privileges
    *  4. otherwise we query the defined relationships between users and use
    *     the minimum privileges returned from that analysis.
    */
    $this->path = $_SERVER['PATH_INFO'];
    $bad_chars_regex = '/[\\^\\[\\(\\\\]/';
    if ( preg_match( $bad_chars_regex, $this->path ) ) {
      $this->DoResponse( 400, translate("The calendar path contains illegal characters.") );
    }

    $path_split = preg_split('#/+#', $this->path );
    $this->permissions = array();
    if ( !isset($path_split[1]) || $path_split[1] == '' ) {
      dbg_error_log( "caldav", "No useful path split possible" );
      unset($this->user_no);
      unset($this->username);
      $this->permissions = array("read" => 'read' );
      dbg_error_log( "caldav", "Read permissions for user accessing /" );
    }
    else {
      $this->username = $path_split[1];
      @dbg_error_log( "caldav", "Path split into at least /// %s /// %s /// %s", $path_split[1], $path_split[2], $path_split[3] );
      $qry = new PgQuery( "SELECT * FROM usr WHERE username = ?;", $this->username );
      if ( $qry->Exec("caldav") && $user = $qry->Fetch() ) {
        $this->user_no = $user->user_no;
      }
      if ( $session->AllowedTo("Admin") ) {
        $this->permissions = array('all' => 'all' );
        dbg_error_log( "caldav", "Full permissions for a systems administrator" );
      }
      else if ( $session->user_no == $this->user_no ) {
        $this->permissions = array('all' => 'all' );
        dbg_error_log( "caldav", "Full permissions for user accessing their own hierarchy" );
      }
      else if ( isset($this->user_no) ) {
        /**
        * We need to query the database for permissions
        */
        $qry = new PgQuery( "SELECT get_permissions( ?, ? ) AS perm;", $session->user_no, $this->user_no);
        if ( $qry->Exec("caldav") && $permission_result = $qry->Fetch() ) {
          $permission_result = "!".$permission_result->perm; // We prepend something to ensure we get a non-zero position.
          $this->permissions = array();
          if ( strpos($permission_result,"A") )
            $this->permissions['all'] = 'all';
          else {
            if ( strpos($permission_result,"R") )       $this->permissions['read'] = 'read';
            if ( strpos($permission_result,"W") )
              $this->permissions['write'] = 'write';
            else {
              if ( strpos($permission_result,"C") )       $this->permissions['bind'] = 'bind';      // PUT of new content (i.e. Create)
              if ( strpos($permission_result,"D") )       $this->permissions['unbind'] = 'unbind';  // DELETE
              if ( strpos($permission_result,"M") )       $this->permissions['write-content'] = 'write-content';  // PUT Modify
            }
          }
        }
        dbg_error_log( "caldav", "Restricted permissions for user accessing someone elses hierarchy: %s", implode( ", ", $this->permissions ) );
      }
    }

    /**
    * If the content we are receiving is XML then we parse it here.
    */
    $xml_parser = xml_parser_create_ns('UTF-8');
    $this->xml_tags = array();
    xml_parser_set_option ( $xml_parser, XML_OPTION_SKIP_WHITE, 1 );
    xml_parse_into_struct( $xml_parser, $this->raw_post, $this->xml_tags );
    xml_parser_free($xml_parser);

    /**
    * Look out for If-None-Match or If-Match headers
    */
    if ( isset($_SERVER["HTTP_IF_NONE_MATCH"]) ) {
      $this->etag_none_match = str_replace('"','',$_SERVER["HTTP_IF_NONE_MATCH"]);
      if ( $this->etag_none_match == '' ) unset($this->etag_none_match);
    }
    if ( isset($_SERVER["HTTP_IF_MATCH"]) ) {
      $this->etag_if_match = str_replace('"','',$_SERVER["HTTP_IF_MATCH"]);
      if ( $this->etag_if_match == '' ) unset($this->etag_if_match);
    }

  }

  /**
  * Are we allowed to do the requested activity
  *
  * @param string $activity The activity we want to do.
  */
  function AllowedTo( $activity ) {
    if ( isset($this->permissions['all']) ) return true;
    switch( $activity ) {
      case 'read':
        return isset($this->permissions['read']) || isset($this->permissions['write']);
        break;
      case 'write':
        return isset($this->permissions['write']);
        break;
      case 'delete':
        return isset($this->permissions['write']) || isset($this->permissions['unbind']);
        break;
      case 'create':
        return isset($this->permissions['write']) || isset($this->permissions['bind']);
        break;
      case 'modify':
        return isset($this->permissions['write']) || isset($this->permissions['write-content']);
        break;
      case 'mkcol':
        return isset($this->permissions['write']);
        break;
    }

    return false;
  }


  /**
  * Utility function we call when we have a simple status-based response to
  * return to the client.  Possibly
  *
  * @param int $status The HTTP status code to send.
  * @param string $message The friendly text message to send with the response.
  */
  function DoResponse( $status, $message, $content_type="text/plain" ) {
    global $session, $c;
    switch( $status ) {
      case 100: $status_text = "Continue"; break;
      case 101: $status_text = "Switching Protocols"; break;
      case 200: $status_text = "OK"; break;
      case 201: $status_text = "Created"; break;
      case 202: $status_text = "Accepted"; break;
      case 203: $status_text = "Non-Authoritative Information"; break;
      case 204: $status_text = "No Content"; break;
      case 205: $status_text = "Reset Content"; break;
      case 206: $status_text = "Partial Content"; break;
      case 207: $status_text = "Multi-Status"; break;
      case 300: $status_text = "Multiple Choices"; break;
      case 301: $status_text = "Moved Permanently"; break;
      case 302: $status_text = "Found"; break;
      case 303: $status_text = "See Other"; break;
      case 304: $status_text = "Not Modified"; break;
      case 305: $status_text = "Use Proxy"; break;
      case 307: $status_text = "Temporary Redirect"; break;
      case 400: $status_text = "Bad Request"; break;
      case 401: $status_text = "Unauthorized"; break;
      case 402: $status_text = "Payment Required"; break;
      case 403: $status_text = "Forbidden"; break;
      case 404: $status_text = "Not Found"; break;
      case 405: $status_text = "Method Not Allowed"; break;
      case 406: $status_text = "Not Acceptable"; break;
      case 407: $status_text = "Proxy Authentication Required"; break;
      case 408: $status_text = "Request Timeout"; break;
      case 409: $status_text = "Conflict"; break;
      case 410: $status_text = "Gone"; break;
      case 411: $status_text = "Length Required"; break;
      case 412: $status_text = "Precondition Failed"; break;
      case 413: $status_text = "Request Entity Too Large"; break;
      case 414: $status_text = "Request-URI Too Long"; break;
      case 415: $status_text = "Unsupported Media Type"; break;
      case 416: $status_text = "Requested Range Not Satisfiable"; break;
      case 417: $status_text = "Expectation Failed"; break;
      case 500: $status_text = "Internal Server Error"; break;
      case 501: $status_text = "Not Implemented"; break;
      case 502: $status_text = "Bad Gateway"; break;
      case 503: $status_text = "Service Unavailable"; break;
      case 504: $status_text = "Gateway Timeout"; break;
      case 505: $status_text = "HTTP Version Not Supported"; break;
    }
    header( sprintf("HTTP/1.1 %d %s", $status, $status_text) );
    header( sprintf("X-RSCDS-Version: RSCDS/%d.%d.%d; DB/%d.%d.%d", $c->code_major, $c->code_minor, $c->code_patch, $c->schema_major, $c->schema_minor, $c->schema_patch) );
    header( "Content-type: ".$content_type );
    echo $message;

    if ( strlen($message) > 100 || strstr($message, "\n") ) {
      $message = substr( preg_replace("#\s+#m", ' ', $message ), 0, 100);
    }

    dbg_error_log("caldav", "Status: %d, Message: %s, User: %d, Path: %s", $status, $message, $session->user_no, $this->path);

    exit(0);
  }


  /**
  * Return an array of what the DAV privileges are that are supported
  *
  * @return array The supported privileges.
  */
  function SupportedPrivileges() {
    $privs = array( "all"=>1, "read"=>1, "write"=>1, "bind"=>1, "unbind"=>1, "write-content"=>1);
    return $privs;
  }
}

?>