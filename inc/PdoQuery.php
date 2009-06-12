<?php
/**
* PDO query class and associated functions
*
* This subpackage provides some functions that are useful around database
* activity and a PdoDialect, PdoDatabase and PdoQuery classes to simplify
* handling of database queries and provide some access for a limited
* ability to handle varying database dialects.
*
* The class is intended to be a very lightweight wrapper with some features
* that have proved useful in developing and debugging web-based applications:
*  - All queries are timed, and an expected time can be provided.
*  - Parameters replaced into the SQL will be escaped correctly in order to
*    minimise the chances of SQL injection errors.
*  - Queries which fail, or which exceed their expected execution time, will
*    be logged for potential further analysis.
*  - Debug logging of queries may be enabled globally, or restricted to
*    particular sets of queries.
*  - Simple syntax for iterating through a result set.
*
* See http://wiki.davical.org/w/PdoQuery for design and usage information.
*
* If not already connected, PdoQuery will attempt to connect to the database,
* successively applying connection parameters from the array in $c->pdo_connect.
*
* We will die if the database is not currently connected and we fail to find
* a working connection.
*
* @package   awl
* @subpackage   PdoQuery
* @author    Andrew McMillan <andrew@morphoss.com>
* @copyright Morphoss Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v3
* @compatibility Requires PHP 5.1 or later
*/

/**
* The PdoDialect class handles
* @package awl
*/
class PdoDialect {
  /**#@+
  * @access private
  */

  /**
  * Holds the name of the database dialect
  */
  protected $dialect;

  /**#@-*/


  /**
  * Parses the connection string to ascertain the database dialect. Returns true if the dialect is supported
  * and fails if the dialect is not supported. All code to support any given database should be within in an
  * external include.
  * @param string $connection_string The full PDO connection string
  */
  function __construct( $connection_string ) {
    if ( preg_match( '/^(pgsql):/', $connection_string, $matches ) ) {
      $this->dialect = $matches[1];
    }
    else {
      trigger_error("Unsupported database connection '".$connection_string."'", E_USER_ERROR);
    }
  }



  /**
  * Returns the SQL for the current database dialect which will return a two-column resultset containing a
  * list of fields and their associated data types.
  * @param string $tablename_string The name of the table we want fields from
  */
  function GetFields( $tablename_string ) {
    if ( !isset($this->dialect) ) {
      trigger_error("Unsupported database dialect", E_USER_ERROR);
    }

    switch ( $this->dialect ) {
      case 'pgsql':
        $tablename_string = $this->Quote($tablename_string, 'identifier');
        $sql = "SELECT f.attname, t.typname FROM pg_attribute f ";
        $sql .= "JOIN pg_class c ON ( f.attrelid = c.oid ) ";
        $sql .= "JOIN pg_type t ON ( f.atttypid = t.oid ) ";
        $sql .= "WHERE relname = $tablename_string AND attnum >= 0 order by f.attnum;";
        return $sql;
    }
  }


  /**
  * Translates the given SQL string into a form that will hopefully work for this database dialect. This hook
  * is expected to be used by developers to provide support for differences in database operation by translating
  * the query string in an arbitrary way, such as through a file or database lookup.
  *
  * The actual translation to other SQL dialects will usually be application-specific, so that any routines
  * called by this will usually be external to this library, or will use resources external to this library.
  */
  function Translate( $sql_string ) {
    // Noop for the time being...
    return $sql_string;
  }



  /**
  * Returns $value escaped in an appropriate way for this database dialect.
  * @param mixed $value The value to be escaped
  * @param string $value_type The type of escaping desired.  If blank this will be worked out from gettype($value).  The special
  *                           type of 'identifier' can also be used for escaping of SQL identifiers.
  */
  function Quote( $value, $value_type = null ) {

    if ( !isset($value_type) ) {
      $value_type = gettype($value);
    }

    switch ( $value_type ) {
      case 'identifier': // special case will only happen if it is passed in.
        $rv = '"' . str_replace('"', '\\"', $value ) . '"';
        break;
      case 'null':
        $rv = 'NULL';
        break;
      case 'integer':
      case 'double' :
        return $str;
      case 'boolean':
        $rv = $str ? 'TRUE' : 'FALSE';
        break;
      case 'string':
      default:
        $str = str_replace("'", "''", $str);
        if ( strpos( $str, '\\' ) !== false ) {
          $str = str_replace('\\', '\\\\', $str);
          if ( $this->dialect == 'pgsql' ) {
            /** PostgreSQL wants to know when a string might contain escapes */
            $rv = "E'$str'";
          }
        }
    }

    return $rv;

  }


  /**
  * Replaces query parameters with appropriately escaped substitutions.
  *
  * The function takes a variable number of arguments, the first is the
  * SQL string, with replaceable '?' characters (a la DBI).  The subsequent
  * parameters being the values to replace into the SQL string.
  *
  * The values passed to the routine are analyzed for type, and quoted if
  * they appear to need quoting.  This can go wrong for (e.g.) NULL or
  * other special SQL values which are not straightforwardly identifiable
  * as needing quoting (or not).  In such cases the parameter can be forced
  * to be inserted unquoted by passing it as "array( 'plain' => $param )".
  *
  * @param  string The query string with replacable '?' characters.
  * @param mixed The values to replace into the SQL string.
  * @return The built query string
  */
  function ReplaceParameters() {
    $argc = func_num_args();
    $qry = func_get_arg(0);
    $args = func_get_args();

    if ( is_array($qry) ) {
      /**
      * If the first argument is an array we treat that as our arguments instead
      */
      $qry = $args[0][0];
      $args = $args[0];
      $argc = count($args);
    }

    /**
    * We only split into a maximum of $argc chunks.  Any leftover ? will remain in
    * the string and may be replaced at Exec rather than Prepare.
    */
    $parts = explode( '?', $qry, $argc );
    $querystring = $parts[0];
    $z = count($parts);

    for( $i = 1; $i < $z; $i++ ) {
      $arg = $args[$i];
      if ( !isset($arg) ) {
        $querystring .= 'NULL';
      }
      elseif ( is_array($arg) && $arg['plain'] != '' ) {
        // We abuse this, but people should access it through the PgQuery::Plain($v) function
        $querystring .= $arg['plain'];
      }
      else {
        $querystring .= $this->Quote($arg);  //parameter
      }
      $querystring .= $parts[$i]; //extras eg. ","
    }
    if ( isset($parts[$z]) ) $querystring .= $parts[$z]; //puts last part on the end

    return $querystring;
  }



}



/**
* Typically there will only be a single instance of the database level class in an application.
* @package awl
*/
class PdoDatabase {
  /**#@+
  * @access private
  */

  /**
  * Holds the PDO database connection
  */
  private $db;

  /**
  * Holds the dialect object
  */
  private $dialect;

  /**
  * Holds the state of the transaction 0 = not started, 1 = in progress, -1 = error pending rollback/commit
  */
  protected $txnstate = 0;

  /**
  * Holds the count of queries executed so far
  */
  protected $querycount = 0;

  /**
  * Holds the total duration of queries executed so far
  */
  protected $querytime = 0;

  /**#@-*/

  /**
  * The connection string is in the standard PDO format. The database won't actually be connected until the first
  * database query is run against it.
  *
  * The database object will also initialise and hold an PdoDialect object which will be used to provide database
  * specific SQL for some queries, as well as translation hooks for instances where it is necessary to modify the
  * SQL in transit to support additional databases.
  * @param string $connection_string The PDO connection string, in all it's glory
  * @param string $dbuser The database username to connect as
  * @param string $dbpass The database password to connect with
  * @param array  $options An array of driver options
  */
  function __construct( $connection_string, $dbuser=null, $dbpass=null, $options=null ) {
    $this->dialect = new PdoDialect( $connection_string );
    $this->db = new PDO( $connection_string, $dbuser, $dbpass, $options );
  }


  /**
  * Returns a PdoQuery object created using this database, the supplied SQL string, and any parameters given.
  * @param string $sql_query_string The SQL string containing optional variable replacements
  * @param mixed ... Subsequent arguments are positionally replaced into the $sql_query_string
  */
  function Prepare( ) {
    $qry = new PdoQuery( &$this );
    $qry->Query(func_get_args());
    return $qry;
  }


  /**
  * Construct and execute an SQL statement from the sql_string, replacing the parameters into it.
  *
  * @param string $sql_query_string The SQL string containing optional variable replacements
  * @param mixed ... Subsequent arguments are positionally replaced into the $sql_query_string
  * @return mixed false on error or number of rows affected. Test failure with === false
  */
  function Exec( ) {
    $sql_string = $this->dialect->ReplaceParameters(func_get_args());

    $start = microtime(true);
    $result = $db->exec($sql_string);
    $duration = microtime(true) - $start;
    $this->querytime += $duration;
    $this->querycount++;

    return $result;
  }


  /**
  * Begin a transaction.
  */
  function Begin() {
    if ( $this->txnstate == 0 ) {
      $this->db->beginTransaction();
      $this->txnstate = 1;
    }
    else {
      trigger_error("Cannot begin a transaction while a transaction is already active.", E_USER_ERROR);
    }
  }


  /**
  * Complete a transaction.
  */
  function Commit() {
    $this->txnstate = 0;
    if ( $this->txnstate != 0 ) {
      $this->db->commit();
    }
  }


  /**
  * Cancel a transaction in progress.
  */
  function Rollback() {
    $this->txnstate = 0;
    if ( $this->txnstate != 0 ) {
      $this->db->rollBack();
    }
    else {
      trigger_error("Cannot rollback unless a transaction is already active.", E_USER_ERROR);
    }
  }


  /**
  * Returns the current state of a transaction, indicating if we have begun a transaction, whether the transaction
  * has failed, or if we are not in a transaction.
  */
  function TransactionState() {
    return $this->txnstate;
  }


  /**
  * Returns the total duration of quries executed so far by this object instance.
  */
  function TotalDuration() {
    return $this->querytime;
  }


  /**
  * Returns the total number of quries executed by this object instance.
  */
  function TotalQueries() {
    return $this->querycount;
  }


  /**
  * Returns an associative array of field types, keyed by field name, for the requested named table. Internally this
  * calls PdoDialect::GetFields to get the required SQL and then processes the query in the normal manner.
  */
  function GetFields( $tablename_string ) {
  }


  /**
  * Operates identically to PdoDatabase::Prepare, except that PdoDialect::Translate() will be called on the query
  * before any processing.
  */
  function PrepareTranslated() {
  }


  /**
  * Switches on or off the processing flag controlling whether subsequent calls to PdoDatabase::Prepare are translated
  * as if PrepareTranslated() had been called.
  */
  function TranslateAll( $onoff_boolean ) {
  }

}


/**
* A variable of this class is normally constructed through a call to PdoDatabase::Query or PdoDatabase::Prepare,
* associating it on construction with the database which is to be queried.
* @package awl
*/
class PdoQuery {

  private $pdb;
  private $sth;
  private $max_duration = 2;

  /**
  * Where $db is a PdoDatabase object. This constructs the PdoQuery. If there are further parameters they
  * will be in turn, the sql, and any positional parameters to replace into that, and will be passed to
  * $this->Query() before returning.
  */
  function __construct( ) {
    $args = func_get_args();
    $this->pdb = array_shift( $args );
    if ( isset($db->default_max_duration) ) {
      $this->max_duration = $db->default_max_duration;
    }
    $this->Query($args);
  }


  /**
  * If the sql is supplied then PDO::prepare will be called with that SQL to prepare the query, and if there
  * are positional parameters then they will be replaced into the sql_string (with appropriate escaping)
  * before the call to PDO::prepare.  Query preparation time is counted towards total query execution time.
  */
  function Query( ) {
    $sql_string = $this->dialect->ReplaceParameters(func_get_args());

    $start = microtime(true);
    $this->sth = $pdb->db->prepare($sql_string);
    $duration = microtime(true) - $start;
    $this->querytime += $duration;
  }


  /**
  * If there are (some) positional parameters in the prepared query, now is the last chance to supply them...
  * before the query is executed. Returns true on success and false on error.
  */
  function Exec( ) {
    $start = microtime(true);
    $result = $this->sth->execute(func_get_args());
    $duration = microtime(true) - $start;
    $this->querytime += $duration;
    $this->querycount++;

    return $result;
  }


  /**
  * Will fetch the next row from the query into an object with elements named for the fields in the result.
  */
  function Fetch() {
    return $this->sth->fetchObject();
  }


  /**
  * Will fetch the next row from the query into an array with numbered elements and with elements named
  * for the fields in the result.
  */
  function FetchArray() {
    return $this->sth->fetch();
  }


  /**
  * Will fetch all result rows from the query into an array of objects with elements named for the fields in the result.
  */
  function FetchAll() {
    return $this->sth->fetchAll(PDO::FETCH_OBJ);
  }


  /**
  * An accessor for the number of rows affected when the query was executed.
  */
  function Rows() {
    return $this->sth->rowCount();
  }


  /**
  * Used to set the maximum duration for this query before it will be logged as a slow query.
  * @param double $seconds The maximum duration for this statement before logging it as 'slow'
  */
  function MaxDuration( $seconds ) {
    $this->max_duration = $seconds;
  }

}

