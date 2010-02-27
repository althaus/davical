
-- This database update adds support for tickets to be handed out to grant
-- specific access to a collection or individual resource, as read-only or
-- read-write.  A table is also added to manage WebDAV binding, in line
-- with http://tools.ietf.org/html/draft-ietf-webdav-bind.

BEGIN;
SELECT check_db_revision(1,2,7);

CREATE TABLE access_ticket (
  ticket_id TEXT PRIMARY KEY,
  is_public BOOLEAN,
  privileges BIT(24),
  target_collection_id INT8 NOT NULL REFERENCES collection(collection_id) ON UPDATE CASCADE ON DELETE CASCADE,
  target_resource_id INT8 REFERENCES caldav_data(dav_id) ON UPDATE CASCADE ON DELETE CASCADE,
  dav_displayname TEXT,
  expires TIMESTAMP
);


-- At this point we only support binding collections
CREATE TABLE dav_binding (
  bind_id INT8 DEFAULT nextval('dav_id_seq') PRIMARY KEY,
  target_ticket_id TEXT REFERENCES access_ticket(ticket_id) ON UPDATE CASCADE ON DELETE CASCADE,
  target_collection_id INT8 REFERENCES collection(collection_id) ON UPDATE CASCADE ON DELETE CASCADE,
  dav_owner_id INT8 NOT NULL REFERENCES principal(principal_id) ON UPDATE CASCADE ON DELETE CASCADE,
  parent_container TEXT,
  dav_name TEXT,
  dav_displayname TEXT
);


CREATE TABLE collection_mashup (
  mashup_id SERIAL PRIMARY KEY,
  dav_owner_id INT8 NOT NULL REFERENCES principal(principal_id) ON UPDATE CASCADE ON DELETE CASCADE,
  parent_container TEXT,
  dav_name TEXT,
  dav_displayname TEXT
);

CREATE TABLE mashup_member (
  mashup_id INT8 NOT NULL REFERENCES collection_mashup(mashup_id) ON UPDATE CASCADE ON DELETE CASCADE,
  target_ticket_id TEXT REFERENCES access_ticket(ticket_id) ON UPDATE CASCADE ON DELETE CASCADE,
  target_collection_id INT8 REFERENCES collection(collection_id) ON UPDATE CASCADE ON DELETE CASCADE,
  member_colour TEXT
);


SELECT new_db_revision(1,2,8, 'Août' );

COMMIT;
ROLLBACK;

