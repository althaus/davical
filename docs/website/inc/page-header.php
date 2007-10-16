<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>DAViCal CalDAV Server<?php
if ( isset($title) ) {
  echo " - ". $title;
}
?></title>
<link rel="stylesheet" type="text/css" href="style.css" />
</head>
<body>
<div id="pageContainer">
<div id="header">
<div id="title"><?php
if ( isset($title) ) {
  echo $title;
}
else {
<<<<<<< HEAD:docs/website/inc/page-header.php
  echo "DAViCal CalDAV Server";
=======
  echo "DAViCal";
>>>>>>> Rename to DAViCal in the documentation.:docs/website/inc/page-header.php
}
?></div>
<div id="subTitle">A CalDAV Store</div>
<div id="headerLinks">
<a href="index.php" class="hlink">Home</a> |
<a href="installation.php" class="hlink">Installation</a> |
<a href="clients.php" class="hlink">Client Config</a> |
<a href="administration.php" class="hlink">Administration</a> |
<a href="/moin/FrontPage" class="hlink">DAViCal Wiki</a> |
<a href="http://andrew.mcmillan.net.nz/" class="hlink">Blog</a> |
<a href="http://sourceforge.net/projects/rscds/" class="hlink">DAViCal on Sourceforge</a>
</div>
</div>
<div id="pageContent">
<?php
  $tags_to_be_closed = "</div>\n";
  if ( $two_panes ) {
    $tags_to_be_closed .= $tags_to_be_closed;
    echo '<div id="leftSide">';
  }
?>
<hr />
