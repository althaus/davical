#!/bin/sh

PORT=${1:-"80"}
IFACE=${2:-"any"}

# Only include packets that contain data
NOTSYNFIN=" and (((ip[2:2] - ((ip[0]&0xf)<<2)) - ((tcp[12]&0xf0)>>2)) != 0)"
DUMP="tcp port ${PORT}"

IPCLAUSE=""
if [ "${IFACE}" != "any" ]; then
  IP="`ip addr show dev ${IFACE} | grep ' inet ' | tr -s ' ' | cut -f3 -d' ' | cut -f1 -d'/'`"
  IPCLAUSE=" and ((src host ${IP} and src port ${PORT}) or (dst host ${IP} and dst port ${PORT}))"
fi

DUMPFILE="dumps/`date '+%FT%T'`.dump"

# touch "${DUMPFILE}"
sudo tcpdump -i $IFACE -s0 -l -n -q -A "${DUMP}${NOTSYNFIN}${IPCLAUSE}" >"${DUMPFILE}" 2>&1 &
DUMPPID="$!"

less "${DUMPFILE}"

sudo kill "${DUMPPID}"

if [ "`stat --format='%s' \"${DUMPFILE}\"`" -le 230 ] ; then
  rm "${DUMPFILE}"
fi
