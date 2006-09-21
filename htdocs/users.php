<?php
require_once("always.php");
require_once("RSCDSSession.php");
$session->LoginRequired();

require_once("interactive-page.php");


  require_once("classBrowser.php");
  $c->stylesheets[] = "css/browse.css";

  $browser = new Browser("Calendar Users");

  $browser->AddColumn( 'user_no', 'No.', '', '##user_link##' );
  $browser->AddColumn( 'username', 'Name' );
  $browser->AddHidden( 'user_link', "'<a href=\"/user.php?user_no=' || user_no || '\">' || user_no || '</a>'" );

  $browser->SetJoins( 'usr' );

  if ( isset( $_GET['o']) && isset($_GET['d']) ) {
    $browser->AddOrder( $_GET['o'], $_GET['d'] );
  }
  else
    $browser->AddOrder( 'user_no', 'A' );

  $browser->RowFormat( "<tr onMouseover=\"LinkHref(this,1);\" title=\"Click to Display Role Detail\" class=\"r%d\">\n", "</tr>\n", '#even' );
  $browser->DoQuery();

  $c->page_title = "Calendar Users";

//   if ( $session->AllowedTo("Support") )
    $user_menu->AddOption("New User","/user.php?create","Add a new user", false, 10);

  $active_menu_pattern = "#^/user#";

include("page-header.php");

echo $browser->Render();

include("page-footer.php");
?>