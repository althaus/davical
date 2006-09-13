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

?>