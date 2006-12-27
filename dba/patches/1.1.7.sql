
-- Adding lock support

BEGIN;
SELECT check_db_revision(1,1,6);

ALTER TABLE relationship_type DROP COLUMN rt_inverse;
ALTER TABLE relationship_type DROP COLUMN prefix_match;
ALTER TABLE relationship_type DROP COLUMN rt_isgroup;

SELECT new_db_revision(1,1,7, 'July' );
COMMIT;
ROLLBACK;

