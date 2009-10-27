<?php
/**
* User maintain / view with DAViCal specific associated tables
*
* @package   davical
* @subpackage   DAViCalUser
* @author    Andrew McMillan <andrew@mcmillan.net.nz>
* @copyright Catalyst .Net Ltd, Morphoss Ltd <http://www.morphoss.com/>
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*
* @TODO: split this (& parent class) into a data-dealing class and a UI class in some way
*/

require('User.php');
require_once('classBrowser.php');  // DAViCalUser is included via drivers_ldap.php (yuck!) so this could already be loaded
require('check_UTF8.php');
require('caldav-PUT-functions.php');

$c->stylesheets[] = $c->base_url.'/css/browse.css';
$c->scripts[] = $c->base_url.'/js/browse.js';

/**
* A class for viewing and maintaining DAViCal User records
*
* @package   davical
*/
class DAViCalUser extends User
{

  var $delete_collection_confirmation_required;
  var $delete_user_confirmation_required;

  /**
  * Constructor - nothing fancy as yet.
  */
  function DAViCalUser( $id , $prefix = '') {
    global $c;
    $this->delete_collection_confirmation_required = null;
    $this->delete_user_confirmation_required = null;
    parent::User( $id, $prefix );
    if ( $this->user_no == 0 && isset($c->template_usr) && is_array($c->template_usr) ) {
      foreach( $c->template_usr AS $k => $v ) {
        $this->Set($k,$v);
      }
    }
  }

  /**
  * Render the form / viewer as HTML to show the user
  * @return string An HTML fragment to display in the page.
  */
  function Render($title = '' ) {
    global  $c;
    $html = '';
    dbg_error_log('User', ":Render: type=$this->WriteType, edit_mode=$this->EditMode" );

    $ef = new EntryForm( $_SERVER['REQUEST_URI'], $this->Values, $this->EditMode );
    $ef->NoHelp();  // Prefer this style, for the moment

    $html = '<div id="entryform">';
    $html .= sprintf('<h1>%s</h1>', translate('You are '.($ef->EditMode?'editing':'viewing')).' '.translate($title));

    if ( $ef->EditMode ) {
      $html .= $ef->StartForm( array('autocomplete' => 'off' ) );
      if ( $this->user_no > 0 ) $html .= $ef->HiddenField( 'user_no', $this->user_no );
    }

    if ( isset($this->delete_user_confirmation_required) ) {
      $html .= '<p class="error">';
      $html .= sprintf('<b>%s</b> \'%s\' <a class="error" href="%s&%s">%s</a> %s', translate('Deleting User:'), $this->Get('username'), $_SERVER['REQUEST_URI'], $this->delete_user_confirmation_required, translate('Confirm Deletion of the User'), translate('All of the user\'s calendars and events will be unrecoverably deleted.') );
      $html .= "</p>\n";
    }

    $html .= '<table width="100%" class="data" cellspacing="0" cellpadding="0">';

    $html .= $this->RenderFields($ef,'');

    $html .= $this->RenderRoles($ef);

    if ( 'insert' != $this->WriteType ) {
      $html .= $this->RenderRelationshipsFrom($ef);
      $html .= $this->RenderRelationshipsTo($ef);
      $html .= $this->RenderCollections($ef);
    }

    $html .= '</table>';
    $html .= '</div>';

    if ( $ef->EditMode ) {
      $html .= '<div id="footer">';
      $html .= $ef->SubmitButton( 'submit', (('insert' == $this->WriteType) ? translate('Create') : translate('Update')) );
      if ( 'insert' != $this->WriteType ) {
        $html .= '&nbsp; &nbsp; &nbsp; &nbsp; ';
        $html .= sprintf('<a class="fsubmit" href="%s/usr.php?user_no=%s&action=delete_user">%s</a>', $c->base_url, $this->user_no, translate('Delete User') );
      }
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
  function RenderRelationshipsFrom( $ef, $title = null ) {
    global $session, $c;

    if ( $title == null ) $title = i18n('Relationships from this user');

    $browser = new Browser('');

    $browser->AddHidden( 'user_link', "'<a href=\"$c->base_url/usr.php?user_no=' || user_no || '\">' || fullname || '</a>'" );
    $browser->AddColumn( 'rt_name', translate('Relationship'), 'left w20' );
    $browser->AddColumn( 'fullname', translate('Linked To'), 'left w20', '##user_link##' );
    $browser->AddHidden( 'confers' );
    $browser->AddColumn( 'email', translate('EMail'), 'left' );

    $browser->SetJoins( 'relationship NATURAL JOIN relationship_type rt LEFT JOIN usr ON (to_user = user_no)' );
    $browser->SetWhere( 'from_user = '.$this->user_no );

    if ( isset( $_GET['o']) && isset($_GET['d']) ) {
      $browser->AddOrder( $_GET['o'], $_GET['d'] );
      if ( $_GET['o'][0] != 'fullname' ) $browser->AddOrder( 'fullname', 'A', 0, 1 );
    }
    else {
      $browser->AddOrder( 'rt_name', 'A' );
      $browser->AddOrder( 'fullname', 'A', 0, 1 );
    }

    if ( $c->enable_row_linking ) {
      $browser->RowFormat( "<tr onMouseover=\"LinkHref(this,1);\" title=\"".translate('Click to display that user')."\" class=\"r%d\">\n", "</tr>\n", '#even' );
    }
    else {
      $browser->RowFormat( "<tr class=\"r%d\">\n", "</tr>\n", '#even' );
    }
    $browser->SetTranslatable( array('rt_name') );
    $browser->DoQuery();


    $html = ( $title == '' ? '' : $ef->BreakLine(translate($title)) );
    $html .= '<tr><td colspan="2">';
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

    if ( $title == null ) $title = i18n('Relationships to this user');
    $browser = new Browser('');

    $browser->AddHidden( 'user_link', "'<a href=\"$c->base_url/usr.php?user_no=' || user_no || '\">' || fullname || '</a>'" );
    $browser->AddColumn( 'fullname', translate('Linked From'), 'left w20', '##user_link##' );
    $browser->AddColumn( 'rt_name', translate('Relationship'), 'left w20' );
    $browser->AddHidden( 'confers' );
    $browser->AddColumn( 'email', translate('EMail'), 'left' );
    if ( $ef->EditMode ) {
      $browser->AddColumn( 'delete', translate('Delete'), 'centre', '', "'<a class=\"fsubmit\" href=\"$c->base_url/usr.php?edit=1&user_no=$this->user_no&action=delete_relationship&from_user=' || user_no || '\">Delete</a>'" );
    }

    $browser->SetJoins( 'relationship NATURAL JOIN relationship_type rt LEFT JOIN usr ON (from_user = user_no)' );
    $browser->SetWhere( 'to_user = '.$this->user_no );

    if ( isset( $_GET['o']) && isset($_GET['d']) ) {
      $browser->AddOrder( $_GET['o'], $_GET['d'] );

      if ( $_GET['o'][0] != 'fullname' ) $browser->AddOrder( 'fullname', 'A', 0, 1 );
    }
    else {
      $browser->AddOrder( 'rt_name', 'A', 1 );
      $browser->AddOrder( 'fullname', 'A', 0, 1 );
    }

    $browser->RowFormat( "<tr onMouseover=\"LinkHref(this,1);\" title=\"".translate('Click to display that user')."\" class=\"r%d\">\n", "</tr>\n", '#even' );
    $browser->SetTranslatable( array('rt_name') );
    $browser->DoQuery();

    /**
    * Present an extra editable row at the bottom of the browse.
    */
    if ( $ef->EditMode ) {
      if ( isset($this->roles['Group']) ) {
        /**
        * We only allow individuals to link to groups at this stage.
        */
        $groupsql = 'AND NOT EXISTS (SELECT 1 FROM role_member WHERE role_no = 2 AND user_no=usr.user_no)';
      }
      else $groupsql = '';

      $sql = <<<EOSQL
SELECT user_no, fullname FROM usr
 WHERE NOT EXISTS ( SELECT 0 FROM relationship WHERE (from_user = usr.user_no AND to_user = $this->user_no))
   AND user_no != $this->user_no $groupsql ORDER BY fullname
EOSQL;

      if ( ! isset($this->roles['Group']) )
        $nullvalue = translate( '--- select a user, group or resource ---' );
      else
        $nullvalue = translate( '--- select a user or resource ---' );
      $person_selection = $ef->DataEntryField( '', 'lookup', 'relate_to',
                                array('title' => translate('Select the user, resource or group to relate this user to'),
                                      '_null' => $nullvalue,
                                      '_sql'  => $sql ) );

      $relationship_type_selection = $ef->DataEntryField( '', 'lookup', 'relate_as',
                                array('title' => translate('Select the type of relationship from this user'),
                                      '_null' => translate('--- select a relationship type ---'),
                                      '_sql'  => 'SELECT rt_id, rt_name FROM relationship_type ' ) );

      $browser->AddRow( array(
                      'user_link' => $person_selection,
                      'rt_name' => $relationship_type_selection,  /* Since 'fullname' is formatted to display this value */
                      'delete' => sprintf('<input type="submit" name="submit" value="%s" class="fsubmit">', htmlspecialchars(translate('Add Relationship')))
                     ) );
    }

    $html = ( $title == '' ? '' : $ef->BreakLine(translate($title)) );
    $html .= '<tr><td colspan="2">';
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

    if ( $title == null ) $title = i18n('This user\'s collections');
    $browser = new Browser('');

    $browser->AddHidden( 'collection_link', "'<a href=\"$c->base_url/collection.php?user_no=' || user_no || '&dav_name=' || dav_name || '\">' || dav_name || '</a>'" );
    $browser->AddColumn( 'dav_name', translate('Collection Path'), 'left', '##collection_link##' );
    $browser->AddColumn( 'is_calendar', translate('Is a Calendar?'), 'centre', '', 'CASE WHEN is_calendar THEN \'Yes\' ELSE \'No\' END' );
    $browser->AddColumn( 'publicly_readable', translate('Public'), 'centre', '', 'CASE WHEN publicly_readable THEN \'Yes\' ELSE \'No\' END' );
    $browser->AddColumn( 'modified', translate('Changed On'), 'centre', '', 'to_char(created,\'YYYY-MM-DD HH24:MI\')' );
    if ( $ef->EditMode ) {
      $browser->AddColumn( 'delete', translate('Action'), 'left', '', "'<a class=\"fsubmit\" href=\"$c->base_url/usr.php?user_no=$this->user_no&dav_name=##URL:dav_name##&action=delete_collection\">Delete</a>'" );
    }
    $browser->SetJoins( 'collection LEFT JOIN usr USING (user_no)' );
    $browser->SetWhere( "collection.user_no = $this->user_no AND NOT collection.dav_name LIKE ('/' || usr.username || '/.%')" );

    if ( isset( $_GET['o']) && isset($_GET['d']) ) {
      $browser->AddOrder( $_GET['o'], $_GET['d'] );
    }
    else
      $browser->AddOrder( 'dav_name', 'A' );

    $browser->DoQuery();

    /**
    * Present an extra editable row at the bottom of the browse.
    */
    if ( $ef->EditMode && ('insert' != $this->WriteType) ) {
      $calendar_name = $ef->DataEntryField( '', 'text', 'path_ics',
              array( 'size' => 10,
                     'title' => translate('The calendar name part of the path to store your ics. E.g. the "home" part of "/caldav.php/username/home/"')
                   ) );
      $calendar_name = '/'.$this->Get('username').'/ &nbsp;' . $calendar_name . '&nbsp;/';

      $is_public = $ef->DataEntryField( '', 'checkbox', 'publicly_readable',
              array( 'title' => translate('Should this calendar be readable without authenticating?') ) );

      $icalendar_file = $ef->DataEntryField( '', 'file', 'ics_file',
              array( 'size' => 20, 'title' => translate('Upload a .ics calendar in iCalendar format ')) );

      $browser->AddRow( array(
                      'collection_link' => $calendar_name,
                      'modified' => $icalendar_file,
                      'publicly_readable' => $is_public,
                      'delete' => sprintf('<input type="submit" name="submit" value="%s" class="fsubmit">', htmlspecialchars(translate('Create Calendar')))
                     ) );
    }

    $browser->RowFormat( "<tr onMouseover=\"LinkHref(this,1);\" title=\"".translate('Click to display the contents of the collection')."\" class=\"r%d\">\n", "</tr>\n", '#even' );

    $html = ( $title == '' ? '' : $ef->BreakLine(translate($title)) );
    if ( isset($this->delete_collection_confirmation_required) ) {
      $html .= '<tr><td colspan="2" class="error">';
      $html .= sprintf('<b>%s</b> "%s" <a class="error" href="%s&%s">%s</a> %s', translate('Deleting Collection:'), $_GET['dav_name'], $_SERVER['REQUEST_URI'], $this->delete_collection_confirmation_required, translate('Confirm Deletion of the Collection'), translate('All collection data will be unrecoverably deleted.') );
      $html .= "</td></tr>\n";
    }
    $html .= '<tr><td colspan="2">';
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
  * Extend parent definition of what the current user is allowed to do
  * @param string $whatever What the user wants to do
  * @return boolean Whether they are allowed to.
  */
  function AllowedTo ( $whatever )
  {
    global $session;

    $rc = false;
    switch( strtolower($whatever) ) {

      case 'deleterelationship':
        $rc = ( $session->AllowedTo('Admin')
                || ($this->user_no > 0 && $session->user_no == $this->user_no) );
        break;

      case 'deletecollection':
        $rc = ( $session->AllowedTo('Admin')
                || ($this->user_no > 0 && $session->user_no == $this->user_no) );
        break;

      default:
        $rc = parent::AllowedTo( $whatever );
    }

    return $rc;
  }


  /**
  * Handle any unusual actions we might invent
  */
  function HandleAction( $action ) {
    global $session, $c;

    dbg_error_log('User',':HandleAction: Action %s', $action );

    switch( $action ) {
      case 'delete_relationship':
        dbg_error_log('User',':HandleAction: Deleting relationship to %d from %d', $this->user_no, $_GET['from_user'] );
        if ( $this->AllowedTo('DeleteRelationship') ) {
          dbg_error_log('User',':HandleAction: Deleting relationship to %d from %d', $this->user_no, $_GET['from_user'] );
          $qry = new PgQuery('DELETE FROM relationship WHERE to_user=? AND from_user=?;', $this->user_no, $_GET['from_user'] );
          if ( $qry->Exec() ) {
            $c->messages[] = i18n('Relationship deleted');
          }
          else {
            $c->messages[] = i18n('There was an error writing to the database.');
            return false;
          }
        }
        return true;

      case 'delete_collection':
        dbg_error_log('User',':HandleAction: Deleting collection %s for user %d', $_GET['dav_name'], $this->user_no );
        if ( $this->AllowedTo('DeleteCollection') ) {
          if ( $session->CheckConfirmationHash('GET', 'confirm') ) {
            dbg_error_log('User',':HandleAction: Allowed to delete collection %s for user %d', $_GET['dav_name'], $this->user_no );
            $qry = new PgQuery('DELETE FROM collection WHERE user_no=? AND dav_name=?;', $this->user_no, $_GET['dav_name'] );
            if ( $qry->Exec() ) {
              $c->messages[] = i18n('Collection deleted');
             return true;
            }
            else {
              $c->messages[] = i18n('There was an error writing to the database.');
              return false;
            }
          }
          else {
            $c->messages[] = i18n('Please confirm deletion of collection - see below');
            $this->delete_collection_confirmation_required = $session->BuildConfirmationHash('GET', 'confirm');
            return false;
          }
        }

      case 'delete_user':
        dbg_error_log('User',':HandleAction: Deleting user %d', $this->user_no );
        if ( $this->AllowedTo('DeleteUser') ) {
          if ( $session->CheckConfirmationHash('GET', 'confirm') ) {
            dbg_error_log('User',':HandleAction: Allowed to delete user %d -%s', $this->user_no, $this->get('user_name') );
            $qry = new PgQuery('DELETE FROM usr WHERE user_no=?;', $this->user_no );
            if ( $qry->Exec() ) {
              $c->messages[] = i18n('User deleted');
             return true;
            }
            else {
              $c->messages[] = i18n('There was an error writing to the database.');
              return false;
            }
          }
          else {
            $c->messages[] = i18n('Please confirm deletion of user');
            $this->delete_user_confirmation_required = $session->BuildConfirmationHash('GET', 'confirm');
            return false;
          }
        }

      default:
        return false;
    }
    return false;
  }


  /**
  * Write the record to the file
  */
  function Write( ) {
    global $session, $c, $path_ics, $publicly_readable;

    if ( parent::Write() ) {
      if ( $this->WriteType == 'insert' ) {
        $username = $this->Get('username');
        CreateHomeCalendar($username);
        CreateDefaultRelationships($username);
      }
      if ( isset($_POST['relate_to']) && $_POST['relate_to'] != '' && isset($_POST['relate_as']) && $_POST['relate_as'] != '' && isset($_POST['submit']) && $_POST['submit'] == htmlspecialchars(translate('Add Relationship')) ) {
        dbg_error_log('User',':Write: Adding relationship as %d to %d', $_POST['relate_as'], isset($_POST['relate_to'] ) );
        $qry = new PgQuery('INSERT INTO relationship (from_user, to_user, rt_id ) VALUES( ?, ?, ? )', $_POST['relate_to'], $this->user_no, $_POST['relate_as'] );
        if ( $qry->Exec() ) {
          $c->messages[] = i18n('Relationship added.');
        }
        else {
          $c->messages[] = i18n('There was an error writing to the database.');
          return false;
        }
      }
      param_to_global('path_ics', '#^[^/]+$#');
      param_to_global('publicly_readable', '#^(on|off)$#');
      if ( isset($path_ics) && $path_ics != '' ) {
        dbg_error_log('User',':Write: New collection "%s", public: %s', $_POST['path_ics'], isset($_POST['publicly_readable'] ) );
        $ics = '';
        if ( isset($_FILES['ics_file']['tmp_name']) && $_FILES['ics_file']['tmp_name'] != '' ) {
          $ics = trim(file_get_contents($_FILES['ics_file']['tmp_name']));
          dbg_error_log('User',':Write: Loaded %d bytes from %s', strlen($ics), $_FILES['ics_file']['tmp_name'] );
        }

        /**
        * If the user has uploaded a .ics file as a calendar, we fake this out
        * as if it were a "PUT" request against a collection.  This is something
        * of a hack.  It works though :-)
        */
        if ( check_string($ics) ) {
          $path = '/'.$this->Get('username').'/'.$path_ics.'/';
          controlRequestContainer( $this->Get('username'), $this->user_no, $path, false, ($publicly_readable == 'on' ? true : false));
          import_collection( $ics, $this->user_no, $path, $session->user_no );
          $c->messages[] = sprintf(translate('Calendar "%s" for user "%s" was created.'), $path_ics, $this->Get('username'));
        }
        else {
          $c->messages[] =  sprintf(translate('The file is not UTF-8 encoded, please check the error for more details.') );
        }
      }
      return true;
    }
    return false;
  }

}

