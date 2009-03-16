</body><?php
  if ( isset($c->scripts) && is_array($c->scripts) ) {
    foreach ( $c->scripts AS $script ) {
      echo "<script language=\"JavaScript\" src=\"$script\"></script>\n";
    }
  }
?>
</html>