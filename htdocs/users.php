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
require_once("../inc/always.php");
require_once("DAViCalSession.php");
$session->LoginRequired();

require_once("interactive-page.php");

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

  require_once("classBrowser.php");
  $c->stylesheets[] = "css/browse.css";
  $c->scripts[] = "js/browse.js";

  $browser = new Browser(translate("Calendar Users"));

  $browser->AddColumn( 'user_no', translate('No.'), 'right', '##user_link##' );
  $browser->AddColumn( 'username', translate('Name') );
  $browser->AddHidden( 'user_link', "'<a href=\"$c->base_url/usr.php?user_no=' || user_no || '\">' || user_no || '</a>'" );
  $browser->AddColumn( 'fullname', translate('Full Name') );
  $browser->AddColumn( 'email', translate('EMail') );
  $browser->AddColumn( 'relations', translate('Relationships'), '', '', 'relationship_list(user_no)', '', '', 'translate_relationship_list' );
  $browser->AddOrder( 'username', 'A' );

  $browser->SetJoins( 'usr' );

  if ( isset( $_GET['o']) && isset($_GET['d']) ) {
    $browser->AddOrder( $_GET['o'], $_GET['d'] );
  }
  else
    $browser->AddOrder( 'user_no', 'A' );

  if ( $c->enable_row_linking ) {
    $browser->RowFormat( "<tr onMouseover=\"LinkHref(this,1);\" title=\"".translate("Click to display user details")."\" class=\"r%d\">\n", "</tr>\n", '#even' );
  }
  else {
    $browser->RowFormat( "<tr class=\"r%d\">\n", "</tr>\n", '#even' );
  }
  $browser->DoQuery();

  $c->page_title = translate("Calendar Users");

  if ( $session->AllowedTo("Admin") )
    $user_menu->AddOption(translate("New User"),"$c->base_url/usr.php?create",translate("Add a new user"), false, 10);

  $active_menu_pattern = "#^$c->base_url/user#";

include("page-header.php");

echo $browser->Render();

include("page-footer.php");
