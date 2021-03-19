# This file is licensed under the Affero General Public License version 3 or
# later. See the COPYING file.
#
# @author Claus-Justus Heine <himself@claus-justus-heine.de>
# @copyright Claus-Justus Heine 2020,2021
#
app_name=$(notdir $(CURDIR))
SRCDIR=.
ABSSRCDIR=$(CURDIR)
BUILDDIR=./build
ABSBUILDDIR=$(CURDIR)/build
build_tools_directory=$(BUILDDIR)/tools
DOC_BUILD_DIR=$(ABSBUILDDIR)/artifacts/doc
source_build_directory=$(BUILDDIR)/artifacts/source
source_package_name=$(source_build_directory)/$(app_name)
appstore_build_directory=$(BUILDDIR)/artifacts/appstore
appstore_package_name=$(appstore_build_directory)/$(app_name)
BASH=$(shell which bash 2> /dev/null)
SHELL := $(BASH)
npm=$(shell which npm 2> /dev/null)
COMPOSER_SYSTEM=$(shell which composer 2> /dev/null)
ifeq (, $(COMPOSER_SYSTEM))
COMPOSER=php $(build_tools_directory)/composer.phar
else
COMPOSER=$(COMPOSER_SYSTEM)
endif
COMPOSER_OPTIONS=--no-dev --prefer-dist
PHPDOC=/opt/phpDocumentor/bin/phpdoc
PHPDOC_TEMPLATE=--template=clean
#--template=clean --template=xml

#--template=responsive-twig

MAKE_HELP_DIR=$(SRCDIR)/dev-scripts/MakeHelp
include $(MAKE_HELP_DIR)/MakeHelp.mk

all: help

composer.json: composer.json.in
	cp composer.json.in composer.json

stamp.composer-core-versions: composer.lock
	date > stamp.composer-core-versions

composer.lock: DRY:=
composer.lock: composer.json composer.json.in
	rm -f composer.lock
	$(COMPOSER) install $(COMPOSER_OPTIONS)
	env DRY=$(DRY) dev-scripts/tweak-composer-json.sh || {\
 rm -f composer.lock;\
 $(COMPOSER) install $(COMPOSER_OPTIONS);\
}

pre-build:
	git submodule update --init
.PHONY: pre-build

#@@ Fetches the PHP and JS dependencies and compiles the JS.
#@ If no composer.json is present, the composer step is skipped, if no
#@ package.json or js/package.json is present, the npm step is skipped
build: pre-build composer npm cleanup
.PHONY: build

.PHONY: comoser-download
composer-download:
	mkdir -p $(build_tools_directory)
	curl -sS https://getcomposer.org/installer | php
	mv composer.phar $(build_tools_directory)

# Installs and updates the composer dependencies. If composer is not installed
# a copy is fetched from the web
.PHONY: composer
composer: stamp.composer-core-versions
	$(COMPOSER) install $(COMPOSER_OPTIONS)

.PHONY: node-hacks
node-hacks:
	make -C $(ABSSRCDIR)/3rdparty/selectize

.PHONY: npm-update
npm-update: node-hacks
	npm update

.PHONY: npm-init
npm-init: node-hacks
	npm install

# Installs npm dependencies
.PHONY: npm
npm: npm-init
	npm run dev

npm-build: npm-init
	npm run build

# Removes the appstore build
.PHONY: clean
clean: ## Tidy up local environment
	rm -rf ./build
	rm -rf ./js/*
	rm -rf ./css/*

# Same as clean but also removes dependencies installed by composer, bower and
# npm
.PHONY: distclean
distclean: clean ## Clean even more, calls clean
	rm -rf vendor
	rm -rf node_modules

.PHONY: realclean
realclean: distclean ## Really delete everything but the bare source files
	rm -f composer.lock
	rm -f composer.json
	rm -f stamp.composer-core-versions
	rm -f package-lock.json
	rm -f *.html
	rm -f stats.json

# Builds the source and appstore package
.PHONY: dist
dist:
	make source
	make appstore

$(BUILDDIR)/core-exclude:
	@mkdir -p $(BUILDDIR)
	( cd ../../3rdparty ; find . -mindepth 2 -maxdepth 2  -type d )|sed -e 's|^[.]/|../$(app_name)/vendor/|g' -e 's|$$|/*|g' > $@

.PHONY: cleanup
cleanup: $(BUILDDIR)/core-exclude
	while read LINE; do rm -rf $$(dirname $$LINE); done< <(cat $<)
	$(COMPOSER) dump-autoload

.PHONY: doc
doc: phpdoc doxygen jsdoc

.PHONY: phpdoc
phpdoc: $(PHPDOC)
	rm -rf $(DOC_BUILD_DIR)/phpdoc/*
	$(PHPDOC) run \
 $(PHPDOC_TEMPLATE) \
 --force \
 --parseprivate \
 --visibility api,public,protected,private,internal \
 --sourcecode \
 --defaultpackagename $(app_name) \
 -d $(ABSSRCDIR)/lib -d $(ABSSRCDIR)/appinfo \
 --setting graphs.enabled=true \
 --cache-folder $(ABSBUILDDIR)/phpdoc/cache \
 -t $(DOC_BUILD_DIR)/phpdoc

#--setting guides.enabled=true \
#

.PHONY: doxygen
doxygen: doc/doxygen/Doxyfile
	rm -rf $(DOC_BUILD_DIR)/doxygen/*
	mkdir -p $(DOC_BUILD_DIR)/doxygen
	cd doc/doxygen && doxygen

.PHONY: jsdoc
jsdoc: doc/jsdoc/jsdoc.json
	rm -rf $(DOC_BUILD_DIR)/jsdoc/*
	mkdir -p $(DOC_BUILD_DIR)/jsdoc
	npm run generate-docs

# Builds the source package
.PHONY: source
source:
	rm -rf $(source_build_directory)
	mkdir -p $(source_build_directory)
	tar cvzf $(source_package_name).tar.gz \
	--exclude-vcs \
	--exclude="../$(app_name)/build" \
	--exclude="../$(app_name)/js/node_modules" \
	--exclude="../$(app_name)/node_modules" \
	--exclude="../$(app_name)/*.log" \
	--exclude="../$(app_name)/js/*.log" \
        ../$(app_name)

# Builds the source package for the app store, ignores php and js tests
.PHONY: appstore
appstore: $(BUILDDIR)/core-exclude
	$(COMPOSER) update --no-dev $(COMPOSER_OPTIONS)
	ls -l vendor
	rm -rf $(appstore_build_directory)
	mkdir -p $(appstore_build_directory)
	tar cvzf $(appstore_package_name).tar.gz \
 --exclude-vcs \
 --exclude="../$(app_name)/dev-scripts" \
 --exclude="../$(app_name)/build" \
 --exclude="../$(app_name)/tests" \
 --exclude="../$(app_name)/Makefile" \
 --exclude="../$(app_name)/*.log" \
 --exclude="../$(app_name)/phpunit*xml" \
 --exclude="../$(app_name)/composer.*" \
 --exclude="../$(app_name)/js/node_modules" \
 --exclude="../$(app_name)/js/tests" \
 --exclude="../$(app_name)/js/test" \
 --exclude="../$(app_name)/js/package.json" \
 --exclude="../$(app_name)/js/bower.json" \
 --exclude="../$(app_name)/js/karma.*" \
 --exclude="../$(app_name)/js/protractor.*" \
 --exclude="../$(app_name)/package.json" \
 --exclude="../$(app_name)/bower.json" \
 --exclude="../$(app_name)/karma.*" \
 --exclude="../$(app_name)/protractor\.*" \
 --exclude="../$(app_name)/.*" \
 --exclude="../$(app_name)/js/.*" \
 --exclude-from="$(BUILDDIR)/core-exclude" \
 ../$(app_name)
	$(COMPOSER) install $(COMPOSER_OPTIONS)

.PHONY: verifydb
verifydb:
	$(SRCDIR)/vendor/bin/doctrine orm:validate-schema

.PHONY: updatesql
updatesql:
	$(SRCDIR)/vendor/bin/doctrine orm:schema-tool:update --dump-sql

.PHONY: test
test: composer
	$(CURDIR)/vendor/phpunit/phpunit/phpunit -c phpunit.xml
	$(CURDIR)/vendor/phpunit/phpunit/phpunit -c phpunit.integration.xml

.PHONY: l10n
l10n: translationfiles/update.sh translationfiles/templates/cafevdb.pot
	translationfiles/update.sh
