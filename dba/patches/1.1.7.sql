
-- Adding lock support

BEGIN;
SELECT check_db_revision(1,1,6);

ALTER TABLE relationship_type DROP COLUMN rt_inverse;
ALTER TABLE relationship_type DROP COLUMN prefix_match;
ALTER TABLE relationship_type ALTER COLUMN rt_isgroup RENAME TO rt_togroup;
ALTER TABLE relationship_type ADD COLUMN rt_fromgroup BOOLEAN;

SELECT new_db_revision(1,1,7, 'July' );
COMMIT;
ROLLBACK;

