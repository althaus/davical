<?php
if ( ! class_exists('MenuSet') ) include('MenuSet.php');


$home_menu = new MenuSet('submenu', 'submenu', 'submenu_active');
$home_menu->AddOption(translate('Logout'), $c->base_url.'/index.php?logout', translate('Log out of DAViCal') );

$wiki_help = '';
if ( isset($_SERVER['SCRIPT_NAME']) ) {
  $wiki_help = preg_replace('#^.*/#', '', $_SERVER['SCRIPT_NAME']);
  $wiki_help = preg_replace('#\.php.*$#', '', $wiki_help);
  $wiki_help = 'w/Help/'.$wiki_help;
}


$help_menu = new MenuSet('submenu', 'submenu', 'submenu_active');
$help_menu->AddOption(translate('DAViCal Homepage'),'http://www.davical.org/',translate('The DAViCal Home Page'), false, 6000, true );
$help_menu->AddOption(translate('DAViCal Wiki'),'http://wiki.davical.org/'.$wiki_help,translate('Visit the DAViCal Wiki'), false, 7000, true );
$help_menu->AddOption(translate('Request Feature'),'http://davical.uservoice.com/',translate('Go to the DAViCal Feature Requests'), false, 8000, true );
$help_menu->AddOption(translate('Report Bug'),'http://sourceforge.net/tracker/?func=add&group_id=179845&atid=890785',translate('Report a bug in the system'), false, 9000, true );

$user_menu = new MenuSet('submenu', 'submenu', 'submenu_active');
$user_menu->AddOption(translate('View My Details'),$c->base_url.'/usr.php?user_no='.$session->user_no,translate('View my own user record'));
$user_menu->AddOption(translate('List Users'),$c->base_url.'/davical.php?action=browse&t=principal&type=1');
$user_menu->AddOption(translate('List Resources'),$c->base_url.'/davical.php?action=browse&t=principal&type=2');
$user_menu->AddOption(translate('List Groups'),$c->base_url.'/davical.php?action=browse&t=principal&type=3');

$admin_menu = new MenuSet('submenu', 'submenu', 'submenu_active');
if ( $session->AllowedTo('Admin' )) {
  $admin_menu->AddOption(translate('Setup'),$c->base_url.'/setup.php',translate('Setup DAViCal') );
  $admin_menu->AddOption(translate('Upgrade Database'),$c->base_url.'/upgrade.php',translate('Upgrade DAViCal database schema') );
  $admin_menu->AddOption(translate('Import Calendars'),$c->base_url.'/tools.php',translate('Operations on your calendar') );
  $admin_menu->AddOption( translate('Relationships'), $c->base_url.'/relationship_types.php', translate('Browse all relationship types') );
  $user_menu->AddOption(translate('New User'),$c->base_url.'/usr.php?create',translate('Add a new user'));
}

$related_menu = new MenuSet('related', 'menu', 'menu_active');

$main_menu = new MenuSet('menu', 'menu', 'menu_active');
$main_menu->AddSubMenu($home_menu, translate('Home'), $c->base_url.'/index.php', translate('Home Page'), false, 1000);
$main_menu->AddSubMenu($user_menu, translate('User Functions'), $c->base_url.'/users.php', translate('Browse all users'), false, 2000);
$main_menu->AddSubMenu($admin_menu, translate('Administration'), $c->base_url.'/index.php', translate('Administration'), false, 3000);
$main_menu->AddSubMenu($help_menu, translate('Help'), $c->base_url.'/help.php',translate('Help on the current screen'), false, 9000);

