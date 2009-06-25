<?php
  $title = "Client Configuration";
  $two_panes = true;
  include("inc/page-header.php");

  $dir = "clients";
  $clients = array();
  $screenshots = array();
  $icons = array();
  $details = array();
  if ( is_dir($dir)) {
    if ( $dh = opendir($dir) ) {
      while (($file = readdir($dh)) !== false ) {
        if ( preg_match( '#^([^/]+)-([^/-]+)\.([^/.-]+[^~])$#', $file, $matches ) ) {
          switch ( $matches[2] ) {
            case 'details':
              $details[$matches[1]] = $file;
              $clients[] = $matches[1];
              break;

            case 'icon':          $icons[$matches[1]] = $file;            break;
            case 'screenshot':    $screenshots[$matches[1]] = $file;      break;

            default:
              break;
          }
        }
      }
      closedir($dh);
    }
  }

  $client_page = "Interoperability";
  if ( isset( $_GET['client'] ) ) {
    if ( isset( $details[$_GET['client']] ) ) {
      $client_page = $_GET['client'];
    }
  }

  $style = ($client_page == "Interoperability" ? ' class="selected"' : '' );
  printf( '<p%s><a%s href="clients.php?client=Interoperability">Interoperability</a></p>', $style, $style );

  sort($clients);
  foreach( $clients AS $k => $v ) {
    if ( $v == "Interoperability" ) continue;
    if ( $v == "Other" ) continue;
    $style = (strcmp($client_page,$v) == 0 ? ' class="selected"' : '' );
    printf( '<p%s><a%s href="clients.php?client=%s">', $style, $style, urlencode($v) );
    if ( isset($icons[$v]) ) {
      printf( '<img src="clients/%s" alt="%s" /><br />', urlencode($icons[$v]), urlencode($v) );
    }
    echo "$v</a></p>\n";
  }

  $style = ($client_page == "Other" ? ' class="selected"' : '' );
  printf( '<p%s><a%s href="clients.php?client=Other">Other</a></p>', $style, $style );

  include("inc/page-middle.php");

  include("clients/".$details[$client_page]);

  if ( isset($screenshots[$client_page]) ) {
    printf( '</div><p><img src="clients/%s"></p>', urlencode($screenshots[$client_page]) );
    $tags_to_be_closed = "</div>\n";
  }

  include("inc/page-footer.php");
?>