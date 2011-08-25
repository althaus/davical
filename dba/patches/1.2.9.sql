
-- This database update adds some extra columns / tables for tracking
-- alarms, attendee states and addressbook address field expansion.

BEGIN;
SELECT check_db_revision(1,2,8);

-- Kind of important to have these as first-class citizens
ALTER TABLE addressbook_resource ADD COLUMN fburl TEXT DEFAULT NULL;
ALTER TABLE addressbook_resource ADD COLUMN caluri TEXT DEFAULT NULL;
ALTER TABLE addressbook_resource ADD COLUMN caladruri TEXT DEFAULT NULL;

DROP TABLE addressbook_address_adr CASCADE;
CREATE TABLE addressbook_address_adr (
  dav_id INT8 NOT NULL REFERENCES caldav_data(dav_id) ON UPDATE CASCADE ON DELETE CASCADE,
  type TEXT,
  box_no TEXT,
  unit_no TEXT,
  street_address TEXT,
  locality TEXT,
  region TEXT,
  postcode TEXT,
  country TEXT,
  property TEXT -- The full text of the property
);

-- 'N' => 'New/Needs setting', 'A' = 'Active', 'O' = 'Old'
ALTER TABLE calendar_alarm ADD COLUMN trigger_state CHAR DEFAULT 'N';

-- Internal DAViCal calendar state
ALTER TABLE calendar_attendee ADD COLUMN attendee_state TEXT;
ALTER TABLE calendar_attendee ADD COLUMN weak_etag TEXT;

SELECT new_db_revision(1,2,9, 'Septembre' );

COMMIT;
ROLLBACK;

