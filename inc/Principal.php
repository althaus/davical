<?php
/**
* An object representing a 'Principal' read from the database
*
* @package   davical
* @subpackage   Principal
* @author    Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Morphoss Ltd <http://www.morhposs.com/>
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
*/

require_once('AwlCache.php');

/**
* A class for things to do with a Principal
*
* @package   davical
*/
class Principal {

  /**
   * Some control over our DB
   * @var unknown_type
   */
  private static $db_tablename = 'dav_principal';
  private static $db_mandatory_fields = array(
        'username', 
  );

  public static function updateableFields() {
    return array(
            'username', 'email', 'user_active', 'modified', 'password', 'fullname', 
            'email_ok', 'date_format_type', 'locale', 'type_id', 'displayname', 'default_privileges'
		  );
  } 

  /**
   * We cache these so if we try and access a row by principal_id/user_no/e_mail that we've
   * already read we don't read it again. 
   * @var unknown_type
   */
  private static $byUserno = array();
  private static $byId     = array();
  private static $byEmail  = array();

  /**
   * Columns from the database
   */
  protected $username;
  protected $user_no;
  protected $principal_id;
  protected $email;
  protected $dav_name;
  public $user_active;
  public $created; 
  public $modified; 
  public $password;
  public $fullname; 
  public $email_ok; 
  public $date_format_type; 
  public $locale; 
  public $type_id;
  public $displayname; 
  public $default_privileges; 
  public $is_principal;
  public $is_calendar;
  public $collection_id; 
  public $is_addressbook; 
  public $resourcetypes;
  public $privileges; 

  /**
   * Whether this Principal actually exists in the database yet.
   * @var boolean
   */
  protected $exists;

  /**
  * @var The home URL of the principal
  */
  protected $url; 

  /**
  * @var The actual requested URL for this principal, when the request was for /principals/... or such
  */
  protected $original_request_url; 
  
  /**
   * Whether this was retrieved using an e-mail address
   * @var boolean
   */
  protected $by_email;  

  /**
   * If we're using memcached this is the namespace we'll put stuff in
   * @var unknown_type
   */
  private $cacheNs;
  private $cacheKey;

  protected $collections;
  protected $dead_properties;
  protected $default_calendar;
    
  function __construct( $type, $value, $use_cache=true ) {
    global $c, $session;

    $this->exists = false;
    $this->by_email = false;
    $this->original_request_url = null;

    switch( $type ) {
      case 'path':
        $type = 'username';
        $value = $this->usernameFromPath($value);
        break;
      case 'dav_name':
        $type = 'username';
        $value = substr($value, 1, -1);
        break;
    }

    
    $cache = new AwlCache();
    if ( $use_cache && isset($session->principal_id) ) {
      switch ( $type ) {
        case 'user_no':
          $this->user_no = $value;
          if ( isset(self::$byUserno[$value]) ) {
            $type = 'username';
            $value = self::$byUserno[$value];
          }
          break;
        case 'principal_id':
          $this->principal_id = $value;
          if ( isset(self::$byId[$value]) ) {
            $type = 'username';
            $value = self::$byId[$value];
          }
          break;
        case 'email':
          $this->by_email = true;
          $this->email = $value;
          if ( isset(self::$byEmail[$value]) ) {
            $type = 'username';
            $value = self::$byEmail[$value];
          }
          break;
        case 'username':
          break;
        default:
          throw new Exception('Can only retrieve a Principal by user_no,principal_id,username or email address');
      }

      if ( $type == 'username' ) {
        $this->username = $value;
        $this->dav_name = '/'.$value.'/';
        $this->url = ConstructURL( $this->dav_name, true );
        $this->cacheNs = 'principal-/'.$value.'/';
        $this->cacheKey = 'p-'.$session->principal_id;
        $row = $cache->get('principal-/'.$value.'/', 'p-'.$session->principal_id );
        if ( $row !== false ) {
          self::$byId[$row->principal_id]   = $row->username;
          self::$byUserno[$row->user_no]    = $row->username;
          self::$byEmail[$row->email]       = $row->username;
          $this->assignRowValues($row);
          $this->url = ConstructURL( $this->dav_name, true );
          $this->exists = true;
          return $this;
        }
      }
    }

    $sql = 'SELECT *, ';
    if ( isset($session->principal_id) ) {
      $sql .= 'pprivs(:session_principal::int8,principal_id,:scan_depth::int) AS privileges ';
      $params = array( ':session_principal' => $session->principal_id, ':scan_depth' => $c->permission_scan_depth );
    }
    else {
      $sql .= '0::BIT(24) AS privileges ';
      $params = array( );
    }
    $sql .= 'FROM dav_principal WHERE ';
    switch ( $type ) {
      case 'username':
        $sql .= 'lower(username)=lower(:param)';
        break;
      case 'user_no':
        $sql .= 'user_no=:param';
        break;
      case 'principal_id':
        $sql .= 'principal_id=:param';
        break;
      case 'email':
        $this->by_email = true;
        $sql .= 'lower(email)=lower(:param)';
        break;
    }
    $params[':param'] = $value;
  
    $qry = new AwlQuery( $sql, $params );
    if ( $qry->Exec('Principal',__LINE__,__FILE__) && $qry->rows() == 1 && $row = $qry->Fetch() ) {
      $this->exists = true;
      if ( isset($session->principal_id) ) {
        self::$byId[$row->principal_id]   = $row->username;
        self::$byUserno[$row->user_no]    = $row->username;
        self::$byEmail[$row->email]       = $row->username;
        if ( !isset($this->cacheNs) ) {
          $this->cacheNs = 'principal-'.$row->dav_name;
          $this->cacheKey = 'p-'.$session->principal_id;
        }
      }
      $this->assignRowValues($row);
      $this->url = ConstructURL( $this->dav_name, true );
      $row = $cache->set($this->cacheNs, $this->cacheKey, $row, 864000 );
      return $this;
    }

    if ( $type == 'username' && $value == 'unauthenticated' ) {
      $this->assignGuestValues();
    }
  }

  /**
   * This will allow protected properties to be referenced for retrieval, but not
   * referenced for update.
   * @param $property
   */
  public function __get( $property ) {
    return $this->{$property};      
  }

  
  /**
   * This will allow protected properties to be examined for whether they are set
   * without making them writable.  PHP 5.1 or later only.
   * @param $property
   */
  public function __isset( $property ) {
    return isset($this->{$property});      
  }

  private function assignGuestValues() {
    $this->user_no = -1;
    $this->exists = false;
    $this->username = translate('unauthenticated');
    $this->fullname = $this->displayname = translate('Unauthenticated User');
    $this->email = false;
    $this->is_principal = true;
    $this->is_calendar = false;
    $this->principal_id = -1;
    $this->dav_name = '/';
    $this->privileges = $this->default_privileges = 0;    
  }

  private function assignRowValues( $db_row ) {
    foreach( $db_row AS $k => $v ) {
      $this->{$k} = $v;      
    }
  }  

  public function Exists() {
    return $this->exists;
  }

  
  public function byEmail() {
    return $this->by_email;
  }

  
  /**
  * Work out the username, based on elements of the path.
  * @param string $path The path to be used.
  * @param array $options The request options, controlling whether e-mail paths are allowed.
  */
  private function usernameFromPath( $path ) {
    global $session, $c;

    if ( $path == '/' || $path == '' ) {
      dbg_error_log( 'Principal', 'No useful path split possible' );
      return $session->username;
    }

    $path_split = explode('/', $path );
    @dbg_error_log( 'Principal', 'Path split into at least /// %s /// %s /// %s', $path_split[1], $path_split[2], $path_split[3] );

    $username = $path_split[1];
    if ( $path_split[1] == 'principals' && isset($path_split[3]) ) {
      $username = $path_split[3];
      $this->original_request_url = $path;      
    }
    if ( substr($username,0,1) == '~' ) {
      $username = substr($username,1);      
      $this->original_request_url = $path;      
    }

    if ( isset($c->allow_by_email) && $c->allow_by_email && preg_match( '#^(\S+@\S+[.]\S+)$#', $username) ) {
      // This might seem inefficient, but we cache the result, so the second time will not read from the DB
      $p = new Principal('email',$username);
      $username = $p->username;
      $this->by_email = true;
    }
    return $username;
  }

  
  /**
  * Return the username
  * @return string The username
  */
  function username() {
    return (isset($this->username)?$this->username:false);
  }


  /**
  * Set the username - but only if the record does not yet exist!
  * @return string The username
  */
  function setUsername($new_username) {
    if ( $this->exists ) return false;
    $this->username = $new_username;
    return $this->username;
  }


  /**
  * Return the user_no
  * @return int The user_no
  */
  function user_no() {
    return (isset($this->user_no)?$this->user_no:false);
  }


  /**
  * Return the principal_id
  * @return string The principal_id
  */
  function principal_id() {
    return (isset($this->principal_id)?$this->principal_id:false);
  }


  /**
  * Return the email
  * @return string The email
  */
  function email() {
    return (isset($this->email)?$this->email:false);
  }


  /**
  * Return the partial path representing this principal
  * @return string The dav_name
  */
  function dav_name() {
    if ( !isset($this->dav_name) ) {
      if ( !isset($this->username) ) {
        throw new Exception('Can\'t calculate dav_name for unknown username');
      }
      $this->dav_name = '/'.$this->username.'/';
    }
    return $this->dav_name;
  }

  
  /**
  * Ensure the principal's dead properties are loaded
  */
  protected function FetchDeadProperties() {
    if ( isset($this->dead_properties) ) return;

    $this->dead_properties = array();
    $qry = new AwlQuery('SELECT property_name, property_value FROM property WHERE dav_name= :dav_name', array(':dav_name' => $this->dav_name()) );
    if ( $qry->Exec('Principal') ) {
      while ( $property = $qry->Fetch() ) {
        $this->dead_properties[$property->property_name] = $property->property_value;
      }
    }
  }
  
  
  /**
  * Fetch the list of collections for this principal
  * @return string The internal dav_name for the home_calendar, or null if there is none
  */
  protected function FetchCollections() {
    if ( isset($this->collections) ) return;
    
    $this->collections = array();
    $qry = new AwlQuery('SELECT * FROM collection WHERE user_no= :user_no', array(':user_no' => $this->user_no()) );
    if ( $qry->Exec('Principal') ) {
      while ( $collection = $qry->Fetch() ) {
        $this->collections[$collection->dav_name] = $collection;
      }
    }
  }

  
  /**
  * Return the default calendar for this principal
  * @return string The internal dav_name for the home_calendar, or false if there is none
  */
  function default_calendar() {
    global $c;
    
    if ( !isset($this->default_calendar) ) {
      $this->default_calendar = false;
      if ( !isset($this->dead_properties) ) $this->FetchDeadProperties();
      if ( isset($this->dead_properties['urn:ietf:params:xml:ns:caldav:schedule-default-calendar-URL']) ) {
        $this->default_calendar = $this->dead_properties['urn:ietf:params:xml:ns:caldav:schedule-default-calendar-URL'];
      }
      else {
        if ( !isset($this->collections) ) $this->FetchCollections();
        $dav_name = $this->dav_name().$c->home_calendar_name.'/';
        if ( isset($this->collections[$dav_name]) && ($this->collections[$dav_name]->is_calendar == 't') ) {
              $this->default_calendar = $dav_name;
        }
        else {
          $dav_name = $this->dav_name().'home/';
          if ( isset($this->collections[$dav_name]) && ($this->collections[$dav_name]->is_calendar == 't') ) {
            $this->default_calendar = $dav_name;
          }
          else {
            foreach( $this->collections AS $dav_name => $collection ) {
              if ( $collection->is_calendar == 't' ) {
                $this->default_calendar = $dav_name;
              }
            }
          }
        }
      }
    }
    return $this->default_calendar;
  }

  
  /**
  * Return the URL for this principal
  * @param string $type The type of URL we want (the principal, by default)
  * @param boolean $internal Whether an internal reference is requested
  * @return string The principal-URL
  */
  public function url($type = 'principal', $internal=false ) {
    global $c;
    
    if ( $internal )
      $result = $this->dav_name();
    else {
      if ( isset($this->original_request_url) && $type == 'principal' )
        $result = $this->original_request_url;
      else
        $result = $this->url; 
    }

    switch( $type ) {
      case 'principal':          break;
      case 'schedule-default-calendar':  $result = $this->default_calendar(); break;
      case 'schedule-inbox':     $result .= '.in/';        break;
      case 'schedule-outbox':    $result .= '.out/';       break;
      case 'dropbox':            $result .= '.drop/';      break;
      case 'notifications':      $result .= '.notify/';    break;
      default:
        fatal('Unknown internal URL type "'.$type.'"');
    }
    return $result;
  }

  
  public function internal_url($type = 'principal' ) {
    return $this->url($type,true);
  }

  
  public function unCache() {
    if ( !isset($this->cacheNs) ) return; 
    $cache = new AwlCache();
    $cache->delete($this->cacheNs, null );
  }

  
  private function Write( $field_values, $inserting=true ) {
    global $c;
    if ( is_array($field_values) ) $field_values = (object) $field_values;

    if ( !isset($field_values->{'user_active'}) ) {
      if ( isset($field_values->{'active'}) )
        $field_values->{'user_active'} = $field_values->{'active'};
      else if ( $inserting )
        $field_values->{'user_active'} = true;
    }
    if ( !isset($field_values->{'modified'}) && isset($field_values->{'updated'}) )
      $field_values->{'modified'} = $field_values->{'updated'};
    if ( !isset($field_values->{'type_id'}) && $inserting )
      $field_values->{'type_id'} = 1; // Default to 'person'
    if ( !isset($field_values->{'default_privileges'}) && $inserting )
      $field_values->{'default_privileges'} = decbin(privilege_to_bits($c->default_privileges));
    
      
    $sql = '';
    if ( $inserting ) {
      $insert_fields = array();
      $param_names = array();
    }
    else {
      $update_list = array();
    }
    $sql_params = array();
    foreach( self::updateableFields() AS $k ) {
      if ( !isset($field_values->{$k}) && !isset($this->{$k}) ) continue;
      if ( $inserting ) {
        $insert_fields[] = $k;
        $param_names[] = ':'.$k;
      }
      else {
        $update_list[] = $k.'=:'.$k;
      }
      $sql_params[':'.$k] = (isset($field_values->{$k}) ? $field_values->{$k} : $this->{$k});  
    }

    if ( $inserting && isset(self::$db_mandatory_fields) ) {
      foreach( self::$db_mandatory_fields AS $k ) {
        if ( !isset($sql_params[':'.$k]) ) {
          throw new Exception( get_class($this).'::Create: Mandatory field "'.$k.'" is not set.');
        } 
      }
      $sql = 'INSERT INTO '.self::$db_tablename.' ('.implode(',',$insert_fields).') VALUES('.implode(',',$param_names).')';
    }
    else {
      $sql = 'UPDATE '.self::$db_tablename.' SET '.implode(',',$update_list);
      $sql .= ' WHERE principal_id=:principal_id';
      $sql_params[':principal_id'] = $this->principal_id; 
    }

    $qry = new AwlQuery($sql, $sql_params);
    if ( $qry->Exec('Principal',__FILE__,__LINE__) ) {
      $this->unCache();
      $new_principal = new Principal('username', $sql_params[':username']);
      foreach( $new_principal AS $k => $v ) {
        $this->{$k} = $v;
      }
    }
  }

  
  public function Create( $field_values ) {
    $this->Write($field_values, true);
  }

  public function Update( $field_values ) {
    if ( !$this->Exists() ) {
      throw new Exception( get_class($this).'::Create: Attempting to update non-existent record.');
    }
    $this->Write($field_values, false);
  }

  static public function cacheFlush( $where, $whereparams=array() ) {
    $cache = new AwlCache();
    if ( !$cache->isActive() ) return;
    $qry = new AwlQuery('SELECT dav_name FROM dav_principal WHERE '.$where, $whereparams );
    if ( $qry->Exec('Principal',__FILE__,__LINE__) ) {
      while( $row = $qry->Fetch() ) {
        $cache->delete('principal-'.$row->dav_name, null);
      }
    }
  }  

  static public function cacheDelete( $type, $value ) {
    $cache = new AwlCache();
    if ( !$cache->isActive() ) return;
    if ( $type == 'username' ) {
      $value = '/'.$value.'/';
    }
    $cache->delete('principal-'.$value, null);
  }  
}
