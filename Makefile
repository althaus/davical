#!/usr/bin/make -f
# 

package=rscds

all: built-docs

built-docs: docs/api/phpdoc.ini htdocs/*.php inc/*.php
	phpdoc -c docs/api/phpdoc.ini
	touch built-docs

clean:
	rm -f built-docs
	-find docs/api/* ! -name "phpdoc.ini" ! -name ".gitignore" -delete
	-find . -name "*~" -delete
	

.PHONY:  all clean
