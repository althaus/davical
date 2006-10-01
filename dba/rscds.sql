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

  PRIMARY KEY ( user_no, dav_name )
);

GRANT SELECT,INSERT,UPDATE,DELETE ON caldav_data TO general;

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
  dav_name TEXT,
  dav_etag TEXT,

  -- Extracted vEvent event data
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

  -- Cascade updates / deletes from the caldav_data table
  CONSTRAINT caldav_exists FOREIGN KEY ( user_no, dav_name )
                REFERENCES caldav_data ( user_no, dav_name )
                MATCH FULL ON DELETE CASCADE ON UPDATE CASCADE DEFERRABLE
);

GRANT SELECT,INSERT,UPDATE,DELETE ON event TO general;

-- BEGIN:VTODO
-- CREATED:20060921T035148Z
-- LAST-MODIFIED:20060921T035301Z
-- DTSTAMP:20060921T035301Z
-- UID:9a495928-276c-406b-8acd-e0883dfe68e3
-- SUMMARY:Something to do
-- PRIORITY:0
-- CLASS:PUBLIC
-- DUE;TZID=/mozilla.org/20050126_1/Antarctica/McMurdo:20060922T155149
-- X-MOZ-LOCATIONPATH:9a495928-276c-406b-8acd-e0883dfe68e3.ics
-- LOCATION:At work...
-- DESCRIPTION:This needs to be done.
-- URL:http://mcmillan.net.nz/
-- END:VTODO

-- The parsed todo.  Here we have pulled those todos apart somewhat.
CREATE TABLE todo (
  user_no INT references usr(user_no),
  dav_name TEXT,
  dav_etag TEXT,

  -- Extracted VTODO data
  uid TEXT,
  created TIMESTAMP,
  last_modified TIMESTAMP,
  dtstamp TIMESTAMP,
  dtstart TIMESTAMP WITH TIME ZONE,
  dtend TIMESTAMP WITH TIME ZONE,
  due TIMESTAMP WITH TIME ZONE,
  priority INT,
  summary TEXT,
  location TEXT,
  description TEXT,
  class TEXT,
  transp TEXT,
  rrule TEXT,
  url TEXT,
  percent_complete NUMERIC(7,2),
  tz_id TEXT REFERENCES time_zone( tz_id ),

  -- Cascade updates / deletes from the caldav_data table
  CONSTRAINT caldav_exists FOREIGN KEY ( user_no, dav_name )
                REFERENCES caldav_data ( user_no, dav_name )
                MATCH FULL ON DELETE CASCADE ON UPDATE CASCADE DEFERRABLE
);

GRANT SELECT,INSERT,UPDATE,DELETE ON todo TO general;

-- Something that can look like a filesystem hierarchy where we store stuff
CREATE TABLE calendar (
  user_no INT references usr(user_no),
  dav_name TEXT,
  dav_etag TEXT,
  created TIMESTAMP WITH TIME ZONE,

  PRIMARY KEY ( user_no, dav_name )
);

GRANT SELECT,INSERT,UPDATE,DELETE ON calendar TO general;

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
