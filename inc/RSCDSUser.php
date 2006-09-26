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

}

?>