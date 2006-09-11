#!/bin/sh
#
# Build the RSCDS database
#

DBNAME="${1:-rscds}"

createdb -E UTF8 "${DBNAME}"

psql -f rscds.sql "${DBNAME}"

psql -f sample-data.sql "${DBNAME}"
