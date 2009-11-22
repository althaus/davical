<?php

// Editor component for company records
$editor = new Editor(translate('Principal'), 'dav_principal');
$editor->AddField( 'date_format_type', null, "SELECT 'E', 'European' UNION SELECT 'U', 'US Format' UNION SELECT 'I', 'ISO Format'" );
$editor->AddField( 'type_id', null, 'SELECT principal_type_id, principal_type_desc FROM principal_type ORDER BY principal_type_id' );
param_to_global('id', 'int', 'old_id', 'principal_id' );
$editor->SetWhere( 'principal_id='.$id );

$privilege_names = array( 'read', 'write-properties', 'write-content', 'unlock', 'read-acl', 'read-current-user-privilege-set',
                         'bind', 'unbind', 'write-acl', 'read-free-busy', 'schedule-deliver-invite', 'schedule-deliver-reply',
                         'schedule-query-freebusy', 'schedule-send-invite', 'schedule-send-reply', 'schedule-send-freebusy' );

$pwstars = '@@@@@@@@@@';
if ( $editor->IsSubmit() ) {
  $editor->WhereNewRecord( "principal_id=(SELECT CURRVAL('dav_id_seq'))" );
  unset($_POST['password']);
  if ( $_POST['newpass1'] != '' && $_POST['newpass1'] != $pwstars ) {
    if ( $_POST['newpass1'] == $_POST['newpass2'] ) {
      $_POST['password'] = $_POST['newpass1'];
    }
    else {
      $c->messages[] = "Password not updated. The supplied passwords do not match.";
    }
  }
  if ( isset($_POST['default_privileges']) ) {
    $privilege_bitpos = array_flip($privilege_names);
    $priv_names = array_keys($_POST['default_privileges']);
    $privs = privilege_to_bits($priv_names);
    $_POST['default_privileges'] = sprintf('%024s',decbin($privs));
    $editor->Assign('default_privileges', $privs_dec);
  }
  $editor->Write();
}
else {
  $editor->GetRecord();
}
if ( $editor->Available() ) {
  $c->page_title = $editor->Title(translate('Principal').': '.$editor->Value('fullname'));
}
else {
  $c->page_title = $editor->Title(translate('Create New Principal'));
  $privs = decbin(privilege_to_bits($c->default_privileges));
  $editor->Assign('default_privileges', $privs);
}

$privilege_xlate = array(
  'read' => translate('Read'),
  'write-properties' => translate('Write Metadata'),
  'write-content' => translate('Write Data'),
  'unlock' => translate('Override a Lock'),
  'read-acl' => translate('Read Access Controls'),
  'read-current-user-privilege-set' => translate('Read Current User\'s Access'),
  'bind' => translate('Create Resources'),
  'unbind' => translate('Delete Resources'),
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
  $privileges_set .= '<label class="privilege"><input name="default_privileges['.$privilege_names[$i].']" id="priv_checkbox_'.$privilege_names[$i].'" type="checkbox"'.$privilege_set.'>'.$privilege_xlate[$privilege_names[$i]].'</label>'."\n";
}
$privileges_set .= '</div>';

$prompt_principal_id = translate('Principal ID');
$prompt_username = translate('Username');
$prompt_password_1 = translate('Change Password');
$prompt_password_1 = translate('Confirm Password');
$prompt_fullname = translate('Fullname');
$prompt_email = translate('Email Address');
$prompt_date_format = translate('Date Format Style');
$prompt_type = translate('Principal Type');
$prompt_privileges = translate('Default Privileges');

$id = $editor->Value('principal_id');
$template = <<<EOTEMPLATE
##form##
<script language="javascript">
function toggle_privileges() {
  var argv = toggle_privileges.arguments;
  var argc = argv.length;

  if ( argc < 1 ) {
    return;
  }

  var set_to = -1;
  if ( argv[0] == 'all' ) {
    var fieldcount = document.forms[0].elements.length;
    for (var i = 0; i < fieldcount; i++) {
      var fieldname = document.forms[0].elements[i].name;
      if ( fieldname.match( /^default_privileges/ ) ) {
        if ( set_to == -1 ) {
          set_to = ( document.forms[0].elements[i].checked ? 0 : 1 );
        }
        document.forms[0].elements[i].checked = set_to;
      }
    }
  }
  else {
    for (var i = 0; i < argc; i++) {
      var f = document.getElementById( 'priv_checkbox_' + argv[i]);
      if ( set_to == -1 ) {
        set_to = ( f.checked ? 0 : 1 );
      }
      f.checked = set_to;
    }
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
 <tr> <th class="right">$prompt_principal_id:</th>           <td class="left">##principal_id.value##</td> </tr>
 <tr> <th class="right">$prompt_username:</th>          <td class="left">##xxxxusername.input.10##</td> </tr>
 <tr> <th class="right">$prompt_password_1:</th>   <td class="left">##newpass1.password.$pwstars##</td> </tr>
 <tr> <th class="right">$prompt_password_1:</th>  <td class="left">##newpass2.password.$pwstars##</td> </tr>
 <tr> <th class="right">$prompt_fullname:</th>         <td class="left">##fullname.input.50##</td> </tr>
 <tr> <th class="right">$prompt_email:</th>             <td class="left">##email.input.50##</td> </tr>
 <tr> <th class="right">$prompt_date_format:</th>  <td class="left">##date_format_type.select##</td> </tr>
 <tr> <th class="right">$prompt_type:</th>    <td class="left">##type_id.select##</td> </tr>
 <tr> <th class="right">$prompt_privileges:</th><td class="left">
<input type="button" value="All" class="submit" title="Toggle all privileges" onclick="toggle_privileges('all');">
<input type="button" value="Read/Write" class="submit" title="Set read+write privileges"
 onclick="toggle_privileges('read', 'write-properties', 'write-content', 'bind', 'unbind', 'read-free-busy',
                            'read-current-user-privilege-set', 'schedule-deliver-invite', 'schedule-deliver-reply', 'schedule-query-freebusy',
                            'schedule-send-invite', 'schedule-send-reply', 'schedule-send-freebusy' );">
<input type="button" value="Read" class="submit" title="Set read privileges"
 onclick="toggle_privileges('read', 'read-free-busy', 'schedule-query-freebusy', 'read-current-user-privilege-set' );">
<input type="button" value="Free/Busy" class="submit" title="Set free/busy privileges"
 onclick="toggle_privileges('read-free-busy', 'schedule-query-freebusy' );">
<input type="button" value="Schedule Deliver" class="submit" title="Set schedule-deliver privileges"
 onclick="toggle_privileges('schedule-deliver-invite', 'schedule-deliver-reply', 'schedule-query-freebusy' );">
<input type="button" value="Schedule Send" class="submit" title="Set schedule-deliver privileges"
 onclick="toggle_privileges('schedule-send-invite', 'schedule-send-reply', 'schedule-send-freebusy' );">
<br>$privileges_set</td> </tr>
 <tr> <th class="right"></th>                   <td class="left" colspan="2">##submit##</td> </tr>
</table>
</form>
EOTEMPLATE;

$editor->SetTemplate( $template );
$page_elements[] = $editor;


$browser = new Browser(translate('Group Memberships'));
$c->stylesheets[] = 'css/browse.css';
$c->scripts[] = 'js/browse.js';

$browser->AddColumn( 'group_id', translate('ID'), 'right', '##principal_link##' );
$rowurl = $c->base_url . '/davical.php?action=edit&t=principal&id=';
$browser->AddHidden( 'principal_link', "'<a href=\"$rowurl' || principal_id || '\">' || principal_id || '</a>'" );
$browser->AddColumn( 'displayname', translate('Display Name') );
$browser->AddColumn( 'member_of', translate('Is Member of'), '', '', 'is_member_of_list(principal_id)' );
$browser->AddColumn( 'members', translate('Has Members'), '', '', 'has_members_list(principal_id)' );

$browser->SetOrdering( 'displayname', 'A' );

$browser->SetJoins( "group_member LEFT JOIN dav_principal ON (group_id = principal_id) " );
$browser->SetWhere( 'user_active AND member_id = '.$id );

if ( $c->enable_row_linking ) {
  $browser->RowFormat( '<tr onMouseover="LinkHref(this,1);" title="'.translate('Click to edit principal details').'" class="r%d">', '</tr>', '#even' );
}
else {
  $browser->RowFormat( '<tr class="r%d">', '</tr>', '#even' );
}
$browser->DoQuery();
$page_elements[] = $browser;


if ( $editor->Value('type_id') == 3 ) {
  $browser = new Browser(translate('Group Members'));

  $browser->AddColumn( 'group_id', translate('ID'), 'right', '##principal_link##' );
  $rowurl = $c->base_url . '/davical.php?action=edit&t=principal&id=';
  $browser->AddHidden( 'principal_link', "'<a href=\"$rowurl' || principal_id || '\">' || principal_id || '</a>'" );
  $browser->AddColumn( 'displayname', translate('Display Name') );
  $browser->AddColumn( 'member_of', translate('Is Member of'), '', '', 'is_member_of_list(principal_id)' );
  $browser->AddColumn( 'members', translate('Has Members'), '', '', 'has_members_list(principal_id)' );

  $browser->SetOrdering( 'displayname', 'A' );

  $browser->SetJoins( "group_member LEFT JOIN dav_principal ON (member_id = principal_id) " );
  $browser->SetWhere( 'user_active AND group_id = '.$id );

  if ( $c->enable_row_linking ) {
    $browser->RowFormat( '<tr onMouseover="LinkHref(this,1);" title="'.translate('Click to edit principal details').'" class="r%d">', '</tr>', '#even' );
  }
  else {
    $browser->RowFormat( '<tr class="r%d">', '</tr>', '#even' );
  }
  $browser->DoQuery();
  $page_elements[] = $browser;
}


$browser = new Browser(translate('Principal Grants'));

$browser->AddColumn( 'to_principal', translate('To ID'), 'right', '##principal_link##' );
$rowurl = $c->base_url . '/davical.php?action=edit&t=principal&id=';
$browser->AddHidden( 'principal_link', "'<a href=\"$rowurl' || to_principal || '\">' || to_principal || '</a>'" );
$browser->AddColumn( 'displayname', translate('Display Name') );
$browser->AddColumn( 'privs', translate('Privileges'), '', '', 'privileges_list(privileges)' );
$browser->AddColumn( 'members', translate('Has Members'), '', '', 'has_members_list(principal_id)' );

$browser->SetOrdering( 'displayname', 'A' );

$browser->SetJoins( "grants LEFT JOIN dav_principal ON (to_principal = principal_id) " );
$browser->SetWhere( 'by_principal = '.$id );

if ( $c->enable_row_linking ) {
  $browser->RowFormat( '<tr onMouseover="LinkHref(this,1);" title="'.translate('Click to edit principal details').'" class="r%d">', '</tr>', '#even' );
}
else {
  $browser->RowFormat( '<tr class="r%d">', '</tr>', '#even' );
}
$browser->DoQuery();
$page_elements[] = $browser;


$browser = new Browser(translate('Principal Collections'));

$browser->AddColumn( 'collection_id', translate('ID'), 'right', '##collection_link##' );
$rowurl = $c->base_url . '/davical.php?action=edit&t=collection&id=';
$browser->AddHidden( 'collection_link', "'<a href=\"$rowurl' || collection_id || '\">' || collection_id || '</a>'" );
$browser->AddColumn( 'dav_name', translate('Path') );
$browser->AddColumn( 'dav_displayname', translate('Display Name') );
$browser->AddColumn( 'privs', translate('Privileges'), '', '', 'privileges_list(default_privileges)' );

$browser->SetOrdering( 'dav_name', 'A' );

$browser->SetJoins( "collection " );
$browser->SetWhere( 'user_no = '.intval($editor->Value('user_no')) );

if ( $c->enable_row_linking ) {
  $browser->RowFormat( '<tr onMouseover="LinkHref(this,1);" title="'.translate('Click to edit principal details').'" class="r%d">', '</tr>', '#even' );
}
else {
  $browser->RowFormat( '<tr class="r%d">', '</tr>', '#even' );
}
$browser->DoQuery();
$page_elements[] = $browser;


