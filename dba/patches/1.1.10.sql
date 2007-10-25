
-- Sort out accessing calendar entries.

BEGIN;
SELECT check_db_revision(1,1,9);

-- Make sure that class is set to something, by default PUBLIC.
-- According to RFC2445, 4.8.1.3.
UPDATE calendar_item SET class = 'PUBLIC' WHERE class IS NULL;

-- Allow forcing all events in a calendar to a specific class 
ALTER TABLE collection ADD COLUMN force_class TEXT;

SELECT new_db_revision(1,1,10, 'October' );
COMMIT;
ROLLBACK;

