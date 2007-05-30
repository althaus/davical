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
    $c->page_title = ( $user_no != "" ? translate("User Unavailable") : translate("New User") );
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
            $c->page_title = ( $user_no != "" ? translate("User Unavailable") : translate("New User") );
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

          if ( $ics !='' ) {
            /**
            * If the user has uploaded a .ics file as a calendar, we fake this out
            * as if it were a "PUT" request against a collection.  This is something
            * of a hack.  It works though :-)
            */
            include('check_UTF8.php');
            if ( check_string($ics) ) {
              $path = "/".$user->Get("username").$path_ics;
              include("caldav-PUT-functions.php");
              if(controlRequestContainer($user->Get("username"),$user->user_no, $path,false) === true ){
                import_collection($ics,$user->user_no,$path,$session->user_no);
                $c->messages[] = sprintf(translate("All events of user %s were deleted and replaced by those from the file."),$user->Get("username"));
              }
            } else
              $c->messages[] =  sprintf(translate("The file %s is not UTF-8 encoded, please check the error for more details."),$dir.'/'.$file);
          }
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
    $user_menu->AddOption(translate("New User"),"$c->base_url/usr.php?create",translate("Add a new user"), false, 10);

  if ( $user->user_no > 0 ) {
    if ( $user->AllowedTo('update') ) {
    	$user_menu->AddOption( translate($user->EditMode?"View":"Edit")." ".$user->Values->fullname, "$c->base_url/usr.php?user_no=$user->user_no".($user->EditMode?"":"&edit=1"), translate(($user->EditMode?"View":"Edit")." this user record"), true, 900 );
    }
    else {
      $user_menu->AddOption( translate("View")." ".$user->Values->fullname, "$c->base_url/usr.php?user_no=$user->user_no", translate("View this user record"), true, 900 );
    }
  }

  include("page-header.php");
  echo $user->Render($c->page_title);
  include("page-footer.php");
?>
