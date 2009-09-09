
BEGIN;
SELECT check_db_revision(1,1,0);

GRANT SELECT,UPDATE ON relationship_type_rt_id_seq TO general;
SELECT new_db_revision(1,1,1, 'January' );

COMMIT;
ROLLBACK;
