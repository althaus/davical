<?php
/**
* Extend the vComponent to specifically handle VCARD resources
*/

require_once('vComponent.php');

class VCard extends vComponent {

  /**
  * Into tables like this:
  *
CREATE TABLE addressbook_resource (
  dav_id INT8 NOT NULL REFERENCES caldav_data(dav_id) ON UPDATE CASCADE ON DELETE CASCADE PRIMARY KEY,
  version TEXT,
  uid TEXT,
  nickname TEXT,
  fn TEXT, -- fullname
  n TEXT, -- Name Surname;First names
  note TEXT,
  org TEXT,
  url TEXT
);

CREATE TABLE addressbook_address_adr (
  dav_id INT8 NOT NULL REFERENCES caldav_data(dav_id) ON UPDATE CASCADE ON DELETE CASCADE,
  type TEXT,
  adr TEXT,
  property TEXT -- The full text of the property
);

CREATE TABLE addressbook_address_tel (
  dav_id INT8 NOT NULL REFERENCES caldav_data(dav_id) ON UPDATE CASCADE ON DELETE CASCADE,
  type TEXT,
  tel TEXT,
  property TEXT -- The full text of the property
);

CREATE TABLE addressbook_address_email (
  dav_id INT8 NOT NULL REFERENCES caldav_data(dav_id) ON UPDATE CASCADE ON DELETE CASCADE,
  type TEXT,
  email TEXT,
  property TEXT -- The full text of the property
);
  *
  */
  function Write( $dav_name ) {
    $addresses = $this->GetProperties('ADR');
    $telephones = $this->GetProperties('TEL');
    $emails = $this->GetProperties('EMAIL');
  }

}