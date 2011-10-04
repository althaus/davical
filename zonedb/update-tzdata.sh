#!/bin/sh

wget --continue 'ftp://elsie.nci.nih.gov/pub/tz*.tar.gz'

TZCODEFILE="`ls -t tzcode*.tar.gz|tail -n 1`"
TZDATAFILE="`ls -t tzdata*.tar.gz|tail -n 1`"

(
  mkdir tzcode && cd tzcode && tar -xfz ../$TZCODEFILE
)

(
  mkdir tzdata && cd tzdata && tar -xfz ../$TZDATAFILE
)

vzic --olson-dir tzdata --output-dir vtimezones
echo "Olson `echo $TZDATAFILE | cut -f1 -d.`" >vtimezones/primary-source
