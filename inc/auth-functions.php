<?php
/**
* The authentication handling plugins can be used by the Session class to
* provide authentication.
*
* Each authenticate hook needs to:
*   - Accept a username / password
*   - Confirm the username / password are correct
*   - Create (or update) a 'usr' record in our database
*   - Return the 'usr' record as an object
*   - Return === false when authentication fails
*
* It can expect that:
*   - Configuration data will be in $c->authenticate_hook['config'], which might be an array, or whatever is needed.
*
* In order to be called:
*   - This file should be included
*   - $c->authenticate_hook['call'] should be set to the name of the plugin
*   - $c->authenticate_hook['config'] should be set up with any configuration data for the plugin
*
* @package   davical
* @subpackage   authentication
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst IT Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

require_once("AWLUtilities.php");

/**
* Create a default home calendar for the user.
* @param string $username The username of the user we are creating relationships for.
*/
function CreateHomeCalendar( $username ) {
  global $session, $c;
  if ( ! isset($c->home_calendar_name) || strlen($c->home_calendar_name) == 0 ) return true;

  $usr = getUserByName( $username );
  $parent_path = "/".$username."/";
  $calendar_path = $parent_path . $c->home_calendar_name."/";
  $dav_etag = md5($usr->user_no . $calendar_path);
  $sql = "INSERT INTO collection (user_no, parent_container, dav_name, dav_etag, dav_displayname, is_calendar, ";
  $sql .= "created, modified) VALUES( ?, ?, ?, ?, ?, true, current_timestamp, current_timestamp );";
  $qry = new PgQuery( $sql, $this->user_no, $parent_path, $calendar_path, $dav_etag, $usr->fullname);
  if ( $qry->Exec() ) {
    $c->messages[] = i18n("Home calendar added.");
    dbg_error_log("User",":Write: Created user's home calendar at '%s'", $calendar_path );
  }
  else {
    $c->messages[] = i18n("There was an error writing to the database.");
    return false;
  }
  return true;
}


/**
* Create default relationships
* @param string $username The username of the user we are creating relationships for.
*/
function CreateDefaultRelationships( $username ) {
  global $session, $c;
  if ( ! isset($c->default_relationships) || !is_array($c->default_relationships) || count($c->default_relationships) == 0 ) return false;

  $usr = getUserByName( $username );
  $sql = "";
  foreach( $c->default_relationships AS $to_user => $permission ) {
    $sql .= "INSERT INTO relationship (from_user, to_user, rt_id) ";
    $sql .= "VALUES( $usr->user_no, $to_user, (select rt_id from relationship_type where confers = '$permission' order by rt_id limit 1) );";
  }
  $qry = new PgQuery( $sql );
  if ( $qry->Exec() ) {
    $c->messages[] = i18n("Default relationships added.");
    dbg_error_log("User",":Write: Added default relationships" );
  }
  else {
    $c->messages[] = i18n("There was an error writing to the database.");
    return false;
  }
  return true;
}


/**
* Authenticate against a different PostgreSQL database which contains a usr table in
* the AWL format.
*
* @package   davical
*/
function AuthExternalAWL( $username, $password ) {
  global $c;

  $authconn = pg_Connect($c->authenticate_hook['config']['connection']);
  if ( ! $authconn ) {
    echo <<<EOERRMSG
  <html><head><title>Database Connection Failure</title></head><body>
  <h1>Database Error</h1>
  <h3>Could not connect to PostgreSQL database</h3>
  </body>
  </html>
EOERRMSG;
    exit(1);
  }

  if ( isset($c->authenticate_hook['config']['columns']) )
    $cols = $c->authenticate_hook['config']['columns'];
  else
    $cols = "*";

  $qry = new PgQuery("SELECT $cols FROM usr WHERE lower(username) = ? ", strtolower($username) );
  $qry->SetConnection($authconn);
  if ( $qry->Exec('Login',__LINE,__FILE__) && $qry->rows == 1 ) {
    $usr = $qry->Fetch();
    if ( session_validate_password( $password, $usr->password ) ) {

      $qry = new PgQuery("SELECT * FROM usr WHERE user_no = $usr->user_no;" );
      if ( $qry->Exec('Login',__LINE,__FILE__) && $qry->rows == 1 )
        $type = "UPDATE";
      else
        $type = "INSERT";

      include_once("DataUpdate.php");
      $qry = new PgQuery( sql_from_object( $usr, $type, 'usr', "WHERE user_no=$usr->user_no" ) );
      $qry->Exec('Login',__LINE,__FILE__);

      /**
      * We disallow login by inactive users _after_ we have updated the local copy
      */
      if ( isset($usr->active) && $usr->active == 'f' ) return false;

      if ( $type == 'INSERT' ) {
        CreateHomeCalendar($usr->username);
        CreateDefaultRelationships($usr->username);
      }

      return $usr;
    }
  }

  return false;

}
