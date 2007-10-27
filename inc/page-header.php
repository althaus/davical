<?php

if ( !isset($c->page_title) ) {
  $c->page_title = translate("Really Simple CalDAV Store");
}

function make_help_link($matches)
{
  global $c;

  // as usual: $matches[0] is the complete match
  // $matches[1] the match for the first subpattern
  // enclosed in '##...##' and so on
  // Use like: $s = preg_replace_callback("/##([^#]+)##", "make_help_link", $s);
//  $help_topic = preg_replace( '/^##(.+)##$/', '$1', $matches[1]);
  $help_topic = $matches[1];
  $display_url = $help_topic;
  if ( $GLOBALS['session']->AllowedTo("Admin") || $GLOBALS['session']->AllowedTo("Support") ) {
    if ( strlen($display_url) > 30 ) {
      $display_url = substr( $display_url, 0, 28 ) . "..." ;
    }
  }
  else {
    $display_url = "help";
  }
  return " <a class=\"help\" href=\"$c->base_url/help.php?h=$help_topic\" title=\"".translate("Show help on")." '$help_topic'\" target=\"_new\">[$display_url]</a> ";
}


function send_page_header() {
  global $session, $c, $page_menu, $user_menu, $role_menu, $relationship_menu, $active_menu_pattern;

//  echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">';

  header( "Content-type: text/html; charset=utf-8" );

  echo <<<EOHDR
<html>
<head>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8"/>
<title>$c->page_title</title>

EOHDR;

  foreach ( $c->stylesheets AS $stylesheet ) {
    echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"$stylesheet\" />\n";
  }
  if ( isset($c->local_styles) ) {
    // Always load local styles last, so they can override prior ones...
    foreach ( $c->local_styles AS $stylesheet ) {
      echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"$stylesheet\" />\n";
    }
  }

  if ( isset($c->print_styles) ) {
    // Finally, load print styles last, so they can override all of the above...
    foreach ( $c->print_styles AS $stylesheet ) {
      echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"$stylesheet\" media=\"print\"/>\n";
    }
  }

  if ( isset($c->scripts) && is_array($c->scripts) ) {
    foreach ( $c->scripts AS $script ) {
      echo "<script language=\"JavaScript\" src=\"$script\"></script>\n";
    }
  }

  echo "</head>\n<body>\n";
  echo "<div id=\"pageheader\">\n";

  if ( isset($page_menu) && is_object($page_menu) ) {
    if ( isset($relationship_menu) && is_object($relationship_menu) ) {
      $page_menu->AddSubMenu( $relationship_menu, translate("Relationships"),
                               "$c->base_url/relationship_types.php", translate("Browse all relationship types"), false, 4050 );
    }
    $page_menu->AddSubMenu( $user_menu, translate("Users"), "$c->base_url/users.php", translate("Browse all users"), false, 4100 );
//    $page_menu->AddSubMenu( $role_menu, "Roles", "/roles.php", "Browse all roles", false, 4300 );
    $page_menu->MakeSomethingActive($active_menu_pattern);
    echo $page_menu->Render();
  }

  echo "</div>\n";

  if ( isset($c->messages) && is_array($c->messages) && count($c->messages) > 0 ) {
    echo "<div id=\"messages\"><ul class=\"messages\">\n";
    foreach( $c->messages AS $i => $msg ) {
      // ##HelpTextKey## gets converted to a "/help.phph=HelpTextKey" link
      $msg = preg_replace_callback("/##([^#]+)##/", "make_help_link", translate($msg));
      echo "<li class=\"messages\">$msg</li>\n";
    }
    echo "</ul>\n</div>\n";
  }

}

send_page_header();

?>