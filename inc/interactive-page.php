<?php
require_once("MenuSet.php");
$page_menu = new MenuSet('menu', 'menu', 'menu_active');
$page_menu->AddOption(translate("Home"),"/",translate("Browse all users"), false, 3900 );
$page_menu->AddOption(translate("Help"),"/help.php",translate("Help on something or other"), false, 4500 );
$page_menu->AddOption(translate("Logout"),"/?logout",translate("Log out of the").$c->system_name, false, 5400 );
$page_menu->AddOption(translate("Report Bug"),"http://sourceforge.net/tracker/?func=add&group_id=179845&atid=890785",translate("Report a bug in the system"), false, 9000 );

$relationship_menu = new MenuSet('submenu', 'submenu', 'submenu_active');
$user_menu = new MenuSet('submenu', 'submenu', 'submenu_active');
// $role_menu = new MenuSet('submenu', 'submenu', 'submenu_active');

$user_menu->AddOption(translate("My Details"),"/user.php?user_no=$session->user_no",translate("View my own user record"), false, 700);

$active_menu_pattern = '#^/(index.*)?$#'
?>