#!/usr/bin/make -f
# 

package=davical
version=$(shell cat VERSION)

all: inc/always.php built-docs built-po

built-docs: docs/api/phpdoc.ini htdocs/*.php inc/*.php
	phpdoc -c docs/api/phpdoc.ini || echo "NOTICE: Failed to build optional API docs"
	touch built-docs

built-po: built-docs inc/always.php scripts/po/rebuild-translations.sh scripts/po/extract.pl po/*.po
	scripts/po/rebuild-translations.sh
	touch built-po

#
# Insert the current version number into always.php
#
inc/always.php: VERSION inc/always.php.in
	sed -e "/^ *.c->version_string *= *'[^']*' *;/ s/^ *.c->version_string *= *'[^']*' *;/\$$c->version_string = '`head -n1 VERSION`';/" <inc/always.php.in >inc/always.php

#
# Build a release .tar.gz file in the directory above us
#
release: built-docs
	-ln -s . $(package)-$(version)
	tar czf ../$(package)-$(version).tar.gz \
	    --no-recursion --dereference $(package)-$(version) \
	    $(shell git ls-files |grep -v '.git'|sed -e s:^:$(package)-$(version)/:) \
	    $(shell find $(package)-$(version)/docs/api/ ! -name "phpdoc.ini" )
	rm $(package)-$(version)
	
clean:
	rm -f built-docs built-po
	-find . -name "*~" -delete
	
clean-all: clean
	-find docs/api/* ! -name "phpdoc.ini" ! -name ".gitignore" -delete

.PHONY:  all clean release
