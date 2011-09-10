#!/bin/sh
#
# Rebuild all of our strings to be translated.  Written for
# the DAViCal CalDAV Server by Andrew McMillan vaguely
# based on something that originally came from Horde.
#

[ -n "${DEBUG}" ] && set -o xtrace

PODIR="po"
LOCALEDIR="locale"
APPLICATION="davical"
AWL_LOCATION="../awl"

if [ ! -d "${AWL_LOCATION}" ]; then
  AWL_LOCATION=/usr/share/awl
  if [ ! -d "${AWL_LOCATION}" ]; then
    echo "I can't find a location for the AWL libraries and I need those strings too"
    exit 1
  fi
fi

egrep -l '(i18n|translate)' htdocs/*.php inc/*.php inc/ui/*.php > ${PODIR}/pofilelist.tmp1
sed "s:../awl:${AWL_LOCATION}:" ${PODIR}/pofilelist.txt >> ${PODIR}/pofilelist.tmp1
sort ${PODIR}/pofilelist.tmp1 | uniq > ${PODIR}/pofilelist.tmp
xgettext --no-location --add-comments=Translators --keyword=translate --keyword=i18n --output=${PODIR}/messages.tmp -s -f ${PODIR}/pofilelist.tmp
sed 's.^"Content-Type: text/plain; charset=CHARSET\\n"."Content-Type: text/plain; charset=UTF-8\\n".' ${PODIR}/messages.tmp > ${PODIR}/messages.pot
rm ${PODIR}/messages.tmp ${PODIR}/pofilelist.tmp ${PODIR}/pofilelist.tmp1

locale_list() {
  ls ${PODIR}/*.po | cut -f2 -d/ | cut -f1 -d.
}

build_supported_locales() {
  echo "TRUNCATE supported_locales;"
  echo "INSERT INTO supported_locales ( locale, locale_name_en, locale_name_locale )"
  echo "    VALUES( 'en', 'English', 'English' );"
  for LOCALE in `locale_list`; do
    echo "INSERT INTO supported_locales ( locale, locale_name_en, locale_name_locale )"
    cat ${PODIR}/${LOCALE}.values
  done
}

build_supported_locales >dba/supported_locales.sql

for LOCALE in `locale_list` ; do
  [ "${LOCALE}" = "en" ] && continue
  if [ ! -f ${PODIR}/${LOCALE}.po ] ; then
    cp ${PODIR}/messages.pot ${PODIR}/${LOCALE}.po
  fi
  msgmerge --no-fuzzy-matching --quiet --width 105 --update ${PODIR}/${LOCALE}.po ${PODIR}/messages.pot
done

for LOCALE in `locale_list` ; do
  [ "${LOCALE}" = "en" ] && continue
  mkdir -p ${LOCALEDIR}/${LOCALE}/LC_MESSAGES
  msgfmt ${PODIR}/${LOCALE}.po -o ${LOCALEDIR}/${LOCALE}/LC_MESSAGES/${APPLICATION}.mo
done

