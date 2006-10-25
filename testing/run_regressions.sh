#!/bin/sh
#
# Run the regression tests and display differences
#

# Restart PGPool to ensure we can drop and recreate the database
# FIXME: We should really drop everything *from* the database and create it
# from that, so we don't need to do this.
sudo /etc/init.d/pgpool restart
dropdb caldav
../dba/create-database.sh caldav

for T in tests/regression-suite/*.test ; do
  TEST="`basename ${T} .test`"
  ./dav_test regression-suite "${TEST}" | ./normalise_result > "tests/regression-suite/results/${TEST}"
  if [ ! -f "tests/regression-suite/${TEST}.result" ] ; then
    touch "tests/regression-suite/${TEST}.result"
  fi
  diff -u "tests/regression-suite/${TEST}.result" "tests/regression-suite/results/${TEST}" >"tests/regression-suite/diffs/${TEST}"
done

for T in tests/regression-suite/*.test ; do
  TEST="`basename ${T} .test`"
  if [ -s "tests/regression-suite/diffs/${TEST}" -o ! -f "tests/regression-suite/${TEST}.result" ] ; then
    echo "======================================="
    echo "Displaying diff for test ${TEST}"
    echo "======================================="
    cat "tests/regression-suite/diffs/${TEST}"
    read -p "Accept this as new standard result [y/N]? " ACCEPT
    if [ "${ACCEPT}" = "y" ] ; then
      cp "tests/regression-suite/results/${TEST}" "tests/regression-suite/${TEST}.result"
    fi
  else
    echo "Test ${TEST} passed OK!"
  fi
done
