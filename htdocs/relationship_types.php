<?php
require_once("always.php");
require_once("RSCDSSession.php");
$session->LoginRequired();

require_once("interactive-page.php");

  require_once("DataEntry.php");
  require_once("DataUpdate.php");
  require_once("classBrowser.php");
  $c->stylesheets[] = "css/browse.css";

  if ( ($session->AllowedTo("Admin") || $session->AllowedTo("Support")) &&
       !$session->just_logged_in && (isset($_POST['submit']) || isset($_GET['action'])) ) {
    $action = (isset($_POST['submit']) ? $_POST['submit'] : $_GET['action'] );
    dbg_error_log( "relationship_types", " action type is %s.", $action );
    $rt_id = intval($_GET['rt_id']);
    $rt = new DBRecord();
    $rt->Initialise('relationship_type', array( 'rt_id' => $rt_id ) );
    switch( strtolower($action) ) {
      case 'delete':
        $qry = new PgQuery("DELETE FROM relationship_type WHERE rt_id = $rt_id;");
        if ( $qry->Exec() ) {
          $c->messages[] = "Relationship Type Deleted.";
        }
        else {
          $c->messages[] = "Database Error.";
          if ( preg_match("/violates foreign key constraint/", $qry->errorstring ) ) {
            $c->messages[] = "That relationship type is being used. See ##RelationshipTypeUsed##";
          }
        }
        break;

      case 'add':
        $rt->PostToValues();
        if ( $rt->Write() ) {
          $c->messages[] = "Relationship Type Added.";
        }
        else {
          $c->messages[] = "Database Error.";
        }
        break;

      case 'edit':
        $rt->PostToValues();
        if ( $rt->Write() ) {
          $c->messages[] = "Relationship Type Updated.";
        }
        else {
          $c->messages[] = "Database Error.";
        }
        break;
    }
  }

  $c->page_title = "Relationship Types";
  $browser = new Browser($c->page_title);

  $browser->AddColumn( 'rt_id', 'Id' );
  $browser->AddColumn( 'rt_name', 'Name' );
  $browser->AddColumn( 'rt_isgroup', 'To Group?', '', '', "CASE WHEN rt_isgroup THEN 'Yes' ELSE 'No' END"  );
  $browser->AddColumn( 'confers', 'Rights' );
  $browser->AddColumn( 'prefix_match', "Prefix" );
  $browser->AddColumn( 'action', "Action", "", "", "'<a href=\"/relationship_types.php?action=delete&rt_id=' || rt_id || '\">Delete</a>'" );

  $browser->SetJoins( 'relationship_type' );

  if ( isset( $_GET['o']) && isset($_GET['d']) ) {
    $browser->AddOrder( $_GET['o'], $_GET['d'] );
  }
  else
    $browser->AddOrder( 'rt_name', 'A' );

  $browser->RowFormat( "<tr class=\"r%d\">\n", "</tr>\n", '#even' );
  $browser->DoQuery();

  $rt_name_field = new EntryField( "text", "rt_name",
                            array("title" => "Enter the name for this resource type",
                                  "size" => "20") );

  $rt_isgroup_field = new EntryField( "checkbox", "rt_isgroup",
                            array("title" => "Is the target of this relationship a group of access rights?") );

  $confers_field = new EntryField( "text", "confers",
                            array("title" => "Is this access read ('R') or Read and Write ('RW')?",
                                  "size" => "5") );

  $prefix_match_field = new EntryField( "text", "hprefix_match",
                            array("title" => "Restrict access to prefixes matching this pattern",
                                  "size" => "15") );

  $browser->AddRow( array(
                  'rt_id' => 'new',
                  'rt_name' => $rt_name_field->Render(),
                  'rt_link' => $rt_name_field->Render(),
                  'rt_isgroup' => $rt_isgroup_field->Render(),
                  'confers' => $confers_field->Render(),
                  'prefix_match' => $prefix_match_field->Render(),
                  'action' => '<input type="submit" name="submit" value="Add" class="fsubmit">'
                  ) );

  $active_menu_pattern = "#^/relationship#";

include("page-header.php");

echo "<form method='post' enctype='multipart/form-data' action='$REQUEST_URI'>";
echo $browser->Render();
echo "</form>";

include("page-footer.php");
?>