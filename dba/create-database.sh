#!/bin/sh
#
# Build the RSCDS database
#

DBNAME="${1:-rscds}"

DBADIR="`dirname \"$0\"`"

# FIXME: Need to check that the database was actually created.
createdb -E UTF8 "${DBNAME}" -T template0

#
# This will fail if the language already exists, but it should not
# because we created from template0.
createlang plpgsql "${DBNAME}"

#
# FIXME: filter non-error output
psql -q -f "${DBADIR}/rscds.sql" "${DBNAME}" 2>&1 | egrep -v "(^CREATE |^GRANT|^BEGIN|^COMMIT| NOTICE: )"

psql -q -f "${DBADIR}/caldav_functions.sql" "${DBNAME}"

psql -q -f "${DBADIR}/base-data.sql" "${DBNAME}"
psql -q -f "${DBADIR}/sample-data.sql" "${DBNAME}"
