-- My CalDAV server - Database Schema
--

-- Use the usr, group and schema management stufffrom libawl-php
\i /usr/share/awl/dba/awl-tables.sql
\i /usr/share/awl/dba/schema-management.sql

CREATE TABLE ics_event_data (
  user_no INT references usr(user_no),
  ics_event_name TEXT,
  ics_event_etag TEXT,
  ics_raw_data TEXT
);

