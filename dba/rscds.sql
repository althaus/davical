-- Really Simple CalDAV Store - Database Schema
--

-- Use the usr, group and schema management stufffrom libawl-php
\i /usr/share/awl/dba/awl-tables.sql
\i /usr/share/awl/dba/schema-management.sql

-- The main event.  Where we store the things the calendar throws at us.
CREATE TABLE caldav_data (
  user_no INT references usr(user_no),
  dav_name TEXT,
  dav_etag TEXT,
  caldav_data TEXT,
  caldav_type TEXT,
  logged_user INT references usr(user_no),

  PRIMARY KEY ( user_no, vevent_name, vevent_etag )
);

GRANT SELECT,INSERT,UPDATE,DELETE ON vevent_data TO general;

-- Not particularly needed, perhaps, except as a way to collect
-- a bunch of valid iCalendar time zone specifications... :-)
CREATE TABLE time_zone (
  tz_id TEXT PRIMARY KEY,
  tz_locn TEXT,
  tz_spec TEXT
);
GRANT SELECT,INSERT ON time_zone TO general;

-- The parsed event.  Here we have pulled those events apart somewhat.
CREATE TABLE event (
  user_no INT references usr(user_no),
  vevent_name TEXT,
  vevent_etag TEXT,

  -- Extracted vEvent event data
  uid TEXT,
  dtstamp TEXT,
  dtstart TIMESTAMP WITH TIME ZONE,
  dtend TIMESTAMP WITH TIME ZONE,
  summary TEXT,
  location TEXT,
  class TEXT,
  transp TEXT,
  description TEXT,
  rrule TEXT,
  tz_id TEXT REFERENCES time_zone( tz_id ),

  -- Cascade updates / deletes from the vevent_data table
  CONSTRAINT vevent_exists FOREIGN KEY ( user_no, vevent_name, vevent_etag )
                REFERENCES vevent_data ( user_no, vevent_name, vevent_etag )
                MATCH FULL ON DELETE CASCADE ON UPDATE CASCADE DEFERRABLE
);

GRANT SELECT,INSERT,UPDATE,DELETE ON event TO general;

-- Each user can be related to each other user.  This mechanism can also
-- be used to define groups of users, since some relationships are transitive.
CREATE TABLE relationship_type (
  rt_id SERIAL PRIMARY KEY,
  rt_name TEXT,
  rt_isgroup BOOLEAN,
  rt_inverse INT,
  confers TEXT DEFAULT 'RW',
  prefix_match TEXT DEFAULT ''
);

GRANT SELECT,INSERT,UPDATE,DELETE ON relationship_type TO general;

CREATE TABLE relationship (
  from_user INT REFERENCES usr (user_no) ON UPDATE CASCADE,
  to_user INT REFERENCES usr (user_no) ON UPDATE CASCADE,
  rt_id INT REFERENCES relationship_type (rt_id) ON UPDATE CASCADE
);

GRANT SELECT,INSERT,UPDATE,DELETE ON relationship TO general;
