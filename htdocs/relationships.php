<?php
require_once("always.php");
require_once("RSCDSSession.php");
$session->LoginRequired();

require_once("interactive-page.php");


  require_once("classBrowser.php");
  $c->stylesheets[] = "css/browse.css";

  $c->page_title = "User Relationships";
  $browser = new Browser($c->page_title);

  $browser->AddColumn( 'from_user', 'From', '', '##from_user_link##' );
  $browser->AddColumn( 'from_name', 'Name', '', '', 'ufrom.fullname' );
  $browser->AddHidden( 'from_user_link', "'<a href=\"/user.php?user_no=' || from_user || '\">' || from_user || '</a>'" );
  $browser->AddColumn( 'to_user', 'From', '', '##to_user_link##' );
  $browser->AddColumn( 'to_name', 'Name', '', '', 'uto.fullname' );
  $browser->AddHidden( 'to_user_link', "'<a href=\"/user.php?user_no=' || to_user || '\">' || to_user || '</a>'" );

  $browser->SetJoins( 'relationship JOIN usr ufrom ON (ufrom.user_no = from_user) JOIN usr uto ON (uto.user_no = to_user) ' );

  if ( isset( $_GET['o']) && isset($_GET['d']) ) {
    $browser->AddOrder( $_GET['o'], $_GET['d'] );
  }
  else
    $browser->AddOrder( 'from_user', 'A' );

  $browser->RowFormat( "<tr onMouseover=\"LinkHref(this,1);\" title=\"Click to Display Role Detail\" class=\"r%d\">\n", "</tr>\n", '#even' );
  $browser->DoQuery();


  $user_menu->AddOption("Relationships","/relationship.php?user_no=$user_no","Relationships for this user", false, 10);

  $active_menu_pattern = "#^/relationship#";

include("page-header.php");

echo $browser->Render();

include("page-footer.php");
?>