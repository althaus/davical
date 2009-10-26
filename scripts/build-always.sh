#!/bin/sh
#
# Apply the current version numbers into always.php from always.php.in
#

DAVICAL_VERSION="`head -n1 VERSION`"
DB_VERSION="`grep 'SELECT new_db_revision' dba/davical.sql | cut -f2 -d'(' | cut -f1-3 -d,`"

sed -e "/^ *.c->version_string *= *'[^']*' *;/ s/^ *.c->version_string *= *'[^']*' *;/\$c->version_string = '${DAVICAL_VERSION}';/" \
    -e "/^ *.c->want_dbversion *=.*$/ s/^ *.c->want_dbversion *=.*$/\$c->want_dbversion = array(${DB_VERSION});/"
