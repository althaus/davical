#!/bin/sh

sudo tcpdump -i lo -s0 -t -n -q -A 'tcp port 80 and (((ip[2:2] - ((ip[0]&0xf)<<2)) - ((tcp[12]&0xf0)>>2)) != 0)'
