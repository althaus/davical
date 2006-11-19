<?php
require_once("always.php");
require_once("RSCDSSession.php");
$session->LoginRequired();

require_once("interactive-page.php");


  require_once("classBrowser.php");
  $c->stylesheets[] = "css/browse.css";
  $c->scripts[] = "js/browse.js";

  $browser = new Browser(translate("Calendar Users"));

  $browser->AddColumn( 'user_no', translate('No.'), 'right', '##user_link##' );
  $browser->AddColumn( 'username', translate('Name') );
  $browser->AddHidden( 'user_link', "'<a href=\"/user.php?user_no=' || user_no || '\">' || user_no || '</a>'" );
  $browser->AddColumn( 'fullname', translate('Full Name') );
  $browser->AddColumn( 'email', translate('EMail') );

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
    $user_menu->AddOption(translate("New User"),"/user.php?create",translate("Add a new user"), false, 10);

  $active_menu_pattern = "#^/user#";

include("page-header.php");

echo $browser->Render();

include("page-footer.php");
?>