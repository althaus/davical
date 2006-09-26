<?php

dbg_error_log("MKCALENDAR", "method handler");

$attributes = array();
$parser = xml_parser_create_ns('UTF-8');
xml_parser_set_option ( $parser, XML_OPTION_SKIP_WHITE, 1 );

function xml_start_callback( $parser, $el_name, $el_attrs ) {
  dbg_error_log( "PROPFIND", "Parsing $el_name" );
  dbg_log_array( "PROPFIND", "$el_name::attrs", $el_attrs, true );
  $attributes[$el_name] = $el_attrs;
}

function xml_end_callback( $parser, $el_name ) {
  dbg_error_log( "PROPFIND", "Finished Parsing $el_name" );
}

xml_set_element_handler ( $parser, 'xml_start_callback', 'xml_end_callback' );

$rpt_request = array();
xml_parse_into_struct( $parser, $raw_post, $rpt_request );
xml_parser_free($parser);

$make_path = $_SERVER['PATH_INFO'];

/**
* FIXME We kind of lie, at this point
*/
header("HTTP/1.1 200 Created");

?>