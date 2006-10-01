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

    if ( $ef->editmode ) {
      $html .= $ef->StartForm( array("autocomplete" => "off" ) );
      if ( $this->user_no > 0 ) $html .= $ef->HiddenField( "user_no", $this->user_no );
    }

    $html .= "<table width=\"100%\" class=\"data\" cellspacing=\"0\" cellpadding=\"0\">\n";

    $html .= $this->RenderFields($ef,"");

    $html .= $this->RenderRoles($ef);

    $html .= $this->RenderRelationships($ef);

    $html .= "</table>\n";
    $html .= "</div>";

    if ( $ef->editmode ) {
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
  function RenderRelationships( $ef, $title = "User Relationships" ) {
    global $session, $c;

    $browser = new Browser("");

    $browser->AddHidden( 'user_link', "'<a href=\"/user.php?user_no=' || user_no || '\">' || fullname || '</a>'" );
    $browser->AddColumn( 'rt_name', 'Relationship' );
    $browser->AddColumn( 'fullname', 'Linked To', 'left', '##user_link##' );
    $browser->AddColumn( 'rt_isgroup', 'Group?' );
    $browser->AddHidden( 'confers', 'Confers' );
    $browser->AddColumn( 'email', 'EMail' );

    $browser->SetJoins( 'relationship NATURAL JOIN relationship_type rt LEFT JOIN usr ON (to_user = user_no)' );
    $browser->SetWhere( "from_user = $this->user_no" );

    $browser->SetUnion("SELECT rt.rt_name, fullname, rt.rt_isgroup, email, '<a href=\"/user.php?user_no=' || user_no || '\">' || fullname || '</a>' AS user_link, rt.confers AS confers FROM relationship NATURAL JOIN relationship_type rt1 LEFT JOIN relationship_type rt ON (rt.rt_id = rt1.rt_inverse) LEFT JOIN usr ON (from_user = user_no) WHERE to_user = $this->user_no ");

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

}

?>