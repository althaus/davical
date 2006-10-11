#!/bin/sh

PORT=${1:-"80"}

sudo tcpdump -i lo -s0 -n -q -A "tcp port ${PORT} and (((ip[2:2] - ((ip[0]&0xf)<<2)) - ((tcp[12]&0xf0)>>2)) != 0)"
