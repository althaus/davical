<?php

// Editor component for company records
$editor = new Editor(translate('Collection'), 'collection');
param_to_global('id', 'int', 'old_id', 'collection_id' );
$editor->SetLookup( 'timezone', 'SELECT \'\', \'*** Unknown ***\' UNION SELECT tz_id, tz_locn FROM time_zone WHERE tz_id = tz_locn AND length(tz_spec) > 100 ORDER BY 1' );
$editor->SetLookup( 'schedule_transp', sprintf('SELECT \'opaque\', \'%s\' UNION SELECT \'transp\', \'%s\'', translate('Opaque'), translate('Transparent') ) );


$editor->AddAttribute('timezone', 'id', 'fld_timezone' );
$editor->AddAttribute('schedule_transp', 'id', 'fld_schedule_transp' );
$editor->AddAttribute('is_calendar', 'onclick', 'toggle_enabled(self.checked,\'fld_timezone\',\'fld_schedule_transp\');');

$editor->SetWhere( 'collection_id='.$id );

$privilege_names = array( 'read', 'write-properties', 'write-content', 'unlock', 'read-acl', 'read-current-user-privilege-set',
                         'bind', 'unbind', 'write-acl', 'read-free-busy', 'schedule-deliver-invite', 'schedule-deliver-reply',
                         'schedule-query-freebusy', 'schedule-send-invite', 'schedule-send-reply', 'schedule-send-freebusy' );

$pwstars = '@@@@@@@@@@';
if ( $editor->IsSubmit() ) {
  $editor->WhereNewRecord( "collection_id=(SELECT CURRVAL('dav_id_seq'))" );
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
  $c->page_title = $editor->Title(translate('Collection').': '.$editor->Value('dav_displayname'));
}
else {
  $c->page_title = $editor->Title(translate('Create New Collection'));
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

$prompt_collection_id = translate('Collection ID');
$prompt_dav_name = translate('DAV Path');
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
 <tr> <th class="right">$prompt_collection_id:</th>    <td class="left">##collection_id.value##</td> </tr>
 <tr> <th class="right">$prompt_dav_name:</th>         <td class="left">/caldav.php##dav_name.value##</td> </tr>
 <tr> <th class="right">$prompt_displayname:</th>      <td class="left">##dav_displayname.input.50##</td> </tr>
 <tr> <th class="right">$prompt_public:</th>           <td class="left">##publicly_readable.checkbox##</td> </tr>
 <tr> <th class="right">$prompt_calendar:</th>         <td class="left">##is_calendar.checkbox##</td> </tr>
 <tr> <th class="right">$prompt_addressbook:</th>      <td class="left">##is_addressbook.checkbox##</td> </tr>
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
 <tr> <th class="right">$prompt_timezone:</th>         <td class="left">##timezone.select##</td> </tr>
 <tr> <th class="right">$prompt_schedule_transp:</th>  <td class="left">##schedule_transp.select##</td> </tr>
 <tr> <th class="right">$prompt_description:</th>      <td class="left">##description.textarea.78x6##</td> </tr>
 <tr> <th class="right"></th>                   <td class="left" colspan="2">##submit##</td> </tr>
</table>
</form>
EOTEMPLATE;

$editor->SetTemplate( $template );
$page_elements[] = $editor;


$c->stylesheets[] = 'css/browse.css';
$c->scripts[] = 'js/browse.js';

$browser = new Browser(translate('Collection Grants'));

$browser->AddColumn( 'to_principal', translate('To ID'), 'right', '##principal_link##' );
$rowurl = $c->base_url . '/davical.php?action=edit&t=principal&id=';
$browser->AddHidden( 'principal_link', "'<a href=\"$rowurl' || to_principal || '\">' || to_principal || '</a>'" );
$browser->AddColumn( 'displayname', translate('Display Name') );
$browser->AddColumn( 'privs', translate('Privileges'), '', '', 'privileges_list(privileges)' );
$browser->AddColumn( 'members', translate('Has Members'), '', '', 'has_members_list(principal_id)' );

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



