
-- Adding lock support

BEGIN;
SELECT check_db_revision(1,1,6);

ALTER TABLE relationship_type DROP COLUMN rt_inverse;
ALTER TABLE relationship_type DROP COLUMN prefix_match;
ALTER TABLE relationship_type DROP COLUMN rt_isgroup;

UPDATE relationship_type SET rt_name ='Administers', confers = 'A' WHERE rt_id = 1;
UPDATE relationship_type SET rt_name ='is Assistant to', confers = 'RW' WHERE rt_id = 2;
UPDATE relationship_type SET rt_name ='Can read from', confers = 'R' WHERE rt_id = 3;
UPDATE relationship_type SET rt_name ='Can see free/busy time of', confers = 'F' WHERE rt_id = 4;

UPDATE relationship SET rt_id=1 WHERE rt_id=4;
UPDATE relationship SET rt_id=4 WHERE rt_id=5;

DELETE FROM relationship_type WHERE rt_id = 5;

SELECT new_db_revision(1,1,7, 'July' );
COMMIT;
ROLLBACK;

