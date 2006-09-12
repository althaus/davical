<?php
require_once("always.php");
require_once("RSCDSSession.php");
$session->LoginRequired();

?>
<html>
<head>
<meta/>
<title>Really Simple CalDAV Store</title>
</head>
<body>
<h1>These are the admin pages...</h1>
<?php
  echo "<p>You appear to be logged on as $session->username ($session->fullname)</p>";
?>
</body>
</html>