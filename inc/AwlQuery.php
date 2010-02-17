<?php
/**
* @package   awl
* @subpackage   AWLDB
* @author    Andrew McMillan <andrew@morphoss.com>
* @copyright Morphoss Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v3 or later
* @compatibility Requires PHP 5.1 or later
*/

require_once('AwlDatabase.php');

/**
* Database query class and associated functions
*
* This subpackage provides some functions that are useful around database
* activity and an AwlQuery class to simplify handling of database queries.
*
* The class is intended to be a very lightweight wrapper with no pretentions
* towards database independence, but it does include some features that have
* proved useful in developing and debugging web-based applications:
*  - All queries are timed, and an expected time can be provided.
*  - Parameters replaced into the SQL will be escaped correctly in order to
*    minimise the chances of SQL injection errors.
*  - Queries which fail, or which exceed their expected execution time, will
*    be logged for potential further analysis.
*  - Debug logging of queries may be enabled globally, or restricted to
*    particular sets of queries.
*  - Simple syntax for iterating through a result set.
*
* This class is intended as a transitional mechanism for moving from the
* PostgreSQL-specific PgQuery class to something which uses PDO in a more
* replaceable manner.
*
*/

/**
* Connect to the database defined in the $c->db_connect[] (or $c->pg_connect) arrays
*/
function _awl_connect_configured_database() {
  global $c, $_awl_dbconn;

  /**
  * Attempt to connect to the configured connect strings
  */
  $_awl_dbconn = false;

  if ( isset($c->db_connect) ) {
    $connection_strings = $c->db_connect;
  }
  elseif ( isset($c->pg_connect) ) {
    $connection_strings = $c->pg_connect;
  }

  foreach( $connection_strings AS $k => $v ) {
    $dbuser = null;
    $dbpass = null;
    if ( is_array($v) ) {
      $dsn = $v['dsn'];
      if ( isset($v['dbuser']) ) $dbuser = $v['dbuser'];
      if ( isset($v['dbpass']) ) $dbpass = $v['dbpass'];
    }
    elseif ( preg_match( '/^(\S+:)?(.*)( user=(\S+))?( password=(\S+))?$/', $v, $matches ) ) {
      $dsn = $matches[2];
      if ( isset($matches[1]) && $matches[1] != '' ) {
        $dsn = $matches[1] . $dsn;
      }
      else {
        $dsn = 'pgsql:' . $dsn;
      }
      if ( isset($matches[4]) && $matches[4] != '' ) $dbuser = $matches[4];
      if ( isset($matches[6]) && $matches[6] != '' ) $dbpass = $matches[6];
    }
    if ( $_awl_dbconn = new AwlDatabase( $dsn, $dbuser, $dbpass ) ) break;
  }

  if ( ! $_awl_dbconn ) {
    echo <<<EOERRMSG
  <html><head><title>Database Connection Failure</title></head><body>
  <h1>Database Error</h1>
  <h3>Could not connect to database</h3>
  </body>
  </html>
EOERRMSG;
    exit;
  }

  if ( isset($c->db_schema) && $c->db_schema != '' ) {
    $_awl_dbconn->SetSearchPath( $c->db_schema . ',public' );
  }

  $c->_awl_dbversion = $_awl_dbconn->GetVersion();
}


if ( !function_exists('duration') ) {
  /**
  * A duration (in decimal seconds) between two times which are the result of calls to microtime()
  *
  * This simple function is used by the AwlQuery class because the
  * microtime function doesn't return a decimal time, so a simple
  * subtraction is not sufficient.
  *
  * @param microtime $t1 start time
  * @param microtime $t2 end time
  * @return double difference
  */
  function duration( $t1, $t2 ) {
    list ( $ms1, $s1 ) = explode ( " ", $t1 );   // Format times - by spliting seconds and microseconds
    list ( $ms2, $s2 ) = explode ( " ", $t2 );
    $s1 = $s2 - $s1;
    $s1 = $s1 + ( $ms2 -$ms1 );
    return $s1;                                  // Return duration of time
  }
}


/**
* The AwlQuery Class.
*
* This class builds and executes SQL Queries and traverses the
* set of results returned from the query.
*
* <b>Example usage</b>
* <code>
* $sql = "SELECT * FROM mytable WHERE mytype = ?";
* $qry = new AwlQuery( $sql, $myunsanitisedtype );
* if ( $qry->Exec("typeselect", __line__, __file__ )
*      && $qry->rows > 0 )
* {
*   while( $row = $qry->Fetch() ) {
*     do_something_with($row);
*   }
* }
* </code>
*
* @package   awl
*/
class AwlQuery
{
  /**#@+
  * @access private
  */
  /**
  * Our database connection, normally copied from a global one
  * @var resource
  */
  protected $connection;

  /**
  * The original query string
  * @var string
  */
  protected $querystring;

  /**
  * The current array of bound parameters
  * @var array
  */
  protected $bound_parameters;

  /**
  * The PDO statement handle, or null if we don't have one yet.
  * @var string
  */
  protected $sth;

  /**
  * Result of the last execution
  * @var resource
  */
  protected $result;

  /**
  * number of current row - use accessor to get/set
  * @var int
  */
  protected $rownum = null;

  /**
  * number of rows from pg_numrows - use accessor to get value
  * @var int
  */
  protected $rows;

  /**
  * The Database error information, if the query fails.
  * @var string
  */
  protected $error_info;

  /**
  * Stores the query execution time - used to deal with long queries.
  * should be read-only
  * @var string
  */
  protected $execution_time;

  /**#@-*/

  /**#@+
  * @access public
  */
  /**
  * Where we called this query from so we can find it in our code!
  * Debugging may also be selectively enabled for a $location.
  * @var string
  */
  public $location;

  /**
  * How long the query should take before a warning is issued.
  *
  * This is writable, but a method to set it might be a better interface.
  * The default is 0.3 seconds.
  * @var double
  */
  public $query_time_warning = 0.3;
  /**#@-*/


 /**
  * Constructor
  * @param  string The query string in PDO syntax with replacable '?' characters or bindable parameters.
  * @param mixed The values to replace into the SQL string.
  * @return The AwlQuery object
  */
  function __construct() {
    global $_awl_dbconn;
    $this->rows = null;
    $this->execution_time = 0;
    $this->error_info = null;
    $this->rownum = -1;
    if ( isset($_awl_dbconn) ) $this->connection = $_awl_dbconn;
    else                       $this->connection = null;

    $argc = func_num_args();
    $args = func_get_args();

    $this->querystring = array_shift($args);
    if ( 1 < $argc ) {
      if ( is_array($args[0]) )
        $this->Bind($args[0]);
      else
        $this->Bind($args);
    }

    return $this;
  }


 /**
  * Use a different database connection for this query
  * @param  resource $new_connection The database connection to use.
  */
  function SetConnection( $new_connection ) {
    $this->connection = $new_connection;
  }



  /**
  * Log query, optionally with file and line location of the caller.
  *
  * This function should not really be used outside of AwlQuery.  For a more
  * useful generic logging interface consider calling dbg_error_log(...);
  *
  * @param string $locn    A string identifying the calling location.
  * @param string $tag     A tag string, e.g. identifying the type of event.
  * @param string $string  The information to be logged.
  * @param int    $line    The line number where the logged event occurred.
  * @param string $file    The file name where the logged event occurred.
  */
  function _log_query( $locn, $tag, $string, $line = 0, $file = "") {
    // replace more than one space with one space
    $string = preg_replace('/\s+/', ' ', $string);

    if ( ($tag == 'QF' || $tag == 'SQ') && ( $line != 0 && $file != "" ) ) {
      dbg_error_log( "LOG-$locn", " Query: %s: %s in '%s' on line %d", ($tag == 'QF' ? 'Error' : 'Possible slow query'), $tag, $file, $line );
    }

    while( strlen( $string ) > 0 )  {
      dbg_error_log( "LOG-$locn", " Query: %s: %s", $tag, substr( $string, 0, 240) );
      $string = substr( "$string", 240 );
    }
  }


  /**
  * Quote the given string so it can be safely used within string delimiters
  * in a query.  To be avoided, in general.
  *
  * @param mixed $str Data to be converted to a string suitable for including as a value in SQL.
  * @return string NULL, TRUE, FALSE, a plain number, or the original string quoted and with ' and \ characters escaped
  */
  function quote($str = null) {
    if ( !isset($this->connection) ) {
      _awl_connect_configured_database();
      $this->connection = $GLOBALS['_awl_dbconn'];
    }
    return $this->connection->Quote($str);
  }


  /**
  * Bind some parameters
  */
  function Bind() {
    $args = func_get_args();

    if ( gettype($args[0]) == 'array' ) {
      $this->bound_parameters = $args[0];
      /** @TODO: perhaps we should WARN here if there is more than 1 argument */
    }
    else {
      $this->bound_parameters = $args;
    }
  }


  /**
  * Tell the database to prepare the query that we will execute
  */
  function Prepare() {
    if ( !isset($this->connection) ) {
      _awl_connect_configured_database();
      $this->connection = $GLOBALS['_awl_dbconn'];
    }
    $this->sth = $this->connection->prepare( $this->querystring );
    if ( ! $this->sth ) {
      $this->error_info = $this->connection->errorInfo();
    }
    else $this->error_info = null;
  }


  /**
  * Return the query string we are planning to execute
  */
  function QueryString() {
    return $this->querystring;
  }


  /**
  * Return the parameters we are planning to substitute into the query string
  */
  function Parameters() {
    return $this->bound_parameters;
  }


  /**
  * Return the count of rows retrieved/affected
  */
  function rows() {
    return $this->rows;
  }


  /**
  * Execute the query, logging any debugging.
  *
  * <b>Example</b>
  * So that you can nicely enable/disable the queries for a particular class, you
  * could use some of PHPs magic constants in your call.
  * <code>
  * $qry->Exec(__CLASS__, __LINE__, __FILE__);
  * </code>
  *
  *
  * @param string $location The name of the location for enabling debugging or just
  *                         to help our children find the source of a problem.
  * @param int $line The line number where Exec was called
  * @param string $file The file where Exec was called
  * @return resource The actual result of the query (FWIW)
  */
  function Exec( $location = '', $line = 0, $file = '' ) {
    global $debuggroups, $c;
    $this->location = trim($location);
    if ( $this->location == "" ) $this->location = substr($_SERVER['PHP_SELF'],1);

    if ( isset($debuggroups['querystring']) || isset($c->dbg['querystring']) || isset($c->dbg['ALL']) ) {
      $this->_log_query( $this->location, 'DBGQ', $this->querystring, $line, $file );
      if ( isset($this->bound_parameters) && !isset($this->sth) ) {
        foreach( $this->bound_parameters AS $k => $v ) {
          $this->_log_query( $this->location, 'DBGQ', sprintf('    "%s" => "%s"', $k, $v), $line, $file );
        }
      }
    }

    if ( isset($this->bound_parameters) && !isset($this->sth) ) {
      $this->Prepare();
    }


    $success = true;
    $t1 = microtime(true); // get start time
    if ( isset($this->sth) && $this->sth !== false ) {
      if ( ! $this->sth->execute( $this->bound_parameters ) ) {
        $this->error_info = $this->sth->errorInfo();
        $success = false;
      }
      else $this->error_info = null;
    }
    else if ( $this->sth !== false ) {
      /** Ensure we have a connection to the database */
      if ( !isset($this->connection) ) {
        _awl_connect_configured_database();
        $this->connection = $GLOBALS['_awl_dbconn'];
      }
      $this->sth = $this->connection->query( $this->querystring );
      if ( ! $this->sth ) {
        $this->error_info = $this->connection->errorInfo();
        $success = false;
      }
      else $this->error_info = null;
    }
    if ( $success ) $this->rows = $this->sth->rowCount();
    $t2 = microtime(true); // get end time
    $i_took = $t2 - $t1;
    $c->total_query_time += $i_took;
    $this->execution_time = sprintf( "%2.06lf", $i_took);

    if ( ! $success ) {
      // query failed
      $this->errorstring = sprintf( 'SQL error "%s" - %s"', $this->error_info[0], (isset($this->error_info[2]) ? $this->error_info[2] : ''));
      $this->_log_query( $this->location, 'QF', $this->errorstring, $line, $file );
      $this->_log_query( $this->location, 'QF', $this->querystring, $line, $file );
      if ( isset($this->bound_parameters) && !isset($this->sth) ) {
        foreach( $this->bound_parameters AS $k => $v ) {
          $this->_log_query( $this->location, 'QF', sprintf('    "%s" => "%s"', $k, $v), $line, $file );
        }
      }
    }
    elseif ( $this->execution_time > $this->query_time_warning ) {
     // if execution time is too long
      $this->_log_query( $this->location, 'SQ', "Took: $this->execution_time for $this->querystring", $line, $file ); // SQ == Slow Query :-)
    }
    elseif ( isset($debuggroups[$this->location]) || isset($c->dbg[strtolower($this->location)]) || isset($c->dbg['ALL']) ) {
     // query successful, but we're debugging and want to know how long it took anyway
      $this->_log_query( $this->location, 'DBGQ', "Took: $this->execution_time for $this->querystring to find $this->rows rows.", $line, $file );
    }

    return $success;
  }


  /**
  * Fetch the next row from the query results
  * @param boolean $as_array True if thing to be returned is array
  * @return mixed query row
  */
  function Fetch($as_array = false) {
    global $c, $debuggroups;

    if ( ( isset($debuggroups["$this->location"]) && $debuggroups["$this->location"] > 2 )
       || (isset($c) && is_object($c) && ( isset($c->dbg[strtolower($this->location)]) && isset($c->dbg[strtolower($this->location)]) )
                                        || isset($c->dbg['ALL']) ) ) {
        $this->_log_query( $this->location, "Fetch", "$this->result Rows: $this->rows, Rownum: $this->rownum");
    }
    if ( ! $this->sth || $this->rows == 0 ) return false; // no results
    if ( $this->rownum == null ) $this->rownum = -1;
    if ( ($this->rownum + 1) >= $this->rows ) return false; // reached the end of results

    $this->rownum++;
    if ( isset($debuggroups["$this->location"]) && $debuggroups["$this->location"] > 1 ) {
      $this->_log_query( $this->location, "Fetch", "Fetching row $this->rownum" );
    }
    $row = $this->sth->fetch( ($as_array ? PDO::FETCH_NUM : PDO::FETCH_OBJ) );

    return $row;
  }


}

