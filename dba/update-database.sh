#!/bin/sh
#
# Update the RSCDS database to the latest schema version and base data
#

DBNAME="${1:-rscds}"

DBADIR="`dirname \"$0\"`"

# Apply any patches.
# FIXME: We shouldn't really apply patches we already have installed, although
# it should be safe enough since any patch which is not idempotent should be
# denied multiple application through the AWL schema management functions.
for PATCHFILE in "${DBADIR}/patches/patch-*.sql"; do
  psql -q -f "${PATCHFILE}" "${DBNAME}"
done

# Update the base data
psql -q -f "${DBADIR}/base-data.sql" "${DBNAME}"
