
-- Minor enhancement:	Add columns to time_zone table to support timezone protocol changes.

BEGIN;
SELECT check_db_revision(1,2,10);

CREATE TABLE timezones (
  our_tzno SERIAL PRIMARY KEY,
  tzid TEXT UNIQUE NOT NULL,
  olson_name TEXT,
  active BOOLEAN,
  last_modified TIMESTAMP DEFAULT current_timestamp,
  etag TEXT,
  vtimezone TEXT
);

CREATE TABLE tz_aliases (
  our_tzno INT8 REFERENCES timezones(our_tzno),
  tzalias TEXT NOT NULL
);

CREATE TABLE tz_localnames (
  our_tzno INT8 REFERENCES timezones(our_tzno),
  locale TEXT NOT NULL,
  localised_name TEXT NOT NULL,
  preferred BOOLEAN DEFAULT TRUE
);


-- Let's assume that all timezone definitions currently present are old, and
-- we can find newer ones.  We don't really want the service feeding them out
-- so we'll mark them inactive as well.
INSERT INTO timezones (tzid, olson_name, active, last_modified, vtimezone, etag )
	SELECT tz_id, tz_locn, false, '1970-01-01T00:00:00Z', tz_spec, 'import' FROM time_zone;
INSERT INTO tz_aliases (our_tzno, tzalias)
    SELECT timezones.our_tzno, tz_locn FROM time_zone LEFT JOIN timezones ON (tz_id = tzid)
    									 WHERE tz_locn IS NOT NULL AND tz_locn != '';

DROP TABLE time_zone CASCADE;
ALTER TABLE calendar_item ADD CONSTRAINT "calendar_item_tz_id_fkey" FOREIGN KEY (tz_id) REFERENCES timezones(tzid)
    ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE;
ALTER TABLE collection ADD CONSTRAINT "collection_timezone_fkey" FOREIGN KEY (timezone) REFERENCES timezones(tzid)
    ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE;

SELECT new_db_revision(1,2,11, 'Novembre' );

COMMIT;
ROLLBACK;

