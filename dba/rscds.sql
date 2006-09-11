-- Really Simple CalDAV Store - Database Schema
--

-- Use the usr, group and schema management stufffrom libawl-php
\i /usr/share/awl/dba/awl-tables.sql
\i /usr/share/awl/dba/schema-management.sql

CREATE TABLE ics_event_data (
  user_no INT references usr(user_no),
  ics_event_name TEXT,
  ics_event_etag TEXT,
  ics_raw_data TEXT,

  PRIMARY KEY ( user_no, ics_event_name, ics_event_etag )
);

GRANT SELECT,INSERT,UPDATE,DELETE ON ics_event_data TO general;


CREATE TABLE time_zones (
  tzid TEXT PRIMARY KEY,
  location TEXT,
  tz_spec TEXT,
  pgtz TEXT
);
GRANT SELECT,INSERT ON time_zones TO general;


CREATE TABLE ical_events (
  user_no INT references usr(user_no),
  ics_event_name TEXT,
  ics_event_etag TEXT,

  -- Extracted vEvent event data
  uid TEXT,
  dtstamp TEXT,
  dtstart TIMESTAMP,
  dtend TIMESTAMP,
  summary TEXT,
  location TEXT,
  class TEXT,
  transp TEXT,
  description TEXT,
  rrule TEXT,
  tzid TEXT REFERENCES time_zones( tzid ),

  -- Cascade updates / deletes from the ics_event_data table
  CONSTRAINT ics_event_exists FOREIGN KEY ( user_no, ics_event_name, ics_event_etag )
                REFERENCES ics_event_data ( user_no, ics_event_name, ics_event_etag )
                MATCH FULL ON DELETE CASCADE ON UPDATE CASCADE DEFERRABLE
);

GRANT SELECT,INSERT,UPDATE,DELETE ON ical_events TO general;
