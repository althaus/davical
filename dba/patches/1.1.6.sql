
-- Adding lock support

BEGIN;
SELECT check_db_revision(1,1,5);

CREATE TABLE locks (
  dav_name TEXT,
  opaquelocktoken TEXT UNIQUE NOT NULL,
  type TEXT,
  scope TEXT,
  depth INT,
  owner INT REFERENCES usr(user_no),
  timeout INTERVAL,
  start TIMESTAMP DEFAULT current_timestamp
);

CREATE INDEX locks_dav_name_idx ON locks(dav_name);
GRANT SELECT,INSERT,UPDATE,DELETE ON locks TO general;

SELECT new_db_revision(1,1,6, 'June' );
COMMIT;
ROLLBACK;

