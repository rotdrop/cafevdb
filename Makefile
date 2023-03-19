# This file is licensed under the Affero General Public License version 3 or
# later. See the COPYING file.
#
# @author Claus-Justus Heine <himself@claus-justus-heine.de>
# @copyright Claus-Justus Heine 2020,2021,2022,2023
#
SRCDIR = .
ABSSRCDIR = $(CURDIR)
#
# try to parse the info.xml if we can, only then fall-back to the directory name
#
APP_INFO = $(SRCDIR)/appinfo/info.xml
XPATH = $(shell which xpath 2> /dev/null)
ifneq ($(XPATH),)
APP_NAME = $(shell $(XPATH) -q -e '/info/id/text()' $(APP_INFO))
else
APP_NAME = $(notdir $(CURDIR))
endif
APP_NAME = $(notdir $(CURDIR))
BUILDDIR = ./build
ABSBUILDDIR = $(CURDIR)/build
BUILD_TOOLS_DIR = $(BUILDDIR)/tools
DOC_BUILD_DIR = $(ABSBUILDDIR)/artifacts/doc
SOURCE_BUILD_DIR = $(BUILDDIR)/artifacts/source
SOURCE_PACKAGE_NAME = $(SOURCE_BUILD_DIR)/$(APP_NAME)
APPSTORE_BUILD_DIR = $(BUILDDIR)/artifacts/appstore
APPSTORE_PACKAGE_NAME = $(APPSTORE_BUILD_DIR)/$(APP_NAME)
BASH=$(shell which bash 2> /dev/null)
SHELL := $(BASH)
PHP = $(shell which php 2> /dev/null) # allow override
PHP_SCOPER_VERSION = 0.18.1
PHP_SCOPER = $(BUILD_TOOLS_DIR)/php-scoper.phar
COMPOSER_SYSTEM = $(shell which composer 2> /dev/null)
ifeq (, $(COMPOSER_SYSTEM))
COMPOSER_TOOL = $(PHP) $(BUILD_TOOLS_DIR)/composer.phar
else
COMPOSER_TOOL=$(COMPOSER_SYSTEM)
endif
COMPOSER_OPTIONS=--prefer-dist
#
OCC=$(ABSSRCDIR)/../../occ
ORM_CLI=$(PHP) $(SRCDIR)/dev-scripts/orm-cmd.php

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
 3rdparty/mediamonks-doctrine-extensions\
 3rdparty/doctrine-on-update-extension

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

PHPDOC = /opt/phpDocumentor/bin/phpdoc
PHPDOC_TEMPLATE =
#--template=clean
#--template=clean --template=xml

#--template=responsive-twig

MAKE_HELP_DIR = $(SRCDIR)/dev-scripts/MakeHelp
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

pre-build: php-scoper-download app-toolkit
#	git submodule update --init
	$(OCC) maintenance:mode --on
.PHONY: pre-build

post-build:
	$(OCC) maintenance:mode --off
	chmod g+rw $(ABSSRCDIR)/../../config/config.php
.PHONY: post-build

#@@ Fetches the PHP and JS dependencies and compiles the JS.
#@ If no composer.json is present, the composer step is skipped, if no
#@ package.json or js/package.json is present, the npm step is skipped
build: dev-setup npm-build post-build
.PHONY: build

#@@ Fetches the PHP and JS dependencies and compiles the JS.
#@ If no composer.json is present, the composer step is skipped, if no
#@ package.json or js/package.json is present, the npm step is skipped
dev: dev-setup npm-dev post-build
.PHONY: dev

dev-setup: pre-build composer namespace-wrapper
.PHONY: dev-setup


.PHONY: composer-download
composer-download:
	mkdir -p $(BUILD_TOOLS_DIR)
	curl -sS https://getcomposer.org/installer | $(PHP)
	mv composer.phar $(BUILD_TOOLS_DIR)

.PHONY: php-scoper-download
php-scoper-download:
	mkdir -p $(BUILD_TOOLS_DIR)
	if ! [ -x $(PHP_SCOPER) ] || ! $(PHP_SCOPER) --version|grep -qsF $(PHP_SCOPER_VERSION); then\
  curl -L -o $(PHP_SCOPER) -sS https://github.com/humbug/php-scoper/releases/download/$(PHP_SCOPER_VERSION)/php-scoper.phar;\
  chmod +x $(PHP_SCOPER);\
fi

# Installs and updates the composer dependencies. If composer is not installed
# a copy is fetched from the web
.PHONY: composer
composer: stamp.composer-core-versions
	$(COMPOSER_TOOL) install $(COMPOSER_OPTIONS)

WRAPPER_PREV_BUILD_HASH = $(shell cat $(ABSSRCDIR)/wrapper-build-hash 2> /dev/null || echo)
WRAPPER_GIT_BUILD_HASH = $(shell { $(WRAPPER_GIT_DEPENDENCIES:%=D=%; echo $$D; git -C $$D rev-parse HEAD;) })

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

$(PHP_SCOPER): php-scoper-download

vendor-wrapped: Makefile $(PHP_SCOPER) scoper.inc.php $(BUILDDIR)/vendor-wrapped
	$(PHP_SCOPER) add-prefix -d$(BUILDDIR) --config=$(ABSSRCDIR)/scoper.inc.php --output-dir=$(ABSSRCDIR)/vendor-wrapped --force
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

#
# Another namespace wrapper, but less complicated, in order to
# decouple our shared Nextcloud traits collection from other apps.
#

APP_TOOLKIT_DIR = $(ABSSRCDIR)/php-toolkit
APP_TOOLKIT_DEST = $(ABSSRCDIR)/lib/Toolkit
APP_TOOLKIT_NS = CAFEVDB

include $(APP_TOOLKIT_DIR)/tools/scopeme.mk

.PHONY: selectize
selectize: $(ABSSRCDIR)/3rdparty/selectize/dist/js/selectize.js $(wildcard $(ABSSRCDIR)/3rdparty/selectize/dist/css/*.css)

$(ABSSRCDIR)/3rdparty/selectize/dist/js/selectize.js: $(shell find $(ABSSRCDIR)/3rdparty/selectize/src -name "*.js")
	make -C $(ABSSRCDIR)/3rdparty/selectize compile

$(wildcard $(ABSSRCDIR)/3rdparty/selectize/dist/css/*.css): $(wildcard $(ABSSRCDIR)/3rdparty/selectize/src/scss/*.scss)
	make -C $(ABSSRCDIR)/3rdparty/selectize compile

CSS_FILES = $(shell find $(ABSSRCDIR)/style -name "*.css" -o -name "*.scss")
JS_FILES = $(shell find $(ABSSRCDIR)/src -name "*.js" -o -name "*.vue")

SELECTIZE_DIST =\
 $(ABSSRCDIR)/3rdparty/selectize/dist/js/selectize.js\
 $(wildcard $(ABSSRCDIR)/3rdparty/selectize/dist/css/*.css)

CHOSEN_DIST = $(wildcard $(ABSSRCDIR)/3rdparty/chosen/public/*)

TINYMCE_DIST = $(wildcard $(ABSSRCDIR)/3rdparty/tinymce/*)

BOOTSTRAP_DUALLISTBOX_DIST = $(wildcard $(ABSSRCDIR)/3rdparty/bootstrap-duallistbox/dist/*)

NPM_INIT_DEPS =\
 Makefile package-lock.json package.json webpack.config.js .eslintrc.js

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
	rm -rf lib/Toolkit/*
.PHONY: distclean

#@@ Really delete everything but the bare source files
realclean: distclean
	rm -f composer*.lock
	rm -f composer.json
	rm -f stamp.composer-core-versions
	rm -f package-lock.json
	rm -f *.html
	rm -f stats.json
	rm -rf js/*
	rm -rf css/*
.PHONY: realclean

# Builds the source and appstore package
.PHONY: dist
dist:
	make source
	make appstore

$(BUILDDIR)/core-exclude:
	@mkdir -p $(BUILDDIR)
	( cd ../../3rdparty ; find . -mindepth 2 -maxdepth 2  -type d )|sed -e 's|^[.]/|../$(APP_NAME)/vendor/|g' -e 's|$$|/*|g' > $@

.PHONY: cleanup
cleanup: $(BUILDDIR)/core-exclude
	while read LINE; do rm -rf $$(dirname $$LINE); done< <(cat $<)
	$(COMPOSER_TOOL) dump-autoload

.PHONY: doc
doc: phpdoc doxygen jsdoc

gh-pages: doc
	cd doc/gh-pages/; \
 git add .; \
 git commit -a -m "Update API docs"; \
 git push
	git commit -m "Update API docs" doc/gh-pages/
	git push

.PHONY: phpdoc
phpdoc: $(PHPDOC)
	rm -rf $(DOC_BUILD_DIR)/phpdoc/*
	$(PHPDOC) run \
 $(PHPDOC_TEMPLATE) \
 --force \
 --parseprivate \
 --visibility api,public,protected,private,internal \
 --sourcecode \
 --defaultpackagename $(APP_NAME) \
 -d $(ABSSRCDIR)/lib -d $(ABSSRCDIR)/appinfo \
 --setting graphs.enabled=true \
 --cache-folder $(ABSBUILDDIR)/phpdoc/cache \
 -t $(DOC_BUILD_DIR)/phpdoc/
	mkdir -p doc/gh-pages/docs/$@/html/
	cd doc/gh-pages/docs/$@/html/; \
 cp -a $(DOC_BUILD_DIR)/$@/. .

#--setting guides.enabled=true \
#

$(SRCDIR)/vendor/bin/phpcs: composer

PHPCS_IGNORE=lib/Database/Doctrine/ORM/Proxies/,templates/legacy/

.PHONY: phpcs
phpcs: $(SRCDIR)/vendor/bin/phpcs
	$(SRCDIR)/vendor/bin/phpcs --ignore=$(PHPCS_IGNORE) -v  --standard=.phpcs.xml lib/ templates/

.PHONY: phpcs-errors
phpcs-errors: $(SRCDIR)/vendor/bin/phpcs
	$(SRCDIR)/vendor/bin/phpcs --ignore=$(PHPCS_IGNORE) -n --standard=.phpcs.xml lib/ templates/|grep FILE:|awk '{ print $$2; }'

.PHONY: doxygen
doxygen: doc/doxygen/Doxyfile
	rm -rf $(DOC_BUILD_DIR)/doxygen/*
	mkdir -p $(DOC_BUILD_DIR)/doxygen
	cd doc/doxygen && doxygen
	mkdir -p doc/gh-pages/docs/$@/html/
	cd doc/gh-pages/docs/$@/html/; \
 cp -a $(DOC_BUILD_DIR)/$@/html/. .

.PHONY: jsdoc
jsdoc: doc/jsdoc/jsdoc.json
	rm -rf $(DOC_BUILD_DIR)/jsdoc/*
	mkdir -p $(DOC_BUILD_DIR)/jsdoc
	npm run generate-docs
	mkdir -p doc/gh-pages/docs/$@/html/
	cd doc/gh-pages/docs/$@/html/; \
 cp -a $(DOC_BUILD_DIR)/$@/. .

# Builds the source package
.PHONY: source
source:
	rm -rf $(SOURCE_BUILD_DIR)
	mkdir -p $(SOURCE_BUILD_DIR)
	tar cvzf $(SOURCE_PACKAGE_NAME).tar.gz \
	--exclude-vcs \
	--exclude="../$(APP_NAME)/build" \
	--exclude="../$(APP_NAME)/js/node_modules" \
	--exclude="../$(APP_NAME)/node_modules" \
	--exclude="../$(APP_NAME)/*.log" \
	--exclude="../$(APP_NAME)/js/*.log" \
        ../$(APP_NAME)

# Builds the source package for the app store, ignores php and js tests
.PHONY: appstore
appstore: $(BUILDDIR)/core-exclude
	$(COMPOSER_TOOL) update --no-dev $(COMPOSER_OPTIONS)
	ls -l vendor
	rm -rf $(APPSTORE_BUILD_DIR)
	mkdir -p $(APPSTORE_BUILD_DIR)
	tar cvzf $(APPSTORE_PACKAGE_NAME).tar.gz \
 --exclude-vcs \
 --exclude="../$(APP_NAME)/dev-scripts" \
 --exclude="../$(APP_NAME)/build" \
 --exclude="../$(APP_NAME)/tests" \
 --exclude="../$(APP_NAME)/Makefile" \
 --exclude="../$(APP_NAME)/*.log" \
 --exclude="../$(APP_NAME)/phpunit*xml" \
 --exclude="../$(APP_NAME)/composer.*" \
 --exclude="../$(APP_NAME)/js/node_modules" \
 --exclude="../$(APP_NAME)/js/tests" \
 --exclude="../$(APP_NAME)/js/test" \
 --exclude="../$(APP_NAME)/js/package.json" \
 --exclude="../$(APP_NAME)/js/bower.json" \
 --exclude="../$(APP_NAME)/js/karma.*" \
 --exclude="../$(APP_NAME)/js/protractor.*" \
 --exclude="../$(APP_NAME)/package.json" \
 --exclude="../$(APP_NAME)/bower.json" \
 --exclude="../$(APP_NAME)/karma.*" \
 --exclude="../$(APP_NAME)/protractor\.*" \
 --exclude="../$(APP_NAME)/.*" \
 --exclude="../$(APP_NAME)/js/.*" \
 --exclude-from="$(BUILDDIR)/core-exclude" \
 ../$(APP_NAME)
	$(COMPOSER_TOOL) install $(COMPOSER_OPTIONS)

.PHONY: verifydb
verifydb: $(ABSSRCDIR)/vendor-wrapped
	$(ORM_CLI) orm:validate-schema || $(ORM_CLI) orm:schema-tool:update --dump-sql

.PHONY: updatesql
updatesql: $(ABSSRCDIR)/vendor-wrapped
	$(ORM_CLI) orm:schema-tool:update --dump-sql

.PHONY: test
test: composer
	$(CURDIR)/vendor/phpunit/phpunit/phpunit -c phpunit.xml
	$(CURDIR)/vendor/phpunit/phpunit/phpunit -c phpunit.integration.xml

.PHONY: l10n
l10n: translationfiles/update.sh translationfiles/templates/cafevdb.pot
	translationfiles/update.sh
