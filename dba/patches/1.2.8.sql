
-- This database update adds support for the draft webdav-sync specification
-- as well as some initial support for addressbook collections which will
-- be needed to support carddav.

BEGIN;
SELECT check_db_revision(1,2,7);

CREATE TABLE access_ticket (
  ticket_id TEXT PRIMARY KEY,
  is_collection BOOLEAN,
  is_public BOOLEAN,
  privileges BIT(24),
  target_id INT8,
  displayname TEXT,
  expires TIMESTAMP
);

SELECT new_db_revision(1,2,8, 'Août' );

COMMIT;
ROLLBACK;

