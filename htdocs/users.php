<?php
/**
* Display a list of all users
*
* @package   davical
* @subpackage   Admin
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
require_once('../inc/always.php');
require_once('DAViCalSession.php');
$session->LoginRequired();

require_once('interactive-page.php');

/**
* Translate relationship names in the relationship_list for each user. See
* the documentation for classBrowser::BrowserColumn for the definition of
* the hook function parameters.
*/
function translate_relationship_list( $value, $field, $row ) {
  global $relationship_names;
  foreach( $relationship_names AS $k => $v ) {
    $value = str_replace( $k, $v, $value );
  }
  return $value;
}

$relationship_names = array();
$qry = new PgQuery( 'SELECT rt_name FROM relationship_type' );
if ( $qry->Exec('users') && $qry->rows > 0 ) {
  while( $relationship = $qry->Fetch() ) {
    $relationship_names[$relationship->rt_name] = translate($relationship->rt_name);
  }
}

  require_once('classBrowser.php');
  $c->stylesheets[] = 'css/browse.css';
  $c->scripts[] = 'js/browse.js';

  $browser = new Browser(translate('Calendar Users'));

  $browser->AddColumn( 'user_no', translate('No.'), 'right', '##user_link##' );
  $browser->AddColumn( 'username', translate('Name') );
  $browser->AddHidden( 'user_link', "'<a href=\"$c->base_url/usr.php?user_no=' || user_no || '\">' || user_no || '</a>'" );
  $browser->AddColumn( 'fullname', translate('Full Name') );
  $browser->AddColumn( 'email', translate('EMail') );
  $browser->AddColumn( 'relations', translate('Relationships'), '', '', 'relationship_list(user_no)', '', '', 'translate_relationship_list' );
  $browser->SetOrdering( 'username', 'A' );

  $browser->SetJoins( 'usr' );

  if ( $c->enable_row_linking ) {
    $browser->RowFormat( '<tr onMouseover="LinkHref(this,1);" title="'.translate('Click to display user details').'" class="r%d">', '</tr>', '#even' );
  }
  else {
    $browser->RowFormat( '<tr class="r%d">', '</tr>', '#even' );
  }
  $browser->DoQuery();

  $c->page_title = translate('Calendar Users');


include('page-header.php');

echo $browser->Render();

include('page-footer.php');
