#!/bin/sh
#
# Build the RSCDS database
#

DBNAME="${1:-rscds}"

DBADIR="`dirname \"$0\"`"

createdb -E UTF8 "${DBNAME}"

psql -f "${DBADIR}/rscds.sql" "${DBNAME}"

psql -f "${DBADIR}/caldav_functions.sql" "${DBNAME}"

psql -f "${DBADIR}/sample-data.sql" "${DBNAME}"
