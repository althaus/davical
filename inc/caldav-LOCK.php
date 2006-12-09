<?php
/**
* We support both LOCK and UNLOCK methods in this function
*/

if ( ! $request->AllowedTo('write') ) {
  $request->DoResponse( 403, translate("You do not have sufficient access to lock that") );
}

require_once("XMLElement.php");

$unsupported = array();
$lockinfo = array();
$inside = array();

foreach( $request->xml_tags AS $k => $v ) {

  $tag = $v['tag'];
  dbg_error_log( "LOCK", " Handling Tag '%s' => '%s' ", $k, $v );
  switch ( $tag ) {
    case 'DAV::LOCKINFO':
      dbg_error_log( "LOCK", ":Request: %s -> %s", $v['type'], $tag );
      if ( $v['type'] == "open" ) {
        $lockscope = "";
        $locktype = "";
        $lockowner = "";
        $inside[$tag] = true;
      }
      else if ( $inside[$tag] && $v['type'] == "close" ) {
        $lockinfo['scope']  = $lockscope;   unset($lockscope);
        $lockinfo['type']   = $locktype;    unset($locktype);
        $lockinfo['owner']  = $lockowner;   unset($lockowner);
        $inside[$tag] = false;
      }
      break;

    case 'DAV::OWNER':
    case 'DAV::LOCKTYPE':
    case 'DAV::LOCKSCOPE':
      dbg_error_log( "LOCK", ":Request: %s -> %s", $v['type'], $tag );
      if ( $inside['DAV::LOCKINFO'] ) {
        if ( $v['type'] == "open" ) {
          $inside[$tag] = true;
        }
        else if ( $inside[$tag] && $v['type'] == "close" ) {
          $inside[$tag] = false;
        }
      }
      break;

    /*case 'DAV::SHARED': */ /** Shared lock is not supported yet */
    case 'DAV::EXCLUSIVE':
      dbg_error_log( "LOCK", ":Request: %s -> %s", $v['type'], $tag );
      if ( $inside['DAV::LOCKSCOPE'] && $v['type'] == "complete" ) {
        $lockscope = strtolower(substr($tag,5));
      }
      break;

    /* case 'DAV::READ': */ /** RFC2518 is pretty vague about read locks */
    case 'DAV::WRITE':
      dbg_error_log( "LOCK", ":Request: %s -> %s", $v['type'], $tag );
      if ( $inside['DAV::LOCKTYPE'] && $v['type'] == "complete" ) {
        $locktype = strtolower(substr($tag,5));
      }
      break;

    case 'DAV::HREF':
      dbg_error_log( "LOCK", ":Request: %s -> %s", $v['type'], $tag );
      dbg_log_array( "LOCK", "DAV:HREF", $v, true );
      if ( $inside['DAV::OWNER'] && $v['type'] == "complete" ) {
        $lockowner = $v['value'];
      }
      break;

    default:
      if ( preg_match('/^(.*):([^:]+)$/', $tag, $matches) ) {
        $unsupported[$matches[2]] = $matches[1];
      }
      else {
        $unsupported[$tag] = "";
      }
      dbg_error_log( "LOCK", "Unhandled tag >>%s<<", $tag);
  }
}




if ( count($unsupported) > 0 ) {
  /**
  * That's a *BAD* request!
  */
  $badprops = new XMLElement( "prop" );
  foreach( $unsupported AS $k => $v ) {
    // Not supported at this point...
    dbg_error_log("ERROR", " LOCK: Support for $v:$k properties is not implemented yet");
    $badprops->NewElement(strtolower($k),false,array("xmlns" => strtolower($v)));
  }
  $error = new XMLElement("error", new XMLElement( "LOCK",$badprops), array("xmlns" => "DAV:") );

  $request->DoResponse( 403, $error->Render(0,'<?xml version="1.0" ?>'), 'text/xml; charset="utf-8"');
}


$lock_opener = $request->FailIfLocked();


if ( $request->method == "LOCK" ) {
  dbg_error_log( "LOCK", "Attempting to lock resource '%s'", $request->path);
  if ( ($lock_token = $request->IsLocked()) ) { // NOTE Assignment in if() is expected here.
    $sql = "UPDATE locks SET start = current_timestamp WHERE opaquelocktoken = ?;";
    $qry = new PgQuery($sql, $lock_token );
    $qry->Exec("LOCK",__LINE__,__FILE__);
  }
  else {
    /**
    * A fresh lock
    */
    $lock_token = uuid();
    $sql = "INSERT INTO locks ( dav_name, opaquelocktoken, type, scope, depth, owner, timeout, start ) VALUES( ?, ?, ?, ?, ?, ?, ?::interval, current_timestamp );";
    $qry = new PgQuery($sql, $request->path, $lock_token, $lockinfo['type'], $lockinfo['scope'], $request->depth, $lockinfo['owner'], $request->timeout.' seconds' );
    $qry->Exec("LOCK",__LINE__,__FILE__);
    header( "Lock-Token: <opaquelocktoken:$lock_token>" );
  }

  $lock_row = $request->GetLockRow($lock_token);
  $activelock = array(
      new XMLElement( 'locktype',  new XMLElement( $lock_row->type )),
      new XMLElement( 'lockscope', new XMLElement( $lock_row->scope )),
      new XMLElement( 'depth',     $request->GetDepthName() ),
      new XMLElement( 'owner',     new XMLElement( 'href', $lock_row->owner )),
      new XMLElement( 'timeout',   'Second-'.$request->timeout),
      new XMLElement( 'locktoken', new XMLElement( 'href', 'opaquelocktoken:'.$lock_token ))
  );
  $response = new XMLElement("lockdiscovery", new XMLElement( "activelock", $activelock), array("xmlns" => "DAV:") );
}
elseif (  $request->method == "UNLOCK" ) {
  dbg_error_log( "LOCK", "Attempting to unlock resource '%s'", $request->path);
  if ( ($lock_token = $request->IsLocked()) ) { // NOTE Assignment in if() is expected here.
    $sql = "DELETE FROM locks WHERE opaquelocktoken = ?;";
    $qry = new PgQuery($sql, $lock_token );
    $qry->Exec("LOCK",__LINE__,__FILE__);
  }
  $request->DoResponse( 204 );
}


$prop = new XMLElement( "prop", $response, array('xmlns'=>'DAV:') );
// dbg_log_array( "LOCK", "XML", $response, true );
$xmldoc = $prop->Render(0,'<?xml version="1.0" encoding="utf-8" ?>');
$request->DoResponse( 200, $xmldoc, 'text/xml; charset="utf-8"' );

?>