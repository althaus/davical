#!/bin/sh

PORT=${1:-"80"}
IFACE=${2:-"any"}

sudo tcpdump -i $IFACE -s0 -l -n -q -A "tcp port ${PORT} and (((ip[2:2] - ((ip[0]&0xf)<<2)) - ((tcp[12]&0xf0)>>2)) != 0)"
