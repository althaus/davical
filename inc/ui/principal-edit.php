<?php

// Editor component for company records
$editor = new Editor(translate('Principal'), 'dav_principal');
$editor->SetLookup( 'date_format_type', "SELECT 'E', 'European' UNION SELECT 'U', 'US Format' UNION SELECT 'I', 'ISO Format'" );
$editor->SetLookup( 'type_id', 'SELECT principal_type_id, principal_type_desc FROM principal_type ORDER BY principal_type_id' );
param_to_global('id', 'int', 'old_id', 'principal_id' );
$editor->SetWhere( 'principal_id='.$id );

$privilege_names = array( 'read', 'write-properties', 'write-content', 'unlock', 'read-acl', 'read-current-user-privilege-set',
                         'bind', 'unbind', 'write-acl', 'read-free-busy', 'schedule-deliver-invite', 'schedule-deliver-reply',
                         'schedule-query-freebusy', 'schedule-send-invite', 'schedule-send-reply', 'schedule-send-freebusy' );

/**
* @Todo: This needs to open up somewhat.
*/
$can_write_principal = ($session->AllowedTo('Admin') || $session->principal_id == $id );
$pwstars = '@@@@@@@@@@';
if ( $can_write_principal && $editor->IsSubmit() ) {
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
 <tr> <th class="right">$prompt_username:</th>          <td class="left">##xxxxusername.input.50##</td> </tr>
 <tr> <th class="right">$prompt_password_1:</th>   <td class="left">##newpass1.password.$pwstars##</td> </tr>
 <tr> <th class="right">$prompt_password_1:</th>  <td class="left">##newpass2.password.$pwstars##</td> </tr>
 <tr> <th class="right">$prompt_fullname:</th>         <td class="left">##fullname.input.50##</td> </tr>
 <tr> <th class="right">$prompt_email:</th>             <td class="left">##email.input.50##</td> </tr>
 <tr> <th class="right">$prompt_date_format:</th>  <td class="left">##date_format_type.select##</td> </tr>
 <tr> <th class="right">$prompt_type:</th>    <td class="left">##type_id.select##</td> </tr>
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
$rowurl = $c->base_url . '/admin.php?action=edit&t=principal&id=';
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

  $grouprow = new Editor("Group Members", "group_member");
  $grouprow->SetLookup( 'member_id', 'SELECT principal_id, displayname FROM dav_principal WHERE principal_id NOT IN (SELECT member_id FROM group_member WHERE group_id = '.$id.')');
  $grouprow->SetSubmitName( 'savegrouprow' );

  if ( $can_write_principal ) {
    if ( $grouprow->IsSubmit() ) {
      if ( $grouprow->IsUpdate() ) {
        $c->messages[] = translate('Updating Member of this Group Principal');
      else
        $c->messages[] = translate('Adding new member to this Group Principal');

      $_POST['group_id'] = $id;
      $member_id = intval($_POST['member_id']);
      $grouprow->SetWhere( "group_id=".qpg($id)." AND member_id=$member_id");
      $grouprow->Write( );
      unset($_GET['member_id']);
    }
    elseif ( isset($_GET['delete_member']) ) {
      $qry = new AwlQuery("DELETE FROM group_member WHERE group_id=:group_id AND member_id = :member_id",
                            array( ':group_id' => $id, ':member_id' => intval($_GET['delete_member']) ));
      $qry->Exec('principal-edit');
      $c->messages[] = translate('Member deleted from this Group Principal');
    }
  }

  function edit_group_row( $row_data ) {
    global $grouprow, $id;

    $form_url = preg_replace( '#&(edit|delete)_group=\d+#', '', $_SERVER['REQUEST_URI'] );

    $template = <<<EOTEMPLATE
<form method="POST" enctype="multipart/form-data" id="add_group" action="$form_url">
  <td class="left"><input type="hidden" name="id" value="$id"></td>
  <td class="left" colspan="3">##member_id.select## &nbsp; ##Add.submit##</td>
  <td class="center"></td>
</form>

EOTEMPLATE;

    $grouprow->SetTemplate( $template );
    $grouprow->Title("");
    if ( $row_data->group_id > -1 ) $grouprow->SetRecord( $row_data );

    return $grouprow->Render();
  }

  $browser = new Browser(translate('Group Members'));

  $browser->AddColumn( 'group_id', translate('ID'), 'right', '##principal_link##' );
  $rowurl = $c->base_url . '/admin.php?action=edit&t=principal&id=';
  $browser->AddHidden( 'principal_id' );
  $browser->AddHidden( 'principal_link', "'<a href=\"$rowurl' || principal_id || '\">' || principal_id || '</a>'" );
  $browser->AddColumn( 'displayname', translate('Display Name') );
  $browser->AddColumn( 'member_of', translate('Is Member of'), '', '', 'is_member_of_list(principal_id)' );
  $browser->AddColumn( 'members', translate('Has Members'), '', '', 'has_members_list(principal_id)' );

  if ( $can_write_principal ) {
    $del_link  = "<a href=\"/admin.php?action=edit&t=principal&id=$id&delete_member=##principal_id##\" class=\"submit\">Delete</a>";
    $browser->AddColumn( 'action', 'Action', 'center', '', "'$edit_link&nbsp;$del_link'" );
  }

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

  if ( $can_write_principal ) {
    $browser->RowFormat( '<tr class="r%d">', '</tr>', '#even' );
    $extra_row = array( 'group_id' => -1 );
    $browser->MatchedRow('group_id', -1, 'edit_group_row');
    $extra_row = (object) $extra_row;
    $browser->AddRow($extra_row);
  }
}


  $grantrow = new Editor("Grants", "grants");
  $grantrow->SetSubmitName( 'savegrantrow' );
  $grantrow->SetLookup( 'to_principal', 'SELECT principal_id, displayname FROM dav_principal WHERE principal_id NOT IN (SELECT member_id FROM group_member WHERE group_id = '.$id.')' );
  if ( $can_write_principal ) {
    if ( $grantrow->IsSubmit() ) {
      if ( $grouprow->IsUpdate() ) {
        $c->messages[] = translate('Updating grants by this Principal');
      else
        $c->messages[] = translate('Granting new privileges from this Principal');
      $_POST['by_principal'] = $id;
      $to_principal = intval($_POST['to_principal']);
      $orig_to_id =  intval($_POST['orig_to_id']);
      $grantrow->SetWhere( "by_principal=".qpg($id)." AND to_principal=$orig_to_id");
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
      $qry = new AwlQuery("DELETE FROM grants WHERE by_principal=:grantor_id AND to_principal = :to_principal",
                            array( ':grantor_id' => $id, ':to_principal' => intval($_GET['delete_grant']) ));
      $qry->Exec('principal-edit');
      $c->messages[] = translate('Deleted a grant from this Principal');
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

$browser = new Browser(translate('Principal Grants'));

$browser->AddColumn( 'to_principal', translate('To ID'), 'right', '##principal_link##' );
$rowurl = $c->base_url . '/admin.php?action=edit&t=principal&id=';
$browser->AddHidden( 'principal_link', "'<a href=\"$rowurl' || to_principal || '\">' || to_principal || '</a>'" );
$browser->AddHidden( 'grant_privileges', 'privileges' );
$browser->AddColumn( 'displayname', translate('Display Name') );
$browser->AddColumn( 'privs', translate('Privileges'), '', '', 'privileges_list(privileges)' );
$browser->AddColumn( 'members', translate('Has Members'), '', '', 'has_members_list(principal_id)' );

if ( $can_write_principal ) {
  $del_link  = "<a href=\"/admin.php?action=edit&t=principal&id=$id&delete_grant=##to_principal##\" class=\"submit\">Delete</a>";
  $edit_link  = "<a href=\"/admin.php?action=edit&t=principal&id=$id&edit_grant=##to_principal##\" class=\"submit\">Edit</a>";
  $browser->AddColumn( 'action', 'Action', 'center', '', "'$edit_link&nbsp;$del_link'" );
}

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

if ( $can_write_principal ) {
  if ( isset($_GET['edit_grant']) ) {
    $browser->MatchedRow('to_principal', $_GET['edit_grant'], 'edit_grant_row');
  }
  else {
    $browser->RowFormat( '<tr class="r%d">', '</tr>', '#even' );
    $extra_row = array( 'to_principal' => -1 );
    $browser->MatchedRow('to_principal', -1, 'edit_grant_row');
    $extra_row = (object) $extra_row;
    $browser->AddRow($extra_row);
  }
}


$browser = new Browser(translate('Principal Collections'));

$browser->AddColumn( 'collection_id', translate('ID'), 'right', '##collection_link##' );
$rowurl = $c->base_url . '/admin.php?action=edit&t=collection&id=';
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


