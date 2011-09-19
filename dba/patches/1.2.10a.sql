
-- Minor fix:			Ensure the target of a binding is unique
-- Minor enhancement:	Add columns for earliest start / latest end for improved pre-selection
--                      Add columns to support remote binds
-- Fix for upgrading:   Provide version of check_db_revision() function

CREATE or REPLACE FUNCTION check_db_revision( INT, INT, INT ) RETURNS BOOLEAN AS '
   DECLARE
      major ALIAS FOR $1;
      minor ALIAS FOR $2;
      patch ALIAS FOR $3;
      matching INT;
   BEGIN
      SELECT COUNT(*) INTO matching FROM awl_db_revision
             WHERE (schema_major = major AND schema_minor = minor AND schema_patch > patch)
                OR (schema_major = major AND schema_minor > minor)
                OR (schema_major > major)
             ;
      IF matching >= 1 THEN
        RAISE EXCEPTION ''Database revisions after %.%.% have already been applied.'', major, minor, patch;
        RETURN FALSE;
      END IF;
      SELECT COUNT(*) INTO matching FROM awl_db_revision
                      WHERE schema_major = major AND schema_minor = minor AND schema_patch = patch;
      IF matching >= 1 THEN
        RETURN TRUE;
      END IF;
      RAISE EXCEPTION ''Database has not been upgraded to %.%.%'', major, minor, patch;
      RETURN FALSE;
   END;
' LANGUAGE 'plpgsql';


-- Just in case these constraints got added manually, so we won't fail
-- if there is an existing one.
ALTER TABLE principal DROP CONSTRAINT unique_user CASCADE;
ALTER TABLE collection DROP CONSTRAINT unique_path CASCADE;

CREATE or REPLACE FUNCTION real_path_exists( TEXT ) RETURNS BOOLEAN AS $$
DECLARE
  in_path ALIAS FOR $1;
  tmp BOOLEAN;
BEGIN
  IF in_path = '/' THEN
    RETURN TRUE;
  END IF;
  IF in_path ~ '^/[^/]+/$' THEN
    SELECT TRUE INTO tmp FROM usr WHERE username = substring( in_path from 2 for length(in_path) - 2);
    IF FOUND THEN
      RETURN TRUE;
    END IF;
  ELSE
    IF in_path ~ '^/.*/$' THEN
      SELECT TRUE INTO tmp FROM collection WHERE dav_name = in_path;
      IF FOUND THEN
        RETURN TRUE;
      END IF;
    END IF;
  END IF;
  RETURN FALSE;
END;
$$ LANGUAGE plpgsql ;
        
BEGIN;
SELECT check_db_revision(1,2,9);

ALTER TABLE dav_binding ADD UNIQUE( dav_name );

-- New fields for Rob Ostensen's remote binding setup
ALTER TABLE dav_binding ADD COLUMN external_url TEXT;
ALTER TABLE dav_binding ADD COLUMN type TEXT;

ALTER TABLE principal ADD CONSTRAINT unique_user UNIQUE (user_no);

-- Ensure we don't refer to any newer, duplicated collections
UPDATE caldav_data SET collection_id = (SELECT min(c2.collection_id) FROM collection c1, collection c2
                                         WHERE c1.dav_name = c2.dav_name AND c1.collection_id = caldav_data.collection_id)
        WHERE collection_id > (SELECT min(c2.collection_id) FROM collection c1, collection c2
                                WHERE c1.dav_name = c2.dav_name AND c1.collection_id = caldav_data.collection_id);
-- Ensure the newer duplicated collections don't exist any longer
DELETE FROM collection WHERE collection_id > (SELECT min(collection_id) FROM collection c2 WHERE c2.dav_name = collection.dav_name);
-- Ensure we can't add more duplicates in the future
ALTER TABLE collection ADD CONSTRAINT unique_path UNIQUE (dav_name);

ALTER TABLE dav_binding ADD CONSTRAINT "dav_name_does_not_exist"
		CHECK (NOT real_path_exists(dav_name));

-- We will use these to improve our selection criteria in future, but for now we will leave them null
ALTER TABLE calendar_item ADD COLUMN first_instance_start TIMESTAMP WITHOUT TIME ZONE DEFAULT NULL;
ALTER TABLE calendar_item ADD COLUMN last_instance_end    TIMESTAMP WITHOUT TIME ZONE DEFAULT NULL;

SELECT new_db_revision(1,2,10, 'Octobre' );

COMMIT;
ROLLBACK;

