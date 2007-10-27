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

$c->stylesheets[] = "$c->base_url/css/browse.css";
$c->scripts[] = "$c->base_url/js/browse.js";

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
    global  $c;
    $html = "";
    dbg_error_log("User", ":Render: type=$this->WriteType, edit_mode=$this->EditMode" );

    $ef = new EntryForm( $_SERVER['REQUEST_URI'], $this->Values, $this->EditMode );
    $ef->NoHelp();  // Prefer this style, for the moment

    $html = '<div id="entryform">';
    $html .= sprintf("<h1>%s</h1>\n", translate("You are ".($ef->EditMode?"editing":"viewing"))." ".translate($title));

    if ( $ef->EditMode ) {
      $html .= $ef->StartForm( array("autocomplete" => "off" ) );
      if ( $this->user_no > 0 ) $html .= $ef->HiddenField( "user_no", $this->user_no );
    }

    $html .= "<table width=\"100%\" class=\"data\" cellspacing=\"0\" cellpadding=\"0\">\n";

    $html .= $this->RenderFields($ef,"");

    $html .= $this->RenderImportIcs($ef);
    $html .= $this->RenderRoles($ef);

    $html .= $this->RenderRelationshipsFrom($ef);
    $html .= $this->RenderRelationshipsTo($ef);
    $html .= $this->RenderCollections($ef);

    $html .= "</table>\n";
    $html .= "</div>";

    if ( $ef->EditMode ) {
      $html .= '<div id="footer">';
      $html .= $ef->SubmitButton( "submit", (("insert" == $this->WriteType) ? translate("Create") : translate("Update")) );
      $html .= '</div>';
      $html .= $ef->EndForm();
    }

    return $html;
  }
  /**
  * Render input file to import ics in calendar user
  *
  * @return string The string of html to be output
  */
  function RenderImportIcs( $ef, $title = null ) {
    if ( !$ef->EditMode ) return;
    if ( $title == null ) $title = i18n("Import ICS file");
    $html = ( $title == "" ? "" : $ef->BreakLine(translate($title)) );
    $html .= sprintf( "<tr><th class=\"prompt\">&nbsp;</th><th style=\"text-align:left\">%s</th></tr>\n", translate("<b>WARNING: all events in this path will be deleted before inserting all of the ics file</b>"));
    $html .= $ef->DataEntryLine( translate("path to store your ics"), "%s", "text", "path_ics",
              array( "size" => 20,
                     "title" => translate("set the path to store your ics ex:home if you get it by caldav.php/me/home/"),
                     "help" => translate("<b>WARNING: all events in this path will be deleted before inserting all of the ics file</b>")
                   )
                   , $this->prefix );

    $html .= $ef->DataEntryLine( translate("Your .ics calendar"), "%s", "file", "ics_file",
              array( "size" => 20, "title" => translate("Upload your .ics calendar in ical format ")), $this->prefix );
    return $html;
  }

  /**
  * Render the user's relationships to other users & resources
  *
  * @return string The string of html to be output
  */
  function RenderRelationshipsFrom( $ef, $title = null ) {
    global $session, $c;

    if ( $title == null ) $title = i18n("Relationships from this user");

    $browser = new Browser("");

    $browser->AddHidden( 'user_link', "'<a href=\"$c->base_url/usr.php?user_no=' || user_no || '\">' || fullname || '</a>'" );
    $browser->AddColumn( 'rt_name', translate('Relationship') );
    $browser->AddColumn( 'fullname', translate('Linked To'), 'left', '##user_link##' );
    $browser->AddHidden( 'confers' );
    $browser->AddColumn( 'email', translate('EMail') );
    if ( $ef->EditMode ) { // && $session->AllowedTo("MaintainRelationships") ) {
      $browser->AddColumn( 'delete', translate('Delete'), 'centre', '', "'<a class=\"\" href=\"$c->base_url/usr.php?edit=1&user_no=$this->user_no&action=delete_relationship&to_user=' || user_no || '\">Delete</a>'" );
    }

    $browser->SetJoins( 'relationship NATURAL JOIN relationship_type rt LEFT JOIN usr ON (to_user = user_no)' );
    $browser->SetWhere( "from_user = $this->user_no" );

    if ( isset( $_GET['o']) && isset($_GET['d']) ) {
      $browser->AddOrder( $_GET['o'], $_GET['d'] );
    }
    else
      $browser->AddOrder( 'rt_name', 'A' );

    # We always want a secondary sort on fullname.
    if ( ! isset( $_GET['o'] ) ||
         ( isset( $_GET['o'] ) && $_GET['o'][0] != 'fullname' ) )
      $browser->AddOrder( 'fullname', 'A', 0, 1 );

    if ( $c->enable_row_linking ) {
      $browser->RowFormat( "<tr onMouseover=\"LinkHref(this,1);\" title=\"".translate("Click to display that user")."\" class=\"r%d\">\n", "</tr>\n", '#even' );
    }
    else {
      $browser->RowFormat( "<tr class=\"r%d\">\n", "</tr>\n", '#even' );
    }
    $browser->DoQuery();

    /**
    * Present an extra editable row at the bottom of the browse.
    */
    if ( $ef->EditMode ) { // && $session->AllowedTo("MaintainRelationships") ) {
      $sql = <<<EOSQL
SELECT user_no, fullname FROM usr
 WHERE NOT EXISTS ( SELECT 0 FROM relationship WHERE (to_user = usr.user_no AND from_user = $this->user_no))
   AND user_no != $this->user_no
EOSQL;
      if ( isset($this->roles['Group']) ) {
        /**
        * We only allow individuals to link to groups at this stage.
        */
        $sql .= 'AND NOT EXISTS (SELECT 1 FROM role_member WHERE role_no = 2 AND user_no=usr.user_no)';
      }

      if ( isset($this->roles['Group']) )
        $nullvalue = translate( "--- select a user, group or resource ---" );
      else
        $nullvalue = translate( "--- select a user or resource ---" );
      $person_selection = $ef->DataEntryField( "", "lookup", "relate_to",
                                array("title" => translate("Select the user, resource or group to relate this user to"),
                                      "_null" => $nullvalue,
                                      "_sql"  => $sql ) );

      $relationship_type_selection = $ef->DataEntryField( "", "lookup", "relate_as",
                                array("title" => translate("Select the type of relationship from this user"),
                                      "_null" => translate("--- select a relationship type ---"),
                                      "_sql"  => "SELECT rt_id, rt_name FROM relationship_type " ) );

      $browser->AddRow( array(
                      'rt_name' => $relationship_type_selection,  /* Since 'fullname' is formatted to display this value */
                      'user_link' => $person_selection,
                      'delete' => sprintf('<input type="submit" name="submit" value="%s" class="fsubmit">', htmlspecialchars(translate("Add Relationship")))
                     ) );
    }

    $html = ( $title == "" ? "" : $ef->BreakLine(translate($title)) );
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
  function RenderRelationshipsTo( $ef, $title = null ) {
    global $session, $c;

    if ( $title == null ) $title = i18n("Relationships to this user");
    $browser = new Browser("");

    $browser->AddHidden( 'user_link', "'<a href=\"$c->base_url/usr.php?user_no=' || user_no || '\">' || fullname || '</a>'" );
    $browser->AddColumn( 'fullname', translate('Linked From'), 'left', '##user_link##' );
    $browser->AddColumn( 'rt_name', translate('Relationship') );
    $browser->AddHidden( 'confers' );
    $browser->AddColumn( 'email', translate('EMail') );

    $browser->SetJoins( 'relationship NATURAL JOIN relationship_type rt LEFT JOIN usr ON (from_user = user_no)' );
    $browser->SetWhere( "to_user = $this->user_no" );

    if ( isset( $_GET['o']) && isset($_GET['d']) ) {
      $browser->AddOrder( $_GET['o'], $_GET['d'], 1 );
    }
    else
      $browser->AddOrder( 'rt_name', 'A', 1 );

    $browser->RowFormat( "<tr onMouseover=\"LinkHref(this,1);\" title=\"".translate("Click to display that user")."\" class=\"r%d\">\n", "</tr>\n", '#even' );
    $browser->DoQuery();

    $html = ( $title == "" ? "" : $ef->BreakLine(translate($title)) );
    $html .= "<tr><td>&nbsp;</td><td>\n";
    $html .= $browser->Render();
    $html .= "</td></tr>\n";

    return $html;
  }


  /**
  * Render the user's collections
  *
  * @return string The string of html to be output
  */
  function RenderCollections( $ef, $title = null ) {
    global $session, $c;

    if ( $title == null ) $title = i18n("This user's collections");
    $browser = new Browser("");

    $browser->AddHidden( 'collection_link', "'<a href=\"$c->base_url/collection.php?user_no=' || user_no || '&dav_name=' || dav_name || '\">' || dav_name || '</a>'" );
    $browser->AddColumn( 'dav_name', translate('Collection Path'), 'left', '##collection_link##' );
    $browser->AddColumn( 'is_calendar', translate('Is a Calendar?'), 'centre', '', "CASE WHEN is_calendar THEN 'Yes' ELSE 'No' END" );
    $browser->AddColumn( 'created', translate('Created On') );
    $browser->AddColumn( 'modified', translate('Changed On') );

    $browser->SetJoins( 'collection LEFT JOIN usr USING (user_no)' );
    $browser->SetWhere( "collection.user_no = $this->user_no" );

    if ( isset( $_GET['o']) && isset($_GET['d']) ) {
      $browser->AddOrder( $_GET['o'], $_GET['d'] );
    }
    else
      $browser->AddOrder( 'dav_name', 'A' );

    $browser->RowFormat( "<tr onMouseover=\"LinkHref(this,1);\" title=\"".translate("Click to display the contents of the collection")."\" class=\"r%d\">\n", "</tr>\n", '#even' );
    $browser->DoQuery();

    $html = ( $title == "" ? "" : $ef->BreakLine(translate($title)) );
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
    global $session, $c;

    dbg_error_log("User",":HandleAction: Action %s", $action );

    switch( $action ) {
      case 'delete_relationship':
        dbg_error_log("User",":HandleAction: Deleting relationship from %d to %d", $this->user_no, $_GET['to_user'] );
        if ( $this->AllowedTo("Admin") ) {
          dbg_error_log("User",":HandleAction: Deleting relationship from %d to %d", $this->user_no, $_GET['to_user'] );
          $qry = new PgQuery("DELETE FROM relationship WHERE from_user=? AND to_user=?;", $this->user_no, $_GET['to_user'] );
          if ( $qry->Exec() ) {
            $c->messages[] = i18n("Relationship deleted");
          }
          else {
            $c->messages[] = i18n("There was an error writing to the database.");
            return false;
          }
        }
        return true;

      default:
        return false;
    }
  }


  /**
  * Create a default home calendar for the user.
  */
  function CreateHomeCalendar() {
    global $session, $c;
    if ( ! isset($c->home_calendar_name) || strlen($c->home_calendar_name) == 0 ) return true;

    $parent_path = "/".$this->Get('username')."/";
    $calendar_path = $parent_path . $c->home_calendar_name."/";
    $dav_etag = md5($this->user_no . $calendar_path);
    $sql = "INSERT INTO collection (user_no, parent_container, dav_name, dav_etag, dav_displayname, is_calendar, ";
    $sql .= "created, modified) VALUES( ?, ?, ?, ?, ?, true, current_timestamp, current_timestamp );";
    $qry = new PgQuery( $sql, $this->user_no, $parent_path, $calendar_path, $dav_etag, $this->Get('fullname') );
    if ( $qry->Exec() ) {
      $c->messages[] = i18n("Home calendar added.");
      dbg_error_log("User",":Write: Created user's home calendar at '%s'", $calendar_path );
    }
    else {
      $c->messages[] = i18n("There was an error writing to the database.");
      return false;
    }
    return true;
  }


  /**
  * Create default relationships
  */
  function CreateDefaultRelationships() {
    global $session, $c;
    if ( ! isset($c->default_relationships) || !is_array($c->default_relationships) || count($c->default_relationships) == 0 ) return false;

    $sql = "";
    foreach( $c->default_relationships AS $to_user => $permission ) {
      $sql .= "INSERT INTO relationship (from_user, to_user, rt_id) ";
      $sql .= "VALUES( $this->user_no, $to_user, (select rt_id from relationship_type where confers = '$permission' order by rt_id limit 1) );";
    }
    $qry = new PgQuery( $sql );
    if ( $qry->Exec() ) {
      $c->messages[] = i18n("Default relationships added.");
      dbg_error_log("User",":Write: Added default relationships" );
    }
    else {
      $c->messages[] = i18n("There was an error writing to the database.");
      return false;
    }
    return true;
  }


  /**
  * Write the record to the file
  */
  function Write( ) {
    global $session, $c;

    if ( parent::Write() ) {
      if ( $this->WriteType == 'insert' ) {
        $this->CreateHomeCalendar();
        $this->CreateDefaultRelationships();
      }
      if ( $this->AllowedTo("Admin") && isset($_POST['relate_to']) && isset($_POST['relate_as']) && isset($_POST['submit']) && $_POST['submit'] == htmlspecialchars(translate('Add Relationship')) ) {
        dbg_error_log("User",":Write: Adding relationship as %d to %d", $_POST['relate_as'], isset($_POST['relate_to'] ) );
        $qry = new PgQuery("INSERT INTO relationship (from_user, to_user, rt_id ) VALUES( $this->user_no, ?, ? )", $_POST['relate_to'], $_POST['relate_as'] );
        if ( $qry->Exec() ) {
          $c->messages[] = i18n("Relationship added.");
        }
        else {
          $c->messages[] = i18n("There was an error writing to the database.");
          return false;
        }
      }
      return true;
    }
    return false;
  }
}

