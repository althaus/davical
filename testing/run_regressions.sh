#!/bin/sh
#
# Run the regression tests and display differences
#
DBNAME=regression
PGPOOL=inactive
HOSTNAME=regression

. regression.conf

[ -z "${DSN}" ] && DSN="${DBNAME}"


UNTIL=${1:-"99999"}
ACCEPT_ALL=${2:-""}

REGRESSION="tests/regression-suite"
RESULTS="${REGRESSION}/results"

check_result() {
  TEST="$1"
  if [ ! -f "${REGRESSION}/${TEST}.result" ] ; then
    touch "${REGRESSION}/${TEST}.result"
  fi
  diff -u "${REGRESSION}/${TEST}.result" "${RESULTS}/${TEST}" >"${REGRESSION}/diffs/${TEST}"

  if [ -s "${REGRESSION}/diffs/${TEST}" -o ! -f "${REGRESSION}/${TEST}.result" ] ; then
    echo "======================================="
    echo "Displaying diff for test ${TEST}"
    echo "======================================="
    cat "${REGRESSION}/diffs/${TEST}"
    if [ "${ACCEPT_ALL}" = "" ] ; then
      read -p "Accept this as new standard result [y/N]? " ACCEPT
    else
      ACCEPT=${ACCEPT_ALL}
    fi
    if [ "${ACCEPT}" = "y" ] ; then
      cp "${RESULTS}/${TEST}" "${REGRESSION}/${TEST}.result"
    fi
  else
    echo "Test ${TEST} passed OK!"
  fi
}

drop_database() {
  dropdb $1
  if psql -ltA | cut -f1 -d'|' | grep "^$1$" >/dev/null ; then
    # Restart PGPool to ensure we can drop and recreate the database
    # FIXME: We should really drop everything *from* the database and create it
    # from that, so we don't need to do this.
    [ "${PGPOOL}" = "inactive" ] || sudo /etc/init.d/pgpool restart
    dropdb $1
    if psql -ltA | cut -f1 -d'|' | grep "^$1$" >/dev/null ; then
      echo "Failed to drop $1 database"
      exit 1
    fi
  fi
}

drop_database ${DBNAME}

mkdir -p "${RESULTS}"
mkdir -p "${REGRESSION}/diffs"

TEST="Create-Database"
../dba/create-database.sh ${DBNAME} 'nimda' >"${RESULTS}/${TEST}" 2>&1
check_result "${TEST}"

TEST="Load-Sample-Data"
psql -q -f "../dba/sample-data.sql" "${DBNAME}" >"${RESULTS}/${TEST}" 2>&1
check_result "${TEST}"

for T in ${REGRESSION}/*.test ; do
  TEST="`basename ${T} .test`"
  TESTNUM="`echo ${TEST} | cut -f1 -d'-'`"
  TESTNUM="${TEST/-*}"
  if [ "${TESTNUM}" -gt "${UNTIL}" ] ; then
    break;
  fi
  ./dav_test --dsn "${DSN}" --suite regression-suite --case "${TEST}" | ./normalise_result > "${RESULTS}/${TEST}"

  check_result "${TEST}"

done
