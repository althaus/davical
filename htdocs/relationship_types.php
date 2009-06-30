<?php
require_once("../inc/always.php");
require_once("DAViCalSession.php");
$session->LoginRequired();

require_once("interactive-page.php");

  require_once("DataEntry.php");
  require_once("DataUpdate.php");
  require_once("classBrowser.php");
  $c->stylesheets[] = "$c->base_url/css/browse.css";

  $confirmation_required = false;
  if ( ($session->AllowedTo("Admin") || $session->AllowedTo("Support")) &&
       !$session->just_logged_in && (isset($_POST['submit']) || isset($_GET['action'])) ) {
    $action = (isset($_POST['submit']) ? $_POST['submit'] : $_GET['action'] );
    dbg_error_log( "relationship_types", " action type is %s.", $action );
    $rt_id = intval($_GET['rt_id']);
    $rt = new DBRecord();
    $rt->Initialise('relationship_type', array( 'rt_id' => $rt_id ) );
    switch( strtolower($action) ) {
      case 'delete':
        if ( $session->CheckConfirmationHash('GET', 'confirm') ) {
          $qry = new PgQuery("DELETE FROM relationship_type WHERE rt_id = $rt_id;");
          if ( $qry->Exec() ) {
            $c->messages[] = i18n("Relationship Type Deleted.");
          }
          else {
            $c->messages[] = i18n("Database Error.");
            if ( preg_match("/violates foreign key constraint/", $qry->errorstring ) ) {
              $c->messages[] = i18n("That relationship type is being used. See ##RelationshipTypeUsed##");
            }
          }
        }
        else {
          $c->messages[] = i18n("Please Confirm Deletion");
          $confirmation_required = true;
          $confirmation_hash = $session->BuildConfirmationHash('GET', 'confirm');
        }
        break;

      case 'add':
        $rt->PostToValues();
        if ( $rt->Write() ) {
          $c->messages[] = i18n("Relationship Type Added.");
        }
        else {
          $c->messages[] = i18n("Database Error.");
        }
        break;

    }
  }

  $c->page_title = translate("Relationship Types");
  $browser = new Browser($c->page_title);

  $browser->AddColumn( 'rt_id', translate('Id') );
  $browser->AddColumn( 'rt_name', translate('Name') );
  $browser->AddColumn( 'confers', translate('Rights') );
  $browser->AddColumn( 'action', translate("Action"), "", "", "'<a href=\"$c->base_url/relationship_types.php?action=delete&rt_id=' || rt_id || '\">".translate("Delete")."</a>'" );
  $browser->SetTranslatable( array('rt_name') );

  $browser->SetJoins( 'relationship_type' );

  if ( isset( $_GET['o']) && isset($_GET['d']) ) {
    $browser->AddOrder( $_GET['o'], $_GET['d'] );
  }
  else
    $browser->AddOrder( 'rt_name', 'A' );

  $browser->RowFormat( "<tr class=\"r%d\">\n", "</tr>\n", '#even' );
  $browser->DoQuery();

  $rt_name_field = new EntryField( "text", "rt_name",
                            array("title" => translate("Enter the name for this resource type"),
                                  "size" => "20") );

  $confers_field = new EntryField( "select", "confers",
                            array("title" => translate("Is this access read ('R') or Read and Write ('RW')?"),
                                  "size" => "1",
                                 '_A' => translate('All'),
                                 '_R' => translate('Read'),
                                 '_F' => translate('FreeBusy'),
                                 '_W' => translate('Write'),
                                 '_RW' => translate('ReadWrite')) );

  $browser->AddRow( array(
                  'rt_id' => 'new',
                  'rt_name' => $rt_name_field->Render(),
                  'rt_link' => $rt_name_field->Render(),
                  'confers' => $confers_field->Render(),
                  'action' => '<input type="submit" name="submit" value="'.translate("Add").'" class="fsubmit">'
                  ) );

  $active_menu_pattern = "#^$c->base_url/relationship#";

include("page-header.php");

if ( $confirmation_required ) {
  printf('<p><a href="%s&%s">%s</a></p>', $_SERVER['REQUEST_URI'], $confirmation_hash, translate("Confirm Deletion of the Relationship Type"));
}

printf( '<form method="post" enctype="multipart/form-data" action="%s">', $_SERVER['REQUEST_URI']);
echo $browser->Render();
echo "</form>";

include("page-footer.php");
