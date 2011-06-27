<?PHP
/**
* Functions for managing external BIND resources
*
*
* @package   davical
* @subpackage   external-bind
* @author    Rob Ostensen <rob@boxacle.net>
* @copyright Rob Ostensen
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

function create_external ( $path,$is_calendar,$is_addressbook )
{
  global $request;
  $resourcetypes = '<DAV::collection/>';
  if ($is_calendar) $resourcetypes .= '<urn:ietf:params:xml:ns:caldav:calendar/>';
  $qry = new AwlQuery();
  if ( ! $qry->QDo( 'INSERT INTO collection ( user_no, parent_container, dav_name, dav_etag, dav_displayname,
                                 is_calendar, is_addressbook, resourcetypes, created, modified )
              VALUES( :user_no, :parent_container, :dav_name, :dav_etag, :dav_displayname,
                      :is_calendar, :is_addressbook, :resourcetypes, current_timestamp, current_timestamp )',
           array(
              ':user_no'          => $request->user_no,
              ':parent_container' => '/.external/',
              ':dav_name'         => $path,
              ':dav_etag'         => md5($request->user_no. $path),
              ':dav_displayname'  => $path,
              ':is_calendar'      => ($is_calendar ? 't' : 'f'),
              ':is_addressbook'   => ($is_addressbook ? 't' : 'f'),
              ':resourcetypes'    => $resourcetypes
            ) ) ) {
    $request->DoResponse( 500, translate('Error writing calendar details to database.') );
  }
}

function fetch_external ( $bind_id, $min_age )
{
  $sql = 'SELECT collection.*, collection.dav_name as path, dav_binding.external_url as external_url FROM dav_binding LEFT JOIN collection ON (collection.collection_id=bound_source_id) WHERE bind_id = :bind_id';
  $params = array( ':bind_id' => $bind_id );
  if ( strlen ( $min_age ) > 2 ) {
    $sql .= ' and collection.modified + interval :interval > NOW()';
    $params[':interval'] = $min_age;
  }
  $qry = new AwlQuery( $sql, $params );
  if ( $qry->Exec('DAVResource') && $qry->rows() > 0 && $row = $qry->Fetch() ) {
    $curl = curl_init ( $row->external_url );
    curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, true );
    $ics = curl_exec ( $curl );
    curl_close ( $curl );
    if ( is_string ( $ics ) && strlen ( $ics ) > 20 ) {
      $qry = new AwlQuery( 'UPDATE collection SET modified=NOW() where collection_id = :cid', array ( ':cid' => $row->collection_id ) );
      $qry->Exec('DAVResource');  
      require_once ( 'caldav-PUT-functions.php');
      import_collection ( $ics , $row->user_no, $row->path, 'External Fetch' , false ) ;
      return true;
    }
  }
  return false;
}

function update_external ( $request )
{
  global $c;
  if ( $c->external_refresh < 1 )
    return ;
  $sql = 'SELECT bind_id from dav_binding LEFT JOIN collection ON (collection.collection_id=bound_source_id) collection where dav_name = :dav_name and collection.modified + interval :interval < NOW()';
  $qry = new AwlQuery( $sql, array ( ':dav_name' => $request->dav_name, ':interval' => $c->external_refresh + ' minutes' ) );
  if ( $qry->Exec('DAVResource') && $qry->rows() > 0 && $row = $qry->Fetch() ) {
    if ( $row->bind_id != 0 )
      fetch_external ( $row->bind_id );
  }
}
