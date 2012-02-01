#!/bin/sh

wget --continue 'ftp://ftp.iana.org/tz/tz*.tar.gz'

TZCODEFILE="`readlink tzcode-latest.tar.gz`"
TZDATAFILE="`readlink tzdata-latest.tar.gz`"

# if [ ! -f $TZCODEFILE ]; then
  (
    wget --continue -O $TZCODEFILE 'ftp://ftp.iana.org/tz/'$TZCODEFILE
    rm -rf tzcode
    mkdir -p tzcode && cd tzcode && tar -xzf ../$TZCODEFILE
  )
# fi

# if [ ! -f $TZDATAFILE ]; then
  (
    wget --continue -O $TZDATAFILE 'ftp://ftp.iana.org/tz/'$TZDATAFILE
    rm -rf tzdata
    mkdir -p tzdata && cd tzdata && tar -xzf ../$TZDATAFILE
  )
# fi

vzic --pure --olson-dir tzdata --output-dir vtimezones
echo "Olson `echo $TZDATAFILE | cut -f1 -d.`" >vtimezones/primary-source
