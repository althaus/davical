<?php

// Editor component for company records
$editor = new Editor(translate('Collection'), 'collection');
param_to_global('id', 'int', 'old_id', 'collection_id' );
param_to_global('user_no', 'int' );
param_to_global('collection_name', '{^[^\\\\/]+$}' );
if ( isset($user_no) ) $usr = GetUserByID($user_no);
$editor->SetLookup( 'timezone', 'SELECT \'\', \'*** Unknown ***\' UNION SELECT tz_id, tz_locn FROM time_zone WHERE tz_id = tz_locn AND length(tz_spec) > 100 ORDER BY 1' );
$editor->SetLookup( 'schedule_transp', sprintf('SELECT \'opaque\', \'%s\' UNION SELECT \'transp\', \'%s\'', translate('Opaque'), translate('Transparent') ) );


$editor->AddAttribute('timezone', 'id', 'fld_timezone' );
$editor->AddAttribute('schedule_transp', 'id', 'fld_schedule_transp' );
$editor->AddAttribute('is_calendar', 'onclick', 'toggle_enabled(self.checked,\'fld_timezone\',\'fld_schedule_transp\');');

$editor->SetWhere( 'collection_id='.$id );

$privilege_names = array( 'read', 'write-properties', 'write-content', 'unlock', 'read-acl', 'read-current-user-privilege-set',
                         'bind', 'unbind', 'write-acl', 'read-free-busy', 'schedule-deliver-invite', 'schedule-deliver-reply',
                         'schedule-query-freebusy', 'schedule-send-invite', 'schedule-send-reply', 'schedule-send-freebusy' );

$can_write_collection = ($session->AllowedTo('Admin') || $session->user_no == $user_no );

$pwstars = '@@@@@@@@@@';
if ( $can_write_collection && $editor->IsSubmit() ) {
  $editor->WhereNewRecord( "collection_id=(SELECT CURRVAL('dav_id_seq'))" );
  if ( isset($_POST['default_privileges']) ) {
    $privilege_bitpos = array_flip($privilege_names);
    $priv_names = array_keys($_POST['default_privileges']);
    $privs = privilege_to_bits($priv_names);
    $_POST['default_privileges'] = sprintf('%024s',decbin($privs));
    $editor->Assign('default_privileges', $privs_dec);
  }
  $is_update = ( $_POST['_editor_action'][$editor->Id] == 'update' );
  if ( !$is_update && isset($collection_name) && isset($user_no) && is_object($usr) ) {
    $_POST['dav_name'] = sprintf('/%s/%s/', $usr->username, $collection_name );
  }
  if ( $_POST['timezone'] == '' ) unset($_POST['timezone']);
  $editor->Write();
}
else {
  $editor->GetRecord();
}
if ( $editor->Available() ) {
  $c->page_title = $editor->Title(translate('Collection').': '.$editor->Value('dav_displayname'));
}
else {
  $c->page_title = $editor->Title(translate('Create New Collection'));
  $privs = decbin(privilege_to_bits($c->default_privileges));
  $editor->Assign('default_privileges', $privs);
  $editor->Assign('username', $usr->username);
  $editor->Assign('user_no', $usr->user_no);
}

$privilege_xlate = array(
  'read' => translate('Read'),
  'write-properties' => translate('Write Metadata'),
  'write-content' => translate('Write Data'),
  'unlock' => translate('Override a Lock'),
  'read-acl' => translate('Read Access Controls'),
  'read-current-user-privilege-set' => translate('Read Current User\'s Access'),
  'bind' => translate('Create Events/Collections'),
  'unbind' => translate('Delete Events/Collections'),
  'write-acl' => translate('Write Access Controls'),
  'read-free-busy' => translate('Read Free/Busy Information'),
  'schedule-deliver-invite' => translate('Scheduling: Deliver an Invitation'),
  'schedule-deliver-reply' => translate('Scheduling: Deliver a Reply'),
  'schedule-query-freebusy' => translate('Scheduling: Query free/busy'),
  'schedule-send-invite' => translate('Scheduling: Send an Invitation'),
  'schedule-send-reply' => translate('Scheduling: Send a Reply'),
  'schedule-send-freebusy' => translate('Scheduling: Send free/busy')
);


$default_privileges = bindec($editor->Value('default_privileges'));
$privileges_set = '<div id="privileges">';
for( $i=0; $i<count($privilege_names); $i++ ) {
  $privilege_set = ( (1 << $i) & $default_privileges ? ' CHECKED' : '');
  $privileges_set .= '<label class="privilege"><input name="default_privileges['.$privilege_names[$i].']" id="default_privileges_'.$privilege_names[$i].'" type="checkbox"'.$privilege_set.'>'.$privilege_xlate[$privilege_names[$i]].'</label>'."\n";
}
$privileges_set .= '</div>';

$prompt_collection_id = translate('Collection ID');
$value_id = ( $editor->Available() ? '##collection_id.hidden####collection_id.value##' : translate('New Collection'));
$prompt_dav_name = translate('DAV Path');
$value_dav_name = ( $editor->Available() ? '##dav_name.value##' : '/##user_no.hidden####username.value##/##collection_name.input.20##' );
$prompt_displayname = translate('Displayname');
$prompt_public = translate('Publicly Readable');
$prompt_calendar = translate('Is a Calendar');
$prompt_addressbook = translate('Is an Addressbook');
$prompt_privileges = translate('Default Privileges');
$prompt_description = translate('Description');
$prompt_schedule_transp = translate('Schedule Transparency');
$prompt_timezone = translate('Calendar Timezone');

$id = $editor->Value('collection_id');
$template = <<<EOTEMPLATE
##form##
<script language="javascript">
function toggle_privileges() {
  var argv = toggle_privileges.arguments;
  var argc = argv.length;

  if ( argc < 2 ) {
    return;
  }
  var match_me = argv[0];

  var set_to = -1;
  if ( argv[1] == 'all' ) {
    var form = document.getElementById(argv[2]);
    var fieldcount = form.elements.length;
    var matching = '/^' + match_me + '/';
    for (var i = 0; i < fieldcount; i++) {
      var fieldname = form.elements[i].name;
      if ( fieldname.match( match_me ) ) {
        if ( set_to == -1 ) {
          set_to = ( form.elements[i].checked ? 0 : 1 );
        }
        form.elements[i].checked = set_to;
      }
    }
  }
  else {
    for (var i = 1; i < argc; i++) {
      var f = document.getElementById( match_me + '_' + argv[i]);
      if ( set_to == -1 ) {
        set_to = ( f.checked ? 0 : 1 );
      }
      f.checked = set_to;
    }
  }
}

function toggle_enabled() {
  var argv = toggle_enabled.arguments;
  var argc = argv.length;

  if ( argc < 2 ) {
    return;
  }

  for (var i = 1; i < argc; i++) {
    var f = document.getElementById(argv[i]);
    f.disabled = !argv[0];
  }
}
</script>
<style>
th.right, label.privilege {
  white-space:nowrap;
}
label.privilege {
  margin:0.2em 1em 0.2em 0.1em;
  padding:0 0.2em;
  line-height:1.6em;
}
</style>
<table>
 <tr> <th class="right">$prompt_collection_id:</th>    <td class="left">$value_id</td> </tr>
 <tr> <th class="right">$prompt_dav_name:</th>         <td class="left">/caldav.php$value_dav_name</td> </tr>
 <tr> <th class="right">$prompt_displayname:</th>      <td class="left">##dav_displayname.input.50##</td> </tr>
 <tr> <th class="right">$prompt_public:</th>           <td class="left">##publicly_readable.checkbox##</td> </tr>
 <tr> <th class="right">$prompt_calendar:</th>         <td class="left">##is_calendar.checkbox##</td> </tr>
 <tr> <th class="right">$prompt_addressbook:</th>      <td class="left">##is_addressbook.checkbox##</td> </tr>
 <tr> <th class="right">$prompt_privileges:</th><td class="left">
<input type="button" value="All" class="submit" title="Toggle all privileges" onclick="toggle_privileges('default_privileges', 'all', 'editor_1');">
<input type="button" value="Read/Write" class="submit" title="Set read+write privileges"
 onclick="toggle_privileges('default_privileges', 'read', 'write-properties', 'write-content', 'bind', 'unbind', 'read-free-busy',
                            'read-current-user-privilege-set', 'schedule-deliver-invite', 'schedule-deliver-reply', 'schedule-query-freebusy',
                            'schedule-send-invite', 'schedule-send-reply', 'schedule-send-freebusy' );">
<input type="button" value="Read" class="submit" title="Set read privileges"
 onclick="toggle_privileges('default_privileges', 'read', 'read-free-busy', 'schedule-query-freebusy', 'read-current-user-privilege-set' );">
<input type="button" value="Free/Busy" class="submit" title="Set free/busy privileges"
 onclick="toggle_privileges('default_privileges', 'read-free-busy', 'schedule-query-freebusy' );">
<input type="button" value="Schedule Deliver" class="submit" title="Set schedule-deliver privileges"
 onclick="toggle_privileges('default_privileges', 'schedule-deliver-invite', 'schedule-deliver-reply', 'schedule-query-freebusy' );">
<input type="button" value="Schedule Send" class="submit" title="Set schedule-deliver privileges"
 onclick="toggle_privileges('default_privileges', 'schedule-send-invite', 'schedule-send-reply', 'schedule-send-freebusy' );">
<br>$privileges_set</td> </tr>
 <tr> <th class="right">$prompt_timezone:</th>         <td class="left">##timezone.select##</td> </tr>
 <tr> <th class="right">$prompt_schedule_transp:</th>  <td class="left">##schedule_transp.select##</td> </tr>
 <tr> <th class="right">$prompt_description:</th>      <td class="left">##description.textarea.78x6##</td> </tr>
 <tr> <th class="right"></th>                   <td class="left" colspan="2">##submit##</td> </tr>
</table>
</form>
EOTEMPLATE;

$editor->SetTemplate( $template );
$page_elements[] = $editor;


if ( $editor->Available() ) {

  $c->stylesheets[] = 'css/browse.css';
  $c->scripts[] = 'js/browse.js';


  $grantrow = new Editor("Grants", "grants");
  $grantrow->SetSubmitName( 'savegrantrow' );
  $grantrow->SetLookup( 'to_principal', 'SELECT principal_id, displayname FROM dav_principal WHERE principal_id NOT IN (SELECT member_id FROM group_member WHERE group_id = '.$id.')' );
  if ( $can_write_collection ) {
    if ( $grantrow->IsSubmit() ) {
      $_POST['by_collection'] = $id;
      $to_principal = intval($_POST['to_principal']);
      $orig_to_id =  intval($_POST['orig_to_id']);
      $grantrow->SetWhere( "by_collection=".qpg($id)." AND to_principal=$orig_to_id");
      if ( isset($_POST['grant_privileges']) ) {
        $privilege_bitpos = array_flip($privilege_names);
        $priv_names = array_keys($_POST['grant_privileges']);
        $privs = privilege_to_bits($priv_names);
        $_POST['privileges'] = sprintf('%024s',decbin($privs));
        $grantrow->Assign('privileges', $privs_dec);
      }
      $grantrow->Write( );
      unset($_GET['to_principal']);
    }
    elseif ( isset($_GET['delete_grant']) ) {
      $qry = new AwlQuery("DELETE FROM grants WHERE by_collection=:grantor_id AND to_principal = :to_principal",
                            array( ':grantor_id' => $id, ':to_principal' => intval($_GET['delete_grant']) ));
      $qry->Exec('collection-edit');
    }
  }

  function edit_grant_row( $row_data ) {
    global $grantrow, $id, $privilege_xlate, $privilege_names;

    if ( $row_data->to_principal > -1 ) {
      $grantrow->SetRecord( $row_data );
    }

    $grant_privileges = bindec($grantrow->Value('grant_privileges'));
    $privileges_set = '<div id="privileges">';
    for( $i=0; $i < count($privilege_names); $i++ ) {
      $privilege_set = ( (1 << $i) & $grant_privileges ? ' CHECKED' : '');
      $privileges_set .= '<label class="privilege"><input name="grant_privileges['.$privilege_names[$i].']" id="grant_privileges_'.$privilege_names[$i].'" type="checkbox"'.$privilege_set.'>'.$privilege_xlate[$privilege_names[$i]].'</label>'."\n";
    }
    $privileges_set .= '</div>';

    $orig_to_id = $row_data->to_principal;
    $form_id = $grantrow->Id();
    $form_url = preg_replace( '#&(edit|delete)_grant=\d+#', '', $_SERVER['REQUEST_URI'] );

    $template = <<<EOTEMPLATE
<form method="POST" enctype="multipart/form-data" id="form_$form_id" action="$form_url">
  <td class="left" colspan="2"><input type="hidden" name="id" value="$id"><input type="hidden" name="orig_to_id" value="$orig_to_id">##to_principal.select##</td>
  <td class="left" colspan="2">
<input type="button" value="All" class="submit" title="Toggle all privileges" onclick="toggle_privileges('grant_privileges', 'all', 'form_$form_id');">
<input type="button" value="Read/Write" class="submit" title="Set read+write privileges"
onclick="toggle_privileges('grant_privileges', 'read', 'write-properties', 'write-content', 'bind', 'unbind', 'read-free-busy',
                            'read-current-user-privilege-set', 'schedule-deliver-invite', 'schedule-deliver-reply', 'schedule-query-freebusy',
                            'schedule-send-invite', 'schedule-send-reply', 'schedule-send-freebusy' );">
<input type="button" value="Read" class="submit" title="Set read privileges"
onclick="toggle_privileges('grant_privileges', 'read', 'read-free-busy', 'schedule-query-freebusy', 'read-current-user-privilege-set' );">
<input type="button" value="Free/Busy" class="submit" title="Set free/busy privileges"
onclick="toggle_privileges('grant_privileges', 'read-free-busy', 'schedule-query-freebusy' );">
<input type="button" value="Schedule Deliver" class="submit" title="Set schedule-deliver privileges"
onclick="toggle_privileges('grant_privileges', 'schedule-deliver-invite', 'schedule-deliver-reply', 'schedule-query-freebusy' );">
<input type="button" value="Schedule Send" class="submit" title="Set schedule-deliver privileges"
onclick="toggle_privileges('grant_privileges', 'schedule-send-invite', 'schedule-send-reply', 'schedule-send-freebusy' );">
<br>$privileges_set
  <td class="center">##submit##</td>
</form>

EOTEMPLATE;

    $grantrow->SetTemplate( $template );
    $grantrow->Title("");

    return $grantrow->Render();
  }

  $browser = new Browser(translate('Collection Grants'));

  $browser->AddColumn( 'to_principal', translate('To ID'), 'right', '##principal_link##' );
  $rowurl = $c->base_url . '/admin.php?action=edit&t=collection&id=';
  $browser->AddHidden( 'principal_link', "'<a href=\"$rowurl' || to_principal || '\">' || to_principal || '</a>'" );
  $browser->AddHidden( 'grant_privileges', 'privileges' );
  $browser->AddColumn( 'displayname', translate('Display Name') );
  $browser->AddColumn( 'privs', translate('Privileges'), '', '', 'privileges_list(privileges)' );
  $browser->AddColumn( 'members', translate('Has Members'), '', '', 'has_members_list(principal_id)' );

  if ( $can_write_collection ) {
    $del_link  = "<a href=\"/admin.php?action=edit&t=collection&id=$id&delete_grant=##to_principal##\" class=\"submit\">Delete</a>";
    $edit_link  = "<a href=\"/admin.php?action=edit&t=collection&id=$id&edit_grant=##to_principal##\" class=\"submit\">Edit</a>";
    $browser->AddColumn( 'action', 'Action', 'center', '', "'$edit_link&nbsp;$del_link'" );
  }

  $browser->SetOrdering( 'displayname', 'A' );

  $browser->SetJoins( "grants LEFT JOIN dav_principal ON (to_principal = principal_id) " );
  $browser->SetWhere( 'by_collection = '.$id );

  if ( $c->enable_row_linking ) {
    $browser->RowFormat( '<tr onMouseover="LinkHref(this,1);" title="'.translate('Click to edit principal details').'" class="r%d">', '</tr>', '#even' );
  }
  else {
    $browser->RowFormat( '<tr class="r%d">', '</tr>', '#even' );
  }
  $browser->DoQuery();
  $page_elements[] = $browser;

  if ( $can_write_collection ) {
    if ( isset($_GET['edit_grant']) ) {
      $browser->MatchedRow('to_principal', $_GET['edit_grant'], 'edit_grant_row');
    }
    else {
      $extra_row = array( 'to_principal' => -1 );
      $browser->MatchedRow('to_principal', -1, 'edit_grant_row');
      $extra_row = (object) $extra_row;
      $browser->AddRow($extra_row);
    }
  }
}

