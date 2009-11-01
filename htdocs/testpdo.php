<?php
require_once("../inc/always.php");
require_once("DAViCalSession.php");
$session->LoginRequired();

header('Content-type: text/plain; charset="UTF-8"');

require("AwlQuery.php");

echo "Creating query #1...\n";
$qry = new AwlQuery('SELECT * FROM collection WHERE is_calendar = :calendarness', array( ':calendarness' => true ) );
echo "Executing query...\n";
if ( $qry->Exec() ) {
  while( $row = $qry->Fetch() ) {
    printf( '  --> %-50.50s  %s  '."\n", $row->dav_name, $row->resourcetypes );
  }
}

echo "Creating query #2...\n";
$qry = new AwlQuery('SELECT * FROM collection WHERE is_calendar = false');
echo "Executing query...\n";
if ( $qry->Exec() ) {
  while( $row = $qry->Fetch() ) {
    printf( '  --> %-45.45s  %s  '."\n", $row->dav_name, $row->resourcetypes );
  }
}
