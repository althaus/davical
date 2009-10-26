<?php
  require_once("../inc/always.php");
  require_once("DAViCalSession.php");

  // This page requires login.
  $session->LoginRequired();
  require_once("interactive-page.php");

  require_once("DAViCalUser.php");

  $user_no = intval(isset($_POST['user_no']) ? $_POST['user_no'] : (isset($_GET['user_no'])?$_GET['user_no']:0) );
  $user = new DAViCalUser($user_no);
  if ( 'insert' == $user->WriteType && $user->user_no > 0 ) {
    $user = new DAViCalUser(0);
  }
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
          $user = new DAViCalUser($user->user_no);
          $user->EditMode = true;
          if ( $user->user_no == 0 ) {
            $c->page_title = ( $user_no != "" ? translate("User Unavailable") : translate("New User") );
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
          $user = new DAViCalUser($user->user_no);
          $user->EditMode = true;
        }
      }
    }
  }

  $related_menu->AddOption( sprintf(translate("Edit %s"), $user->Get('fullname')), $c->base_url.'/usr.php?user_no='.$user->user_no.'&edit=1', translate("Edit this user record"), true, 900 );
  $related_menu->AddOption( sprintf(translate("View %s"), $user->Get('fullname')), $c->base_url.'/usr.php?user_no='.$user->user_no, translate("View this user record"), true, 900 );
  if ( $user->user_no > 0 ) {
    if ( $user->AllowedTo('update') && ! $user->EditMode ) {
    	$related_menu->AddOption( sprintf(translate("Edit %s"), $user->Get('fullname')), "$c->base_url/usr.php?user_no=$user->user_no&edit=1", translate("Edit this user record"), true, 900 );
    }
    else {
      $related_menu->AddOption( sprintf(translate("View %s"), $user->Get('fullname')), "$c->base_url/usr.php?user_no=$user->user_no", translate("View this user record"), true, 900 );
    }
  }

  include("page-header.php");
  echo $user->Render($c->page_title);
  include("page-footer.php");
