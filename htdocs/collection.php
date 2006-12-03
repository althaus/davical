<?php
require_once("../inc/always.php");
require_once("RSCDSSession.php");
$session->LoginRequired();

require_once("interactive-page.php");


  require_once("classBrowser.php");
  $c->stylesheets[] = "css/browse.css";

  $c->page_title = translate("Collection Contents");
  $browser = new Browser($c->page_title);

  $browser->AddHidden( 'resource_link', "'<a href=\"$c->base_url/caldav.php' || caldav_data.dav_name || '\">' || caldav_type || '</a>'" );
  $browser->AddColumn( 'caldav_type', translate('Type'), '', '##resource_link##' );
  $browser->AddColumn( 'dtstart', translate('Start'), '', '', "to_char(dtstart,'YYYY-MM-DD HH24:MI')" );
  $browser->AddColumn( 'dtend', translate('Finish'), '', '', "to_char(dtend,'YYYY-MM-DD HH24:MI')" );
  $browser->AddColumn( 'summary', translate('Summary') );
  $browser->AddColumn( 'rrule', translate('Repeat Rule') );

  $browser->SetJoins( 'caldav_data JOIN calendar_item USING ( user_no, dav_name ) ' );
  if ( isset($_GET['user_no']) ) {
    $browser->SetWhere( "user_no=" . intval($_GET['user_no']) );
  }
  if ( isset($_GET['dav_name']) ) {
    $browser->SetWhere( "dav_name ~ " . qpg("^".$_GET['dav_name']."[^/]+$") );
  }

  if ( isset( $_GET['o']) && isset($_GET['d']) ) {
    $browser->AddOrder( $_GET['o'], $_GET['d'] );
  }
  else
    $browser->AddOrder( 'dav_name', 'A' );

  $browser->RowFormat( "<tr class=\"r%d\">\n", "</tr>\n", '#even' );

  $browser->DoQuery();


include("page-header.php");

echo $browser->Render();

include("page-footer.php");
?>