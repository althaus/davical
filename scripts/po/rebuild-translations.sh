#!/bin/sh
#
# Rebuild all of our strings to be translated.  Written for
# the Really Simple CalDAV Store by Andrew McMillan vaguely
# based on something that originally came from Horde.
#

[ -n "${DEBUG}" ] && set -o xtrace

POTOOLS="scripts/po"
PODIR="po"
LOCALEDIR="locale"
APPLICATION="davical"

${POTOOLS}/extract.pl htdocs inc ../awl/inc > ${PODIR}/strings.raw
xgettext --keyword=_ -C --no-location --output=${PODIR}/messages.tmp ${PODIR}/strings.raw
sed -e 's/CHARSET/UTF-8/' <${PODIR}/messages.tmp >${PODIR}/messages.pot
rm ${PODIR}/messages.tmp


for LOCALE in `grep VALUES dba/supported_locales.sql | cut -f2 -d"'" | cut -f1 -d'_'` ; do
  [ "${LOCALE}" = "en" ] && continue
  if [ ! -f ${PODIR}/${LOCALE}.po ] ; then
    cp ${PODIR}/messages.pot ${PODIR}/${LOCALE}.po
  fi
  msgmerge --quiet --width 105 --update ${PODIR}/${LOCALE}.po ${PODIR}/messages.pot
done

for LOCALE in `grep VALUES dba/supported_locales.sql | cut -f2 -d"'" | cut -f1 -d'_'` ; do
  [ "${LOCALE}" = "en" ] && continue
  mkdir -p ${LOCALEDIR}/${LOCALE}/LC_MESSAGES
  msgfmt ${PODIR}/${LOCALE}.po -o ${LOCALEDIR}/${LOCALE}/LC_MESSAGES/${APPLICATION}.mo
done

