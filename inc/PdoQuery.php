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

    switch ( $this->dialect ) {
      case 'pgsql':
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
            //PostgreSQL treats a backslash as an escape character.
            $str = str_replace('\\', '\\\\', $str);
            $rv = "E'$str'";
        }
        break;
    }

    return $rv;

  }

}


/**
* A variable of this class is normally constructed through a call to PdoDatabase::Query or PdoDatabase::Prepare,
* associating it on construction with the database which is to be queried.
* @package awl
*/
class PdoQuery {

  /**
  * Where $db is a PdoDatabase object. This constructs the PdoQuery. If there are further parameters they
  * will be in turn, the sql, and any positional parameters to replace into that, and will be passed to
  * $this->Query() before returning.
  */
  function __construct( $db, ... ) {
  }


  /**
  * If the sql is supplied then PDO::prepare will be called with that SQL to prepare the query, and if there
  * are positional parameters then they will be replaced into the sql_string (with appropriate escaping)
  * before the call to PDO::prepare.
  */
  function Query( $sql_string, ... ) {
  }


  /**
  * If there are (some) positional parameters in the prepared query, now is the last chance to supply them...
  * before the query is executed. Returns true on success and false on error.
  */
  function Exec( ... ) {
  }


  /**
  * Will fetch the next row from the query into an object with elements named for the fields in the result.
  */
  function Fetch() {
  }


  /**
  * Will fetch the next row from the query into an array of fields.
  */
  function FetchArray() {
  }


  /**
  * Will fetch all result rows from the query into an array of objects with elements named for the fields in the result.
  */
  function FetchAll() {
  }


  /**
  * An accessor for the number of rows affected when the query was executed.
  */
  function Rows() {
  }


  /**
  * Used to set the maximum duration for this query before it will be logged as a slow query.
  */
  function MaxDuration( $seconds_double ) {
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
  */
  function Prepare( $sql_string, ... ) {
  }


  /**
  * Construct and execute an SQL statement from the sql_string, replacing the parameters into it. Returns true on
  * success and false on error.
  *
  * While this uses a PdoQuery internally, this is not exposed. It is intended for use with queries which are not
  * needed after execution to know how many rows are affected, or to be able to process a result set.
  */
  function Exec( $sql_string, ... ) {
  }


  /**
  * Begin a transaction.
  */
  function Begin() {
  }


  /**
  * Complete a transaction.
  */
  function Commit() {
  }


  /**
  * Cancel a transaction in progress.
  */
  function Rollback() {
  }


  /**
  * Returns the current state of a transaction, indicating if we have begun a transaction, whether the transaction
  * has failed, or if we are not in a transaction.
  */
  function TransactionState() {
  }


  /**
  * Returns the total duration of quries executed so far by this object instance.
  */
  function TotalDuration() {
  }


  /**
  * Returns the total number of quries executed by this object instance.
  */
  function TotalQueries() {
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

