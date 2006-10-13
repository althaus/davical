<?php
/**
* User maintain / view with RSCDS specific associated tables
*
* @package   rscds
* @subpackage   RSCDSUser
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

require_once("User.php");
require_once("classBrowser.php");

$c->stylesheets[] = "css/browse.css";
$c->scripts[] = "js/browse.js";

/**
* A class for viewing and maintaining RSCDS User records
*
* @package   rscds
*/
class RSCDSUser extends User
{

  /**
  * Constructor - nothing fancy as yet.
  */
  function RSCDSUser( $id , $prefix = "") {
    parent::User( $id, $prefix );
  }

  /**
  * Render the form / viewer as HTML to show the user
  * @return string An HTML fragment to display in the page.
  */
  function Render($title = "" ) {
    $html = "";
    dbg_error_log("User", ":Render: type=$this->WriteType, edit_mode=$this->EditMode" );

    $ef = new EntryForm( $REQUEST_URI, $this->Values, $this->EditMode );
    $ef->NoHelp();  // Prefer this style, for the moment

    $html = '<div id="entryform">';
    if ( $title != "" ) {
      $html .= "<h1>$title</h1>\n";
    }

    if ( $ef->EditMode ) {
      $html .= $ef->StartForm( array("autocomplete" => "off" ) );
      if ( $this->user_no > 0 ) $html .= $ef->HiddenField( "user_no", $this->user_no );
    }

    $html .= "<table width=\"100%\" class=\"data\" cellspacing=\"0\" cellpadding=\"0\">\n";

    $html .= $this->RenderFields($ef,"");

    $html .= $this->RenderRoles($ef);

    $html .= $this->RenderRelationshipsFrom($ef);
    $html .= $this->RenderRelationshipsTo($ef);

    $html .= "</table>\n";
    $html .= "</div>";

    if ( $ef->EditMode ) {
      $html .= '<div id="footer">';
      $html .= $ef->SubmitButton( "submit", (("insert" == $this->WriteType) ? "Create" : "Update") );
      $html .= '</div>';
      $html .= $ef->EndForm();
    }

    return $html;
  }


  /**
  * Render the user's relationships to other users & resources
  *
  * @return string The string of html to be output
  */
  function RenderRelationshipsFrom( $ef, $title = "Relationships from this user" ) {
    global $session, $c;

    $browser = new Browser("");

    $browser->AddHidden( 'user_link', "'<a href=\"/user.php?user_no=' || user_no || '\">' || fullname || '</a>'" );
    $browser->AddColumn( 'rt_name', 'Relationship' );
    $browser->AddColumn( 'fullname', 'Linked To', 'left', '##user_link##' );
//    $browser->AddColumn( 'is_group', 'Group?', 'centre', '', "CASE WHEN rt_isgroup THEN 'Yes' ELSE 'No' END"  );
    $browser->AddHidden( 'confers', 'Confers' );
    $browser->AddColumn( 'email', 'EMail' );
    if ( $ef->EditMode ) { // && $session->AllowedTo("MaintainRelationships") ) {
      $browser->AddColumn( 'delete', 'Delete', 'centre', '', "'<a class=\"\" href=\"/user.php?action=delete_relationsip&from_user=$this->user_no&to_user=' || user_no || '\">Delete</a>'" );
    }

    $browser->SetJoins( 'relationship NATURAL JOIN relationship_type rt LEFT JOIN usr ON (to_user = user_no)' );
    $browser->SetWhere( "from_user = $this->user_no" );

    if ( isset( $_GET['o']) && isset($_GET['d']) ) {
      $browser->AddOrder( $_GET['o'], $_GET['d'] );
    }
    else
      $browser->AddOrder( 'rt_name', 'A' );

    $browser->RowFormat( "<tr onMouseover=\"LinkHref(this,1);\" title=\"Click to display that relationship\" class=\"r%d\">\n", "</tr>\n", '#even' );
    $browser->DoQuery();

    /**
    * Present an extra editable row at the bottom of the browse.
    */
    if ( $ef->EditMode ) { // && $session->AllowedTo("MaintainRelationships") ) {
      if ( isset($this->roles['Group Target']) ) {
        /**
        * We only allow individuals to link to group targets at this stage.
        */
        $group_target = 'AND NOT EXISTS (SELECT 1 FROM role_member WHERE role_no = 2 AND user_no=usr.user_no)';
      }
      $sql = <<<EOSQL
SELECT user_no, fullname FROM usr
 WHERE NOT EXISTS ( SELECT 0 FROM relationship
                     WHERE (to_user = usr.user_no AND from_user = $this->user_no)
                        OR (from_user = usr.user_no AND to_user = $this->user_no))
       $group_target
EOSQL;
      $person_selection = $ef->DataEntryField( "", "lookup", "relate_to",
                                array("title" => "Select the user, resource or group to relate this user to",
                                      "_null" => "--- select a user ".( isset($this->roles['Group Target']) ? '' : ', group ' ).'or resource ---',
                                      "_sql"  => $sql ) );

      $group_target = ( isset($this->roles['Group Target']) ? 'WHERE NOT rt_isgroup' : '' );
      $relationship_type_selection = $ef->DataEntryField( "", "lookup", "relate_as",
                                array("title" => "Select the type of relationship from this user",
                                      "_null" => "--- select a relationship type ---",
                                      "_sql"  => "SELECT rt_id, rt_name FROM relationship_type $group_target " ) );

      $browser->AddRow( array(
                      'rt_name' => $relationship_type_selection,  /* Since 'fullname' is formatted to display this value */
                      'user_link' => $person_selection,
                      'delete' => '<input type="submit" name="submit" value="Add Relationship" class="fsubmit">'
                     ) );
    }

    $html = ( $title == "" ? "" : $ef->BreakLine($title) );
    $html .= "<tr><td>&nbsp;</td><td>\n";
    $html .= $browser->Render();
    $html .= "</td></tr>\n";

    return $html;
  }

  /**
  * Render the user's relationships to other users & resources
  *
  * @return string The string of html to be output
  */
  function RenderRelationshipsTo( $ef, $title = "Relationships to this user" ) {
    global $session, $c;

    $browser = new Browser("");

    $browser->AddHidden( 'user_link', "'<a href=\"/user.php?user_no=' || user_no || '\">' || fullname || '</a>'" );
    $browser->AddColumn( 'fullname', 'Linked From', 'left', '##user_link##' );
    $browser->AddColumn( 'rt_name', 'Relationship' );
    $browser->AddColumn( 'is_group', 'Group?', 'centre', '', "CASE WHEN rt_isgroup THEN 'Yes' ELSE 'No' END"  );
    $browser->AddHidden( 'confers', 'Confers' );
    $browser->AddColumn( 'email', 'EMail' );

    $browser->SetJoins( 'relationship NATURAL JOIN relationship_type rt LEFT JOIN usr ON (from_user = user_no)' );
    $browser->SetWhere( "to_user = $this->user_no" );

    if ( isset( $_GET['o']) && isset($_GET['d']) ) {
      $browser->AddOrder( $_GET['o'], $_GET['d'] );
    }
    else
      $browser->AddOrder( 'rt_name', 'A' );

    $browser->RowFormat( "<tr onMouseover=\"LinkHref(this,1);\" title=\"Click to display that relationship\" class=\"r%d\">\n", "</tr>\n", '#even' );
    $browser->DoQuery();

    $html = ( $title == "" ? "" : $ef->BreakLine($title) );
    $html .= "<tr><td>&nbsp;</td><td>\n";
    $html .= $browser->Render();
    $html .= "</td></tr>\n";

    return $html;
  }

  /**
  * Validate the information the user submitted
  * @return boolean Whether the form data validated OK.
  */
  function Validate( ) {
    return parent::Validate( );
  }


  /**
  * Handle any unusual actions we might invent
  */
  function HandleAction( $action ) {
    global $session;

    switch( $action ) {
      case 'delete_relation':
        if ( $this->AllowedTo("Admin") ) {
          dbg_error_log("User",":HandleAction: Deleting relationship from %d to %d", $this->user_no, $_POST['to_user'] );
          $qry = new PgQuery("DELETE FROM relationship WHERE from_user=? AND to_user=?;", $this->user_no, $_POST['to_user'] );
          if ( $qry->Exec() ) {
            $c->messages[] = "Relationship deleted";
          }
          else {
            $c->messages[] = "There was an error writing to the database.";
            return false;
          }
        }
        return true;

      default:
        return false;
    }
  }


  /**
  * Write the record to the file
  */
  function Write( ) {
    global $session;

    if ( parent::Write() ) {
      if ( $this->AllowedTo("Admin") && isset($_POST['relate_to']) && isset($_POST['relate_as']) && isset($_POST['submit']) && $_POST['submit'] == 'Add Relationship' ) {
        dbg_error_log("User",":Write: Adding relationship as %d to %d", $_POST['relate_as'], isset($_POST['relate_to'] ) );
        $qry = new PgQuery("INSERT INTO relationship (from_user, to_user, rt_id ) VALUES( $this->user_no, ?, ? )", $_POST['relate_to'], $_POST['relate_as'] );
        if ( $qry->Exec() ) {
          $c->messages[] = "Relationship added.";
        }
        else {
          $c->messages[] = "There was an error writing to the database.";
          return false;
        }
      }
      return true;
    }
    return false;
  }
}

?>