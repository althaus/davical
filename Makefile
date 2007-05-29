#!/usr/bin/make -f
# 

package=rscds

all: built-docs

built-docs: docs/api/phpdoc.ini htdocs/*.php inc/*.php
	phpdoc -c docs/api/phpdoc.ini
	touch built-docs

inc/always.php: VERSION inc/always.php.in
	sed -e "/^ *.c->version_string *= *'[^']*' *;/ s/^ *.c->version_string *= *'[^']*' *;/\$$c->version_string = '`head -n1 VERSION`';/" <inc/always.php.in >inc/always.php
	# mv inc/always.php.new inc/always.php

clean:
	rm -f built-docs
	-find docs/api/* ! -name "phpdoc.ini" ! -name ".gitignore" -delete
	-find . -name "*~" -delete
	

.PHONY:  all clean
