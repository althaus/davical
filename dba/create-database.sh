#!/bin/sh
#
# Build the RSCDS database
#

DBNAME="${1:-rscds}"

DBADIR="`dirname \"$0\"`"

# FIXME: Need to check that the database was actually created.
createdb -E UTF8 "${DBNAME}"

#
# This will fail if the language already exists.
# FIXME: test for the language first, perhaps.
createlang plpgsql "${DBNAME}"

#
# FIXME: filter non-error output
psql -f "${DBADIR}/rscds.sql" "${DBNAME}"

psql -f "${DBADIR}/caldav_functions.sql" "${DBNAME}"

psql -f "${DBADIR}/sample-data.sql" "${DBNAME}"
