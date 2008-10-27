<?php
$save = error_reporting(0);
require_once("MenuSet.php");
$page_menu = new MenuSet('menu', 'menu', 'menu_active');
$page_menu->AddOption(translate("Home"),"$c->base_url/index.php",translate("Browse all users"), false, 3900 );
if ( $session->AllowedTo("Admin" )) {
//  $page_menu->AddOption(translate("Setup"),"$c->base_url/setup.php",translate("Setup RSCDS"), false, 5000 );
  $page_menu->AddOption(translate("Operations"),"$c->base_url/tools.php",translate("Operations on your calendar"), false, 5200 );

  $relationship_menu = new MenuSet('submenu', 'submenu', 'submenu_active');
}
$page_menu->AddOption(translate("Logout"),"$c->base_url/index.php?logout",translate("Log out of the").$c->system_name, false, 5400 );
$page_menu->AddOption(translate("Help"),"$c->base_url/help.php",translate("Help on something or other"), false, 8500 );
$page_menu->AddOption(translate("Report Bug"),"http://sourceforge.net/tracker/?func=add&group_id=179845&atid=890785",translate("Report a bug in the system"), false, 9000 );

$user_menu = new MenuSet('submenu', 'submenu', 'submenu_active');

$user_menu->AddOption(translate("My Details"),"$c->base_url/usr.php?user_no=$session->user_no",translate("View my own user record"), false, 700);

$active_menu_pattern = "#^$c->base_url/(index.*)?$#";
error_reporting($save);
