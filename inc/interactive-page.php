<?php
require_once("session-util.php");
require_once("MenuSet.php");
$page_menu = new MenuSet('menu', 'menu', 'menu_active');
$page_menu->AddOption("Home","/","Browse all users", false, 3900 );
$page_menu->AddOption("Help","/help.php","Help on something or other", false, 4500 );
$page_menu->AddOption("Logout","/?logout","Log out of the $c->system_name", false, 5400 );

$relationship_menu = new MenuSet('submenu', 'submenu', 'submenu_active');
$user_menu = new MenuSet('submenu', 'submenu', 'submenu_active');
$role_menu = new MenuSet('submenu', 'submenu', 'submenu_active');

$active_menu_pattern = '#^/(index.*)?$#'
?>