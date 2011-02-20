#!/usr/bin/make -f
#

package=davical
version=$(shell cat VERSION)
snapshot : version = $(shell sed -n 's:\([0-9\.]*\)[-a-f0-9-]*:\1:p' VERSION)-git$(shell git rev-parse --short HEAD)

all: htdocs/always.php built-docs built-po

built-docs: docs/api/phpdoc.ini htdocs/*.php inc/*.php docs/translation.rst
	phpdoc -c docs/api/phpdoc.ini || echo "NOTICE: Failed to build optional API docs"
	rst2pdf docs/translation.rst || echo "NOTICE: Failed to build ReST docs"
	touch built-docs

built-po: htdocs/always.php scripts/po/rebuild-translations.sh po/*.po
	scripts/po/rebuild-translations.sh
	touch built-po

#
# Insert the current version number into always.php
#
htdocs/always.php: scripts/build-always.sh VERSION dba/davical.sql inc/always.php.in
	scripts/build-always.sh <inc/always.php.in >htdocs/always.php

#
# Build a release .tar.gz file in the directory above us
#
release: built-docs VERSION
	-ln -s . $(package)-$(version)
	tar czf ../$(package)-$(version).tar.gz \
	    --no-recursion --dereference $(package)-$(version) \
	    $(shell git ls-files |grep -v '.git'|sed -e s:^:$(package)-$(version)/:) \
	    $(shell find $(package)-$(version)/docs/api/ ! -name "phpdoc.ini" )
	rm $(package)-$(version)

snapshot: release

clean:
	rm -f built-docs built-po
	-find . -name "*~" -delete
	rm docs/translation.pdf

clean-all: clean
	-find docs/api/* ! -name "phpdoc.ini" ! -name ".gitignore" -delete

.PHONY:  all clean release
