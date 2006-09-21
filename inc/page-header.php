<?php

if ( !isset($c->title) ) {
  $c->title = "Really Simple CalDAV Store";
}

echo <<<EOHDR
<html>
<head>
<meta/>
<title>$c->title</title>
<link rel="stylesheet" type="text/css" href="/rscds.css" />
</head>
<body>
EOHDR;

if ( isset($page_menu) && is_object($page_menu) ) {
  $page_menu->AddSubMenu( $relationship_menu, "Relationships", "/relationships.php", "Browse all relationships", false, 4050 );
  $page_menu->AddSubMenu( $user_menu, "Users", "/users.php", "Browse all users", false, 4100 );
  $page_menu->AddSubMenu( $role_menu, "Roles", "/roles.php", "Browse all roles", false, 4300 );
  $page_menu->MakeSomethingActive($active_menu_pattern);
  echo $page_menu->Render();
}
?>