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
COMPOSER_TOOL=php $(build_tools_directory)/composer.phar
else
COMPOSER_TOOL=$(COMPOSER_SYSTEM)
endif
COMPOSER_OPTIONS=--prefer-dist

###############################################################################
#
# Some composer packages must be wrapped into a different namespace to
# avoid conflicts with the ambient cloud software, notably
# Doctrine/ORM and all related packages.
#

WRAPPER_NAMESPACE = OCA\\CAFEVDB\\Wrapped

# hash dependencies which occasionally are hacked
WRAPPER_GIT_DEPENDENCIES =\
 3rdparty/doctrine-orm\
 3rdparty/gedmo-doctrine-extensions\
 3rdparty/mediamonks-doctrine-extensions

NAMESPACE_WRAPPER_DIRS =\
 lib/Database/Doctrine\
 lib/Database/Legacy
NAMESPACE_WRAPPER_FILES =\
 config/cli-config.php\
 lib/Common/Uuid.php\
 lib/Controller/ImagesController.php\
 lib/Controller/ProjectParticipantFieldsController.php\
 lib/Controller/SepaDebitMandatesController.php\
 lib/Database/Connection.php\
 lib/Database/EntityManager.php\
 lib/PageRenderer/ProjectParticipantFields.php\
 lib/Service/Finance/DoNothingReceivablesGenerator.php\
 lib/Service/Finance/IRecurringReceivablesGenerator.php\
 lib/Service/Finance/SepaBulkTransactionService.php\
 lib/Service/Finance/InstrumentInsuranceReceivablesGenerator.php\
 lib/Service/Finance/PeriodicReceivablesGenerator.php\
 lib/Service/Finance/FinanceService.php\
 lib/Service/ContactsService.php\
 lib/Service/GeoCodingService.php\
 lib/Traits/EntityManagerTrait.php

# The complete list of affected files
NAMESPACE_WRAPPER_VICTIMS = $(foreach dir,$(NAMESPACE_WRAPPER_DIRS),$(shell find $(dir) -name '*.php'))\
 $(NAMESPACE_WRAPPER_FILES)

# list of namespaces to wrap
WRAPPED_NAMESPACES =\
 Acelaya\\Doctrine\
 Carbon\
 Doctrine\
 DoctrineExtensions\
 CJH\\Doctrine\\Extensions\
 Gedmo\
 MediaMonks\\Doctrine\
 MyCLabs\\Enum\
 Oro\\DBAL\
 Oro\\ORM\
 Ramsey\\Uuid\
 Ramsey\\Uuid\\Doctrine

#
#
#
###############################################################################

PHPDOC=/opt/phpDocumentor/bin/phpdoc
PHPDOC_TEMPLATE=
#--template=clean
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
	$(COMPOSER_TOOL) install $(COMPOSER_OPTIONS)
	env DRY=$(DRY) dev-scripts/tweak-composer-json.sh || {\
 rm -f composer.lock;\
 $(COMPOSER_TOOL) install $(COMPOSER_OPTIONS);\
}

pre-build:
#	git submodule update --init
.PHONY: pre-build

#@@ Fetches the PHP and JS dependencies and compiles the JS.
#@ If no composer.json is present, the composer step is skipped, if no
#@ package.json or js/package.json is present, the npm step is skipped
build: pre-build composer namespace-wrapper npm-build
.PHONY: build

#@@ Fetches the PHP and JS dependencies and compiles the JS.
#@ If no composer.json is present, the composer step is skipped, if no
#@ package.json or js/package.json is present, the npm step is skipped
dev: pre-build composer namespace-wrapper npm-dev
.PHONY: dev

.PHONY: comoser-download
composer-download:
	mkdir -p $(build_tools_directory)
	curl -sS https://getcomposer.org/installer | php
	mv composer.phar $(build_tools_directory)

# Installs and updates the composer dependencies. If composer is not installed
# a copy is fetched from the web
.PHONY: composer
composer: stamp.composer-core-versions
	$(COMPOSER_TOOL) install $(COMPOSER_OPTIONS)

WRAPPER_PREV_BUILD_HASH = $(shell cat $(ABSSRCDIR)/wrapper-build-hash 2> /dev/null || echo)
WRAPPER_GIT_BUILD_HASH = $(shell git rev-parse $(WRAPPER_GIT_DEPENDENCIES:%=HEAD:%)\
 | git hash-object --stdin)

ifneq ($(WRAPPER_PREV_BUILD_HASH), $(WRAPPER_GIT_BUILD_HASH))
.PHONY: wrapper-build-hash
endif
wrapper-build-hash:
	@echo "GIT dependencies of PHP-Scoper have changed, need to rebuild the wrapper"
	@echo "OLD HASH $(WRAPPER_PREV_BUILD_HASH)"
	@echo "NEW HASH $(WRAPPER_GIT_BUILD_HASH)"
	echo $(WRAPPER_GIT_BUILD_HASH) > wrapper-build-hash

composer-wrapped.lock: composer-wrapped.json
	rm -f composer-wrapped.lock

$(BUILDDIR)/vendor-wrapped: composer-wrapped.lock wrapper-build-hash
	mkdir -p $(BUILDDIR)
	ln -fs ../3rdparty $(BUILDDIR)
	ln -fs ../vendor $(BUILDDIR)
	rm -rf $(BUILDDIR)/vendor-wrapped
	env COMPOSER="$(ABSSRCDIR)/composer-wrapped.json" $(COMPOSER_TOOL) -d$(BUILDDIR) install $(COMPOSER_OPTIONS)
	env COMPOSER="$(ABSSRCDIR)/composer-wrapped.json" $(COMPOSER_TOOL) -d$(BUILDDIR) update $(COMPOSER_OPTIONS)

.PHONY: composer-wrapped-suggest
composer-wrapped-suggest:
	@echo -e "\n*** Wrapped Composer Suggestions ***\n"
	env COMPOSER="$(ABSSRCDIR)/composer-wrapped.json" $(COMPOSER_TOOL) -d$(BUILDDIR) suggest --all

.PHONY: composer-suggest
composer-suggest: composer-wrapped-suggest
	@echo -e "\n*** Regular Composer Suggestions ***\n"
	$(COMPOSER_TOOL) suggest --all

vendor/bin/php-scoper: composer

vendor-wrapped: Makefile vendor/bin/php-scoper scoper.inc.php $(BUILDDIR)/vendor-wrapped
	vendor/bin/php-scoper add-prefix -d$(BUILDDIR) --config=$(ABSSRCDIR)/scoper.inc.php --output-dir=$(ABSSRCDIR)/vendor-wrapped --force
# scoper does not handle symlinks
	cp -a $(BUILDDIR)/vendor-wrapped/bin $(ABSSRCDIR)/vendor-wrapped/
# scoper does not preserve executable bits
	find $(ABSSRCDIR)/vendor-wrapped -name bin -a -type d -exec chmod -R gu+x {} \;

vendor-wrapped/autoload.php: vendor-wrapped
	env COMPOSER="$(ABSSRCDIR)/composer-wrapped.json" $(COMPOSER_TOOL) dump-autoload

.PHONY: namespace-wrapper
namespace-wrapper: vendor-wrapped/autoload.php

namespace-wrapper-patch: $(NAMESPACE_WRAPPER_VICTIMS)
	@sed -E -i ${foreach NS,$(WRAPPED_NAMESPACES),\
 -e 's/use $(NS)/use $(WRAPPER_NAMESPACE)\\$(NS)/g'\
 -e 's/([( ])\\$(NS)/\1\\$(WRAPPER_NAMESPACE)\\$(NS)/g'\
}\
 $(NAMESPACE_WRAPPER_VICTIMS)

namespace-wrapper-unpatch: $(NAMESPACE_WRAPPER_VICTIMS)
	@sed -E -i ${foreach NS,$(WRAPPED_NAMESPACES),\
 -e 's/use $(WRAPPER_NAMESPACE)\\$(NS)/use $(NS)/g'\
 -e 's/([( ])\\$(WRAPPER_NAMESPACE)\\$(NS)/\1\\$(NS)/g'\
}\
 $(NAMESPACE_WRAPPER_VICTIMS)

.PHONY: selectize
selectize: $(ABSSRCDIR)/3rdparty/selectize/dist/js/selectize.js $(wildcard $(ABSSRCDIR)/3rdparty/selectize/dist/css/*.css)

$(ABSSRCDIR)/3rdparty/selectize/dist/js/selectize.js: $(shell find $(ABSSRCDIR)/3rdparty/selectize/src -name "*.js")
	make -C $(ABSSRCDIR)/3rdparty/selectize

$(wildcard $(ABSSRCDIR)/3rdparty/selectize/dist/css/*.css): $(wildcard $(ABSSRCDIR)/3rdparty/selectize/src/scss/*.scss)
	make -C $(ABSSRCDIR)/3rdparty/selectize

CSS_FILES = $(shell find $(ABSSRCDIR)/style -name "*.css" -o -name "*.scss")
JS_FILES = $(shell find $(ABSSRCDIR)/src -name "*.js")

SELECTIZE_DIST =\
 $(ABSSRCDIR)/3rdparty/selectize/dist/js/selectize.js\
 $(wildcard $(ABSSRCDIR)/3rdparty/selectize/dist/css/*.css)

CHOSEN_DIST = $(wildcard $(ABSSRCDIR)/3rdparty/chosen/public/*)

TINYMCE_DIST = $(wildcard $(ABSSRCDIR)/3rdparty/tinymce/*)

BOOTSTRAP_DUALLISTBOX_DIST = $(wildcard $(ABSSRCDIR)/3rdparty/bootstrap-duallistbox/dist/*)

NPM_INIT_DEPS =\
 Makefile package-lock.json package.json webpack.config.js

THIRD_PARTY_NPM_DEPS = $(SELECTIZE_DIST) $(BOOTSTRAP_DUALLISTBOX_DIST)

WEBPACK_DEPS =\
 $(NPM_INIT_DEPS)\
 $(TINYMCE_DIST)\
 $(CHOSEN_DIST)\
 $(CSS_FILES) $(JS_FILES)

# CSS_TARGETS = app.css settings.css admin-settings.css
# JS_TARGETS = app.js settings.js admin-settings.js
# WEBPACK_TARGETS =\
#  $(patsubst %,$(ABSSRCDIR)/css/%,$(CSS_TARGETS))\
#  $(patsubst %,$(ABSSRCDIR)/js/%,$(JS_TARGETS))

WEBPACK_TARGETS = $(ABSSRCDIR)/js/asset-meta.json

package-lock.json: package.json webpack.config.js Makefile $(THIRD_PARTY_NPM_DEPS)
	{ [ -d package-lock.json ] && [ test -d node_modules ]; } || npm install
	npm update
	touch package-lock.json

BUILD_FLAVOUR_FILE = $(ABSSRCDIR)/build-flavour
PREV_BUILD_FLAVOUR = $(shell cat $(BUILD_FLAVOUR_FILE) 2> /dev/null || echo)

$(WEBPACK_TARGETS): $(WEBPACK_DEPS) $(BUILD_FLAVOUR_FILE)
	make webpack-clean
	npm run $(shell cat $(BUILD_FLAVOUR_FILE)) || rm -f $(WEBPACK_TARGETS)

.PHONY: build-flavour-dev
build-flavour-dev:
ifneq ($(PREV_BUILD_FLAVOUR), dev)
	make clean
	echo dev > $(BUILD_FLAVOUR_FILE)
endif

.PHONY: build-flavour-build
build-flavour-build:
ifneq ($(PREV_BUILD_FLAVOUR), build)
	make clean
	echo build > $(BUILD_FLAVOUR_FILE)
endif

.PHONY: npm-dev
npm-dev: build-flavour-dev $(WEBPACK_TARGETS)

.PHONY: npm-build
npm-build: build-flavour-build $(WEBPACK_TARGETS)

#@@ Removes WebPack builds
webpack-clean:
	rm -rf ./js/*
	rm -rf ./css/*
.PHONY: webpack-clean

#@@ Removes build files
clean: ## Tidy up local environment
	rm -rf $(BUILDDIR)
.PHONY: clean

#@@ Same as clean but also removes dependencies installed by composer, bower and npm
distclean: clean ## Clean even more, calls clean
	rm -rf vendor*
	rm -rf node_modules
.PHONY: distclean

#@@ Really delete everything but the bare source files
realclean: distclean
	rm -f composer*.lock
	rm -f composer.json
	rm -f stamp.composer-core-versions
	rm -f package-lock.json
	rm -f *.html
	rm -f stats.json
.PHONY: realclean

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
	$(COMPOSER_TOOL) dump-autoload

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
 -t $(DOC_BUILD_DIR)/phpdoc/

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
	$(COMPOSER_TOOL) update --no-dev $(COMPOSER_OPTIONS)
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
	$(COMPOSER_TOOL) install $(COMPOSER_OPTIONS)

.PHONY: verifydb
verifydb: $(ABSSRCDIR)/vendor-wrapped/bin/doctrine
	$< orm:validate-schema || $< orm:schema-tool:update --dump-sql

.PHONY: updatesql
updatesql: $(ABSSRCDIR)/vendor-wrapped/bin/doctrine
	$< orm:schema-tool:update --dump-sql

.PHONY: test
test: composer
	$(CURDIR)/vendor/phpunit/phpunit/phpunit -c phpunit.xml
	$(CURDIR)/vendor/phpunit/phpunit/phpunit -c phpunit.integration.xml

.PHONY: l10n
l10n: translationfiles/update.sh translationfiles/templates/cafevdb.pot
	translationfiles/update.sh
