<?php
/**
* DAViCal Timezone Service handler
*
* @package   davical
* @subpackage   tzservice
* @author    Andrew McMillan <andrew@morphoss.com>
* @copyright Morphoss Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/
require("./always.php");
require("PublicSession.php");
$session = new PublicSession();

param_to_global('action','{[a-z_-]+}');
param_to_global('format','{[a-z]+/[a-zA-Z0-9.+_-]+}');
param_to_global('changesince');
param_to_global('start');
param_to_global('end');
param_to_global('lang');
$returnall = isset($_GET['returnall']);
param_to_global('tzid');

require_once('CalDAVRequest.php');
$request = new CalDAVRequest();

$code_file = sprintf( 'tz/%s.php', $action );
if ( ! @include_once( $code_file ) ) {
  $request->PreconditionFailed(400, "supported-action", 'The action "'.$_GET['action'].'" is not understood.' );
}

$request->DoResponse( 500, translate("The application failed to understand that request.") );

