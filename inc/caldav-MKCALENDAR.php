<?php

dbg_error_log("MKCALENDAR", "method handler");

$make_path = $_SERVER['PATH_INFO'];

$sql = "INSERT INTO calendar ( user_no, dav_name, dav_etag, created ) VALUES( ?, ?, ?, current_timestamp );";
$qry = new PgQuery( $sql, $session->user_no, $make_path, md5($session->user_no. $make_path) );

if ( $qry->Exec("MKCALENDAR",__LINE__,__FILE__) )
  header("HTTP/1.1 200 Created");
else
  header("HTTP/1.1 500 Infernal Server Error");

?>