<?php
/**
* Tools for manipulating calendars
*
* @package   davical
* @subpackage   DAViCalSession
* @author    Maxime Delorme <mdelorme@tennaxia.com>
* @copyright Maxime Delorme
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

require_once("./always.php");
require_once("DAViCalSession.php");
$session->LoginRequired();

require_once("DataEntry.php");
require_once("interactive-page.php");
require_once("classBrowser.php");

require_once("caldav-PUT-functions.php");
include_once('check_UTF8.php');

if ( !$session->AllowedTo("Admin" ) ) {
  @ob_flush(); exit(0);
}
if( function_exists("sync_LDAP") && isset($_POST['Sync_LDAP'])){
  sync_LDAP();
}

if( function_exists("sync_LDAP_groups") && isset($_POST['Sync_LDAP_groups'])){
  sync_LDAP_groups();
}

if(isset($_POST['import_from_directory'])){
  Tools::importFromDirectory();
}


class Tools {

  function render(){
    global $c;
    echo  $this->renderImportFromDirectory();
    if ( isset($c->authenticate_hook['call']) && $c->authenticate_hook['call'] == 'LDAP_check' && function_exists("sync_LDAP") ) {
      echo $this->renderSyncLDAP();
    }
  }

  static function renderSyncLDAP(){
    $html = '<div id="entryform">';
    $html .= '<h1>'.translate('Sync LDAP with DAViCal') .'</h1>';

    $data = (object) array('directory_path' => '/path/to/your/ics/files','calendar_path' => 'home');
    $ef = new EntryForm( $_SERVER['REQUEST_URI'],$data , true,true );
    $html .= "<table width=\"100%\" class=\"data\">\n";
    $html .= $ef->StartForm( array("autocomplete" => "off" ) );
    $html .= sprintf( "<tr><td style=\"text-align:left\" colspan=\"2\" >%s</td></tr>\n",
    translate("This operation does the following: <ul><li>check valid users in LDAP directory</li> <li>check users in DAViCal</li></ul> then <ul><li>if a user is present in DAViCal but not in LDAP set him as inactive in DAViCal</li> <li>if a user is present in LDAP but not in DAViCal create the user in DAViCal</li> <li>if a user in present in LDAP and DAViCal then update information in DAViCal</li> </ul>"));
    $html .= "</table>\n";

    $html .= $ef->SubmitButton( "Sync_LDAP", translate('Submit'));

    $html .= '<h1>'.translate('Sync LDAP Groups with DAViCal') .'</h1>';
    $html .= "<table width=\"100%\" class=\"data\">\n";
    $html .= $ef->StartForm( array("autocomplete" => "off" ) );
    $html .= sprintf( "<tr><td style=\"text-align:left\" colspan=\"2\" >%s</td></tr>\n",
    translate("This operation does the following: <ul><li>check valid groups in LDAP directory</li> <li>check groups in DAViCal</li></ul> then <ul><li>if a group is present in DAViCal but not in LDAP set as inactive in DAViCal</li> <li>if a group is present in LDAP but not in DAViCal create the group in DAViCal</li> <li>if a group in present in LDAP and DAViCal then update information in DAViCal</li> </ul>"));
    $html .= "</table>\n";

    $html .= $ef->SubmitButton( "Sync_LDAP_groups", translate('Submit'));
    $html .= $ef->EndForm();

    $html .= "</div>";
    return $html;
  }

  static function renderImportFromDirectory(){
      $html = '<div id="entryform">';
      $html .= '<h1>'.translate('Import all .ics files of a directory') .'</h1>';
      $html .= '<p>'.translate('This process will import each file in a directory named "username.ics" and create a user and calendar for each file to import.') .'</p>';
      
      $data = (object) array('directory_path' => '/path/to/your/ics/files','calendar_path' => 'calendar');
      $ef = new EntryForm( $_SERVER['REQUEST_URI'],$data , true,true );
      $html .= "<table width=\"100%\" class=\"data\">\n";
      $html .= $ef->StartForm( array("autocomplete" => "off" ) );

      $html .= $ef->DataEntryLine( translate("path to store your ics"), "%s", "text", "calendar_path",
                array( "size" => 20,
                        "title" => translate("Set the path to store your ics e.g. 'calendar' will be referenced as /caldav.php/username/calendar/"),
                        "help" => translate("<b>WARNING: all events in this path will be deleted before inserting allof the ics file</b>")
                      )
                      , '' );

      $html .= $ef->DataEntryLine( translate("Directory on the server"), "%s", "text", "directory_path",
                array( "size" => 20, "title" => translate("The path on the server where your .ics files are.")));

      $html .= "</table>\n";
      $html .= $ef->SubmitButton( "import_from_directory", translate('Submit'));
      $html .= $ef->EndForm();

      $html .= "</div>";
      return $html;
  }

  static function importFromDirectory(){
    global $c;
    if(empty($_POST["calendar_path"])){
      dbg_error_log( "importFromDirectory", "calendar path not given");
      return ;
    }
    $path_ics = $_POST["calendar_path"];
    if ( substr($path_ics,-1,1) != '/' ) $path_ics .= '/';          // ensure that we target a collection
    if ( substr($path_ics,0,1) != '/' )  $path_ics = '/'.$path_ics; // ensure that we target a collection

    if(empty($_POST["directory_path"])){
      dbg_error_log( "importFromDirectory", "directory path not given");
      return ;
    }
    $dir = $_POST["directory_path"];
    if(!is_readable($dir)){
      $c->messages[] = sprintf(i18n('directory %s is not readable'),htmlspecialchars($dir));
      dbg_error_log( "importFromDirectory", "directory is not readable");
      return ;
    }
    if ($handle = opendir($dir)) {
      $c->readonly_webdav_collections = false;  // Override this setting so we can create collections/events on import.
      while (false !== ($file = readdir($handle))) {
        if ($file == "." || $file == ".." || substr($file,-4) != '.ics') continue;
        if ( !is_readable($dir.'/'.$file) ) {
          dbg_error_log( "importFromDirectory", "ics file '%s' is not readable",$dir .'/'.$file);
          continue;
        }
        $ics = file_get_contents($dir.'/'.$file);
        $ics = trim($ics);


        if ( $ics != '' ) {
          if ( ! check_string($ics) ) {
            $c->messages[] = sprintf(translate('The file "%s" is not UTF-8 encoded, please check error for more details'),$dir.'/'.$file);
            continue;
          }
          $username = substr($file,0,-4);
          $principal = new Principal('username',$username);
          if ( !$principal->Exists() ) {
            $c->messages[] = sprintf(translate('The principal "%s" does not exist'),$username);
            continue;
          }
          $path = "/".$username.$path_ics;
          $user_no = $principal->user_no();
          if ( controlRequestContainer($username, $user_no, $path, false) === -1)
            continue;
          dbg_error_log( "importFromDirectory", "importing to $path");
          import_collection($ics,$user_no,$path,1);
          $c->messages[] = sprintf(translate('All events of user "%s" were deleted and replaced by those from file %s'),substr($file,0,-4),$dir.'/'.$file);
        }
      }
      closedir($handle);
    }
  }
}

$Tools = new Tools();

include("page-header.php");
$Tools->render();
include("page-footer.php");
