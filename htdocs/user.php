<?php
  require_once("always.php");
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
    $user_menu->AddOption(translate("New User"),"/user.php?create",translate("Add a new user"), false, 10);
  if ( $user->user_no > 0 && $user->AllowedTo('update') ) {
    $user_menu->AddOption(translate("View"),"/user.php?user_no=$user->user_no",translate("View this user record") );
    $user_menu->AddOption(translate("Edit"),"/user.php?edit=1&user_no=$user->user_no",translate("Edit this user record"), $user->EditMode );
  }

  include("page-header.php");
  echo $user->Render($c->page_title);
  include("page-footer.php");
?>