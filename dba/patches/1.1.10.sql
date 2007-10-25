
-- Make sure that class is set to something, by default PUBLIC.
-- According to RFC2445, 4.8.1.3.

BEGIN;
SELECT check_db_revision(1,1,9);

UPDATE calendar_item SET class = 'PUBLIC' WHERE class IS NULL;

SELECT new_db_revision(1,1,10, 'October' );
COMMIT;
ROLLBACK;

