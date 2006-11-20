#!/bin/sh
#
# Run the regression tests and display differences
#
DBNAME=caldav
UNTIL=${1:-"99999"}

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
    read -p "Accept this as new standard result [y/N]? " ACCEPT
    if [ "${ACCEPT}" = "y" ] ; then
      cp "${RESULTS}/${TEST}" "${REGRESSION}/${TEST}.result"
    fi
  else
    echo "Test ${TEST} passed OK!"
  fi
}

# Restart PGPool to ensure we can drop and recreate the database
# FIXME: We should really drop everything *from* the database and create it
# from that, so we don't need to do this.
sudo /etc/init.d/pgpool restart
if ! dropdb ${DBNAME}; then
  echo "Unable to drop existing database"
  exit
fi

TEST="Create-Database"
../dba/create-database.sh ${DBNAME} >"${RESULTS}/${TEST}" 2>&1
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
  ./dav_test regression-suite "${TEST}" | ./normalise_result > "${RESULTS}/${TEST}"

  check_result "${TEST}"

done
