
-- This database update adds support for tickets to be handed out to grant
-- specific access to a collection or individual resource, as read-only or
-- read-write.  A table is also added to manage WebDAV binding, in line
-- with http://tools.ietf.org/html/draft-ietf-webdav-bind.

BEGIN;
SELECT check_db_revision(1,2,8);

-- Kind of important to have these as first-class citizens
ALTER TABLE addressbook_resource ADD COLUMN fburl TEXT DEFAULT NULL;
ALTER TABLE addressbook_resource ADD COLUMN caluri TEXT DEFAULT NULL;

-- 'N' => 'New/Needs setting', 'A' = 'Active', 'O' = 'Old'
ALTER TABLE calendar_alarm ADD COLUMN trigger_state trigger_state CHAR DEFAULT 'N';

SELECT new_db_revision(1,2,9, 'Septembre' );

COMMIT;
ROLLBACK;

