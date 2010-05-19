#!/bin/bash
#
# Run the regression tests and display differences
#
DBNAME=regression
PGPOOL=inactive
HOSTNAME=regression

# We need to run the regression tests in the timezone they were written for.
export PGTZ=Pacific/Auckland

. ./regression.conf

[ -z "${DSN}" ] && DSN="${DBNAME}"
[ -n "${HOSTNAME}" ] && WEBHOST="--webhost ${HOSTNAME}"
[ -n "${ALTHOST}"  ] && ALTHOST="--althost ${ALTHOST}"


SUITE=${1:-"regression-suite"}
ACCEPT_ALL=${2:-""}

[ -z "${UNTIL}" ] && UNTIL=99999
[ -z "${SUITE}" ] && SUITE="regression-suite"
REGRESSION="tests/${SUITE}"
RESULTS="${REGRESSION}/results"

check_result() {
  TEST="$1"
  if [ ! -f "${REGRESSION}/${TEST}.result" ] ; then
    touch "${REGRESSION}/${TEST}.result"
  fi
  diff --text -u "${REGRESSION}/${TEST}.result" "${RESULTS}/${TEST}" >"${REGRESSION}/diffs/${TEST}"

  if [ -s "${REGRESSION}/diffs/${TEST}" ] ; then
    echo "======================================="
    echo "Displaying diff for test ${TEST}"
    echo "======================================="
    cat "${REGRESSION}/diffs/${TEST}"
    echo "======================================="
    if [ "${ACCEPT_ALL}" = "" ] ; then
      read -p "[${TEST}] Accept new result [e/r/v/f/x/y/N]? " ACCEPT
    else
      ACCEPT=${ACCEPT_ALL}
    fi
    if [ "${ACCEPT}" = "y" ] ; then
      cp "${RESULTS}/${TEST}" "${REGRESSION}/${TEST}.result"
    elif [ "${ACCEPT}" = "x" ]; then
      echo "./dav_test --dsn '${DSN}' ${WEBHOST} ${ALTHOST} --suite '${SUITE}' --case '${TEST}' --debug"
      exit
    elif [ "${ACCEPT}" = "v" ]; then
      echo "Showing test $REGRESSION/${TEST}.test"
      cat "$REGRESSION/${TEST}.test"
      return 2
    elif [ "${ACCEPT}" = "f" ]; then
      echo "Showing full details of ${TEST}"
      cat "${REGRESSION}/${TEST}.test"
      echo "Showing full result of ${TEST}"
      cat "${RESULTS}/${TEST}"
      return 2
    elif [ "${ACCEPT}" = "e" ]; then
      echo "Editing test $REGRESSION/${TEST}.test"
      vi "$REGRESSION/${TEST}.test"
      return 2
    elif [ "${ACCEPT}" = "r" ]; then
      echo "Rerunning test ${TEST}"
      return 1
    fi
  else
    echo "Test ${TEST} passed OK!"
  fi
  return 0
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


restore_database() {
  drop_database ${DBNAME}

  TEST="Restore-Database"
  createdb --owner davical_dba --encoding UTF8 ${DBNAME} >"${RESULTS}/${TEST}" 2>&1
  pg_restore -Fc -d ${DBNAME} "${REGRESSION}/initial.pgdump" >>"${RESULTS}/${TEST}" 2>&1
  check_result "${TEST}"
}


initialise_regression() {
  drop_database ${DBNAME}

  TEST="Create-Database"
  ../dba/create-database.sh ${DBNAME} 'nimda' >"${RESULTS}/${TEST}" 2>&1
  check_result "${TEST}"

  TEST="Upgrade-Database"
  ../dba/update-davical-database --dbname=${DBNAME} --nopatch --appuser davical_app --owner davical_dba >"${RESULTS}/${TEST}" 2>&1
  check_result "${TEST}"

  TEST="Load-Sample-Data"
  psql -q -f "../dba/sample-data.sql" "${DBNAME}" >"${RESULTS}/${TEST}" 2>&1
  check_result "${TEST}"
}

mkdir -p "${RESULTS}"
mkdir -p "${REGRESSION}/diffs"

if [ -f "${REGRESSION}/initial.pgdump" ]; then
  restore_database
else
  initialise_regression
fi

TSTART="`date +%s`"
TCOUNT=0

for T in ${REGRESSION}/*.test ; do
  TEST="`basename ${T} .test`"
  TESTNUM="`echo ${TEST} | cut -f1 -d'-'`"
  TESTNUM="${TEST/-*}"
  if [ "${TESTNUM}" -gt "${UNTIL}" ] ; then
    break;
  fi

  RESULT=999
  while [ "${RESULT}" -gt 0 ]; do
    ./dav_test --dsn "${DSN}" ${WEBHOST} ${ALTHOST} --suite "${SUITE}" --case "${TEST}" | ./normalise_result > "${RESULTS}/${TEST}"

    RESULT=999
    while [ "${RESULT}" -gt 1 ]; do
      check_result "${TEST}"
      RESULT=$?
    done

  done

  TCOUNT="$(( ${TCOUNT} + 1 ))"
done
TFINISH="`date +%s`"

echo "Regression test run took $(( ${TFINISH} - ${TSTART} )) seconds for ${TCOUNT} tests."
