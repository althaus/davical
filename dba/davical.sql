-- Really Simple CalDAV Store - Database Schema
--

CREATE SEQUENCE dav_id_seq;

-- Something that can look like a filesystem hierarchy where we store stuff
CREATE TABLE collection (
  user_no INT references usr(user_no) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE,
  parent_container TEXT,
  dav_name TEXT,
  dav_etag TEXT,
  dav_displayname TEXT,
  is_calendar BOOLEAN,
  created TIMESTAMP WITH TIME ZONE,
  modified TIMESTAMP WITH TIME ZONE,
  public_events_only BOOLEAN NOT NULL DEFAULT FALSE,
  publicly_readable BOOLEAN NOT NULL DEFAULT FALSE,
  collection_id INT8 PRIMARY KEY DEFAULT nextval('dav_id_seq'),
  UNIQUE(user_no,dav_name)
);


-- The main event.  Where we store the things the calendar throws at us.
CREATE TABLE caldav_data (
  user_no INT references usr(user_no) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE,
  dav_name TEXT,
  dav_etag TEXT,
  created TIMESTAMP WITH TIME ZONE,
  modified TIMESTAMP WITH TIME ZONE,
  caldav_data TEXT,
  caldav_type TEXT,
  logged_user INT references usr(user_no),
  dav_id INT8 UNIQUE DEFAULT nextval('dav_id_seq'),
  collection_id INT8 REFERENCES collection(collection_id) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE,

  PRIMARY KEY ( user_no, dav_name )
);
CREATE INDEX caldav_data_collection_id_fkey ON caldav_data(collection_id);

-- Not particularly needed, perhaps, except as a way to collect
-- a bunch of valid iCalendar time zone specifications... :-)
CREATE TABLE time_zone (
  tz_id TEXT PRIMARY KEY,
  tz_locn TEXT,
  tz_spec TEXT
);


-- The parsed calendar item.  Here we have pulled those events/todos/journals apart somewhat.
CREATE TABLE calendar_item (
  user_no INT references usr(user_no),
  dav_name TEXT,
  dav_etag TEXT,

  -- Extracted vEvent/vTodo data
  uid TEXT,
  created TIMESTAMP,
  last_modified TIMESTAMP,
  dtstamp TIMESTAMP,
  dtstart TIMESTAMP WITH TIME ZONE,
  dtend TIMESTAMP WITH TIME ZONE,
  due TIMESTAMP WITH TIME ZONE,
  summary TEXT,
  location TEXT,
  description TEXT,
  priority INT,
  class TEXT,
  transp TEXT,
  rrule TEXT,
  url TEXT,
  percent_complete NUMERIC(7,2),
  tz_id TEXT REFERENCES time_zone( tz_id ),
  status TEXT,
  completed TIMESTAMP WITH TIME ZONE,
  dav_id INT8 UNIQUE,
  collection_id INT8 REFERENCES collection(collection_id) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE,

  -- Cascade updates / deletes from the caldav_data table
  CONSTRAINT caldav_exists FOREIGN KEY ( user_no, dav_name )
                REFERENCES caldav_data ( user_no, dav_name )
                MATCH FULL ON DELETE CASCADE ON UPDATE CASCADE DEFERRABLE,

  PRIMARY KEY ( user_no, dav_name )
);
CREATE INDEX calendar_item_collection_id_fkey ON calendar_item(collection_id);


-- Each user can be related to each other user.  This mechanism can also
-- be used to define groups of users, since some relationships are transitive.
CREATE TABLE relationship_type (
  rt_id SERIAL PRIMARY KEY,
  rt_name TEXT,
  rt_togroup BOOLEAN,
  confers TEXT DEFAULT 'RW',
  rt_fromgroup BOOLEAN
);


CREATE TABLE relationship (
  from_user INT REFERENCES usr (user_no) ON UPDATE CASCADE,
  to_user INT REFERENCES usr (user_no) ON UPDATE CASCADE,
  rt_id INT REFERENCES relationship_type (rt_id) ON UPDATE CASCADE,

  PRIMARY KEY ( from_user, to_user, rt_id )
);


CREATE TABLE locks (
  dav_name TEXT,
  opaquelocktoken TEXT UNIQUE NOT NULL,
  type TEXT,
  scope TEXT,
  depth INT,
  owner TEXT,
  timeout INTERVAL,
  start TIMESTAMP DEFAULT current_timestamp
);
CREATE INDEX locks_dav_name_idx ON locks(dav_name);


CREATE TABLE property (
  dav_name TEXT,
  property_name TEXT,
  property_value TEXT,
  changed_on TIMESTAMP DEFAULT current_timestamp,
  changed_by INT REFERENCES usr ( user_no ),
  PRIMARY KEY ( dav_name, property_name )
);
CREATE INDEX properties_dav_name_idx ON property(dav_name);


CREATE TABLE freebusy_ticket (
  ticket_id TEXT NOT NULL PRIMARY KEY,
  user_no integer NOT NULL REFERENCES usr(user_no) ON UPDATE CASCADE ON DELETE CASCADE,
  created timestamp with time zone DEFAULT current_timestamp NOT NULL
);


CREATE or REPLACE FUNCTION sync_dav_id ( ) RETURNS TRIGGER AS '
  DECLARE
  BEGIN

    IF TG_OP = ''DELETE'' THEN
      -- Just let the ON DELETE CASCADE handle this case
      RETURN OLD;
    END IF;

    IF NEW.dav_id IS NULL THEN
      NEW.dav_id = nextval(''dav_id_seq'');
    END IF;

    IF TG_OP = ''UPDATE'' THEN
      IF OLD.dav_id = NEW.dav_id THEN
        -- Nothing to do
        RETURN NEW;
      END IF;
    END IF;

    IF TG_RELNAME = ''caldav_data'' THEN
      UPDATE calendar_item SET dav_id = NEW.dav_id WHERE user_no = NEW.user_no AND dav_name = NEW.dav_name;
    ELSE
      UPDATE caldav_data SET dav_id = NEW.dav_id WHERE user_no = NEW.user_no AND dav_name = NEW.dav_name;
    END IF;

    RETURN NEW;

  END
' LANGUAGE 'plpgsql';

CREATE TRIGGER caldav_data_sync_dav_id AFTER INSERT OR UPDATE ON caldav_data
    FOR EACH ROW EXECUTE PROCEDURE sync_dav_id();

CREATE TRIGGER calendar_item_sync_dav_id AFTER INSERT OR UPDATE ON calendar_item
    FOR EACH ROW EXECUTE PROCEDURE sync_dav_id();


-- Only needs SELECT access by website.
CREATE TABLE principal_type (
  principal_type_id SERIAL PRIMARY KEY,
  principal_type_desc TEXT
);


-- web needs SELECT,INSERT,UPDATE,DELETE
CREATE TABLE principal (
  principal_id SERIAL PRIMARY KEY,
  type_id INT8 NOT NULL REFERENCES principal_type(principal_type_id) ON UPDATE CASCADE ON DELETE RESTRICT DEFERRABLE,
  user_no INT8 NULL REFERENCES usr(user_no) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE,
  displayname TEXT,
  active BOOLEAN
);


-- Allowing identification of group members.
CREATE TABLE group_member (
  group_id INT8 REFERENCES principal(principal_id) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE,
  member_id INT8 REFERENCES principal(principal_id) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE
);
CREATE UNIQUE INDEX group_member_pk ON group_member(group_id,member_id);
CREATE INDEX group_member_sk ON group_member(member_id);


-- Only needs SELECT access by website. dav_resource_type will be 'principal', 'collection', 'CalDAV:calendar' and so forth.
CREATE TABLE dav_resource_type (
  resource_type_id SERIAL PRIMARY KEY,
  dav_resource_type TEXT,
  resource_type_desc TEXT
);


CREATE TABLE dav_resource (
  dav_id INT8 PRIMARY KEY DEFAULT nextval('dav_id_seq'),
  dav_name TEXT,
  resource_type_id INT8 REFERENCES dav_resource_type(resource_type_id) ON UPDATE CASCADE ON DELETE RESTRICT DEFERRABLE,
  owner_id INT8 REFERENCES principal(principal_id) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE
);


CREATE TABLE privilege (
  granted_to_id INT8 REFERENCES principal(principal_id) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE,
  resource_id INT8 REFERENCES dav_resource(dav_id) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE,
  granted_by_id INT8 REFERENCES principal(principal_id) ON UPDATE CASCADE ON DELETE RESTRICT DEFERRABLE,
  can_read BOOLEAN,
  can_write BOOLEAN,
  can_write_properties BOOLEAN,
  can_write_content BOOLEAN,
  can_unlock BOOLEAN,
  can_read_acl BOOLEAN,
  can_read_current_user_privilege_set BOOLEAN,
  can_write_acl BOOLEAN,
  can_bind BOOLEAN,
  can_unbind BOOLEAN,
  can_read_free_busy BOOLEAN,
  PRIMARY KEY (granted_to_id, resource_id)
);


SELECT new_db_revision(1,2,2, 'Fevrier' );
