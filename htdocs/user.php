<?php
  require_once("../inc/always.php");
  require_once("RSCDSSession.php");

  // This page requires login.
  $session->LoginRequired();
  require_once("interactive-page.php");

  require_once("RSCDSUser.php");

  $user_no = intval(isset($_POST['user_no']) ? $_POST['user_no'] : $_GET['user_no'] );
  $user = new RSCDSUser($user_no);
  if ( $user->user_no == 0 ) {
    $c->page_title = ( $user_no != "" ? i18n("User Unavailable") : i18n("New User") );
  }
  else {
    $c->page_title = sprintf("%s (%s)", $user->Get("fullname"), $user->Get("username"));
  }
  $show = 0;

  if ( !$session->just_logged_in && (isset($_POST['submit']) || isset($_GET['action'])) ) {
    if ( $session->AllowedTo("Admin") || $session->AllowedTo("Support")
              || ($user->user_no > 0 && $user->user_no == $session->user_no) ) {
      $session->Log( "DBG: Record %s write type is %s.", $user->Table, $user->WriteType );
      if ( isset($_POST['submit']) ) {
        $user->PostToValues();
        if ( $user->Validate() ) {
          $user->Write();
          $user = new RSCDSUser($user->user_no);
          $user->EditMode = true;
          if ( $user->user_no == 0 ) {
            $c->page_title = ( $user_no != "" ? "User Unavailable" : "New User" );
          }
          else {
            $c->page_title = $user->Get("user_no"). " - " . $user->Get("fullname");
          }
        }
        if ( $_FILES['ics_file']['name'] && $_POST['path_ics'] ) {
          $ics = file_get_contents($_FILES['ics_file']['tmp_name']);
          $ics = trim($ics);
          $path_ics = $_POST['path_ics'];

          if ( substr($path_ics,-1,1) != '/' ) $path_ics .= '/';          // ensure that we target a collection
          if ( substr($path_ics,0,1) != '/' )  $path_ics = '/'.$path_ics; // ensure that we target a collection
        }
        else {
          unset($ics);
        }
      }
      else {
        /**
        * Handle any actions, such as 'delete_relation'
        */
        if ( $user->HandleAction($_GET['action']) ) {
          $user = new RSCDSUser($user->user_no);
          $user->EditMode = true;
        }
      }
    }
  }

  if ( $session->AllowedTo("Admin") )
    $user_menu->AddOption(translate("New User"),"$c->base_url/user.php?create",translate("Add a new user"), false, 10);

  if ( $user->user_no > 0 ) {
    if ( $user->AllowedTo('update') ) {
    	$user_menu->AddOption( translate($user->EditMode?"View":"Edit")." ".$user->Values->fullname, "$c->base_url/user.php?user_no=$user->user_no".($user->EditMode?"":"&edit=1"), translate(($user->EditMode?"View":"Edit")." this user record"), true, 900 );
    }
    else {
      $user_menu->AddOption( translate("View")." ".$user->Values->fullname, "$c->base_url/user.php?user_no=$user->user_no", translate("View this user record"), true, 900 );
    }
  }

  include("page-header.php");
  echo $user->Render($c->page_title);
  include("page-footer.php");
  if ( $ics !='' ) {
    /**
    * If the user has uploaded a .ics file as a calendar, we fake this out
    * as if it were a "PUT" request against a collection.  This is something
    * of a hack, especially since it doesn't provide quality feedback to the
    * user about what is happening.  It works though :-)
    *
    * TODO: Extract the important code from caldav-PUT-collection.php into a
    * function which can be included here and shared.  That way we can perhaps
    * show the user a useful error message, if needed.
    */
    include('check_UTF8.php');
    if ( check_string($ics) ) {
      $_SERVER['REQUEST_METHOD'] = 'PUT';
      $_SERVER['PATH_INFO'] = "/".$user->Get("username").$path_ics;
      require_once("CalDAVRequest.php");
      $request = new CalDAVRequest();
      $request->raw_post = $ics;
      include_once("caldav-PUT.php");
    }
  }
?>
