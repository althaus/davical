<?php
require_once("always.php");
require_once("RSCDSSession.php");
$session->LoginRequired();

require_once("interactive-page.php");


  require_once("classBrowser.php");
  $c->stylesheets[] = "css/browse.css";

  $c->page_title = "System Roles";
  $browser = new Browser($c->page_title);

  $browser->AddColumn( 'role_no', 'No.', '', '##role_link##' );
  $browser->AddColumn( 'role_name', 'Name' );
  $browser->AddHidden( 'role_link', "'<a href=\"/role.php?role_no=' || role_no || '\">' || role_no || '</a>'" );

  $browser->SetJoins( "roles" );

  if ( isset( $_GET['o']) && isset($_GET['d']) ) {
    $browser->AddOrder( $_GET['o'], $_GET['d'] );
  }
  else
    $browser->AddOrder( 'role_no', 'A' );

  $browser->RowFormat( "<tr onMouseover=\"LinkHref(this,1);\" title=\"Click to Display Role Detail\" class=\"r%d\">\n", "</tr>\n", '#even' );
  $browser->DoQuery();


//  if ( $session->AllowedTo("Admin") )
    $role_menu->AddOption("New Role","/role.php?create","Add a new role", false, 10);

  $active_menu_pattern = "#^/role#";

include("page-header.php");

echo $browser->Render();
include("page-footer.php");
?>