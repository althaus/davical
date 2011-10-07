<?php
param_to_global( 'external_active', '([tf])', 'active' );

$browser = new Browser(translate('External Calendars'));

$browser->AddColumn( 'collection_id', translate('ID'), 'right', '##collection_link##' );
$rowurl = $c->base_url . '/admin.php?action=edit&t=external&id=';
$browser->AddHidden( 'collection_link', "'<a href=\"$rowurl' || collection_id || '\">' || collection_id || '</a>'" );
$browser->AddColumn( 'dav_displayname', translate('Display Name') );
$browser->AddColumn( 'refs', translate('References'),'right','','(select count(*) from dav_binding where bound_source_id=collection_id )' );

$browser->SetOrdering( 'dav_displayname', 'A' );
$browser->SetJoins( "collection " );

$browser->SetWhere( "parent_container='/.external/'" );


$c->page_title = $browser->Title();

if ( $c->enable_row_linking ) {
  $browser->RowFormat( '<tr onMouseover="LinkHref(this,1);" title="'.htmlspecialchars(translate('Click to display user details')).'" class="r%d">', '</tr>', '#even' );
}
else {
  $browser->RowFormat( '<tr class="r%d">', '</tr>', '#even' );
}

$page_elements[] = $browser;

