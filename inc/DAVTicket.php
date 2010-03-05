<?php
/**
* An object representing a DAV 'ticket'
*
* @package   davical
* @subpackage   DAVTicket
* @author    Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Morphoss Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

require_once('AwlQuery.php');


/**
* A class for things to do with a DAV Ticket
*
* @package   davical
*/
class DAVTicket
{
  /**
  * @var The ID of the ticket
  */
  private $ticket_id;

  /**
  * @var dav_name
  */
  private $dav_name;

  /**
  * @var The collection_id of/containing the ticketed resource
  */
  private $target_collection_id;

  /**
  * @var The resource_id of the ticketed resource, if it is not a collection
  */
  private $target_resource_id;

  /**
  * @var The expiry of the ticket
  */
  private $expiry;

  /**
  * @var The ID of the principal who owns this ticket
  */
  private $dav_owner_id;

  /**
  * @var A bit mask representing the privileges provided by this ticket
  */
  private $privileges;

  /**
  * @var A bit mask representing the privileges granted to the ticket owner to the collection (or container of this) resource.
  */
  private $grantor_collection_privileges;

  /**
  * Constructor
  * @param string $ticket_id
  */
  function __construct( $ticket_id ) {
    global $c;

    $this->dav_name             = null;
    $this->target_collection_id = null;
    $this->target_resource_id   = null;
    $this->expiry               = null;
    $this->expired              = true;
    $this->dav_owner_id         = null;
    $this->ticket_id            = $ticket_id;
    $this->privileges           = 0;
    $this->grantor_collection_privileges = 0;

    $qry = new AwlQuery(
        'SELECT access_ticket.*, collection.dav_name, (access_ticket.expiry >= current_timestamp) AS expired,
                path_privs(access_ticket.dav_owner_id,collection.dav_name,:scan_depth) AS grantor_collection_privileges
           FROM access_ticket JOIN collection ON (target_collection_id = collection_id)
          WHERE ticket_id = :ticket_id',
        array(':ticket_id' => $ticket_id, ':scan_depth' => $c->permission_scan_depth)
    );
    if ( $qry->Exec('DAVTicket',__LINE__,__FILE__) && $qry->rows() == 1 && $t = $qry->Fetch() && $t->expired === 'f' ) {
      foreach( $t AS $k => $v ) {
        $this->{$k} = $v;
      }
      $this->expired = false;
      $this->privileges = bindec($this->privileges);
      $this->grantor_collection_privileges = bindec($this->grantor_collection_privileges);
    }
    if ( isset($this->target_resource_id) ) {
      $qry = new AwlQuery( 'SELECT dav_name FROM caldav_data WHERE dav_id = :dav_id', array(':dav_id' => $this->target_resource_id ) );
      if ( $qry->Exec('DAVTicket',__LINE__,__FILE__) && $qry->rows() == 1 && $r = $qry->Fetch() ) {
        $this->dav_name = $r->dav_name;
      }
    }
  }


  function dav_name() {
    return $this->dav_name;
  }


  function privileges() {
    return ($this->privileges & $this->grantor_collection_privileges);
  }

}
