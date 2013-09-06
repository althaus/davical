
-- Minor enhancement:	Add columns to time_zone table to support timezone protocol changes.

BEGIN;
SELECT check_db_revision(1,2,11);

ALTER TABLE calendar_attendee
  ADD COLUMN is_remote BOOLEAN DEFAULT FALSE;




SELECT new_db_revision(1,2,12, 'Septembre' );

COMMIT;
ROLLBACK;

