#!/bin/sh
#
# Build the CalDAV database
#

DBNAME="${1:-caldav}"

createdb -E UTF8 "${DBNAME}"

psql -f caldav.sql "${DBNAME}"

psql -f sample-data.sql "${DBNAME}"
