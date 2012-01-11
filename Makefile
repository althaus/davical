#!/usr/bin/make -f
#

package := davical
majorversion := $(shell sed -n 's:\([0-9\.]*\)[-a-f0-9-]*:\1:p' VERSION)
gitrev := 0
version := $(majorversion)
issnapshot := 0
snapshot : gitrev = $(shell git rev-parse --short HEAD)
snapshot : version = $(majorversion)-git$(gitrev)
snapshot : issnapshot = 1

.PHONY: nodocs
nodocs: htdocs/always.php built-po

.PHONY: all
all: htdocs/always.php built-docs built-po

built-docs: docs/api/phpdoc.ini htdocs/*.php inc/*.php docs/translation.rst
	phpdoc -c docs/api/phpdoc.ini || echo "NOTICE: Failed to build optional API docs"
	rst2pdf docs/translation.rst || echo "NOTICE: Failed to build ReST docs"
	touch $@

built-po: htdocs/always.php scripts/po/rebuild-translations.sh po/*.po
	scripts/po/rebuild-translations.sh
	touch $@

#
# Insert the current version number into always.php
#
htdocs/always.php: inc/always.php.in scripts/build-always.sh VERSION dba/davical.sql
	scripts/build-always.sh <$< >$@

#
# Build a release .tar.gz file in the directory above us
#
.PHONY: release
release: built-docs VERSION
	-ln -s . $(package)-$(version)
	sed 's:@@VERSION@@:$(majorversion):' davical.spec.in | \
	sed 's:@@ISSNAPSHOT@@:$(issnapshot):' | \
	sed 's:@@GITREV@@:$(gitrev):' > davical.spec
	echo "git ls-files |grep -v '.git'|sed -e s:^:$(package)-$(version)/:"
	tar czf ../$(package)-$(version).tar.gz \
	    --no-recursion --dereference $(package)-$(version) \
	    $(shell git ls-files |grep -v '.git'|sed -e s:^:$(package)-$(version)/:) \
	    $(shell find $(package)-$(version)/docs/api/ ! -name "phpdoc.ini" ) \
	    davical.spec
	rm $(package)-$(version)

.PHONY: snapshot
snapshot: release

.PHONY: clean
clean:
	rm -f built-docs built-po
	-find . -name "*~" -delete
	-rm docs/translation.pdf
	-rm davical.spec

.PHONY: clean-all
clean-all: clean
	-find docs/api/* ! -name "phpdoc.ini" ! -name ".gitignore" -delete
