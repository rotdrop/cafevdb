# This file is licensed under the Affero General Public License version 3 or
# later. See the COPYING file.
#
# @author Claus-Justus Heine <himself@claus-justus-heine.de>
# @copyright Claus-Justus Heine 2020-2026
#
# Thin wrapper around the shared Makefile of the build-system submodule
# dev-scripts/lib, only the app-specific settings and rules live here.
#
SRCDIR = .
ABSSRCDIR = $(CURDIR)
DEV_LIB_DIR = $(ABSSRCDIR)/dev-scripts/lib

BASH = $(shell which bash 2> /dev/null)
SHELL := $(BASH)

ORM_CLI = $(PHP) $(SRCDIR)/dev-scripts/orm-cmd.php
TINYMCE_VERSION = 7

PHPDOC_SETTINGS = --setting guides.enabled=true
PHPDOC_GRAPHS = false
PHPDOC_PREREQ_TARGET = dev-setup

# The Doctrine/ORM stack and related packages are wrapped into the
# app-specific namespace, git dependencies which are occasionally hacked
# trigger a rebuild of the wrapper.
WRAPPER_GIT_DEPENDENCIES =\
 3rdparty/doctrine-orm\
 3rdparty/gedmo-doctrine-extensions\
 3rdparty/mediamonks-doctrine-extensions\
 3rdparty/doctrine-on-update-extension
SCOPED_VENDOR_LINK_DIRS = 3rdparty vendor
# keep the dev-dependencies, composer-patches is one of them
SCOPED_COMPOSER_UPDATE_OPTIONS =

# tinymce and the language packs are downloaded with wget
APP_REQUIRED_TOOLS = WGET

APP_PHP_DIRS = lib

APP_DEV_SETUP_DEPS = ts-app-config package-lock.json

# Everything the bundler needs in addition to the shared dependencies,
# DW_APP_CONFIG is defined below.
CSS_FILES = $(shell find $(ABSSRCDIR)/style -name "*.css" -o -name "*.scss" 2> /dev/null)
DW_APP_CONFIG = node_modules/@rotdrop/nextcloud-app-dokuwiki/build/ts-types/app-config.ts
BUNDLER_DEPS =\
 tinymce\
 $(CSS_FILES)\
 $(SCSS_APP_CONFIG)\
 scss-variables\
 $(DW_APP_CONFIG)

APP_MOSTLYCLEAN_DEPS = tinymce-clean
# Hash files and generated app-local splitter assets.
APP_MOSTLYCLEAN_FILES = app-git-build-hash wrapper-build-hash \
 python/german-word-splitter.pyz python/german-word-splitter.pyz.tmp \
 python/german_utf8_linux.dic python/german_utf8_linux.dic.tmp

APPSTORE_FILES =\
 appinfo\
 css\
 js\
 img\
 l10n\
 lib\
 vendor\
 $(SCOPED_VENDOR_DIR)\
 CHANGELOG.md\
 LICENSE\
 README.md
APPSTORE_COPY_HOOK = appstore-core-exclude

MAKEFILE_DEP = Makefile

# Needed before the include of phpdoc.mk as it is used in a prerequisite list.
APP_BUILD_HASH = app-git-build-hash

include $(DEV_LIB_DIR)/makefile/app.mk
include $(DEV_LIB_DIR)/makefile/phpunit.mk
PHPUNIT_ARGS += --stop-on-failure --stop-on-error
include $(DEV_LIB_DIR)/makefile/phpdoc.mk
include $(ABSSRCDIR)/dev-scripts/word-splitter.mk

DOC_BUILD_DIR = $(ABSBUILDDIR)/artifacts/doc
SOURCE_BUILD_DIR = $(BUILDDIR)/artifacts/source
SOURCE_PACKAGE_NAME = $(SOURCE_BUILD_DIR)/$(APP_NAME)

#@private
post-build:
	@if [ $$(npm list --parseable pinia|wc -l) -gt 1 ]; then\
 echo -e "\nMultiple different versions of pinia are installed, this is likely to crash at runtime!\n" 1>&2;\
 exit 1;\
fi
.PHONY: post-build

build dev: post-build word-splitter

PHPUNIT_COMPOSER_JSON = $(ABSSRCDIR)/vendor-bin/phpunit/composer.json
PHPUNIT_COMPOSER_LOCK = $(wildcard $(ABSSRCDIR)/vendor-bin/phpunit/composer.lock)
$(PHING) $(PHPUNIT): composer.lock $(PHPUNIT_COMPOSER_JSON) $(PHPUNIT_COMPOSER_LOCK)
	if [ ! -f $(ABSSRCDIR)/vendor-bin/phpunit/composer.lock ] || [ $(PHPUNIT_COMPOSER_JSON) -nt $(ABSSRCDIR)/vendor-bin/phpunit/composer.lock ]; then \
	  $(COMPOSER) bin phpunit update && touch $@; \
	else \
	  $(COMPOSER) bin phpunit install && touch $@; \
	fi

# Copy from node_modules/ to 3rdparty/tinymce/
.PHONY: tinymce
tinymce: $(ABSSRCDIR)/3rdparty/tinymce/__ready__

$(ABSSRCDIR)/3rdparty/tinymce/__ready__: $(ABSSRCDIR)/node_modules/tinymce/package.json
	for FILE in $$(find node_modules/tinymce -name "*.js" -o -name "*.css"); do\
  DEST=$$(echo $$FILE|sed 's/node_modules/3rdparty/g');\
  mkdir -p $$(dirname $$DEST);\
  cp $$FILE $$DEST;\
done
	rm -f $(ABSSRCDIR)/3rdparty/tinymce/*.js
	cd $$(dirname $@);\
 $(WGET) https://download.tiny.cloud/tinymce/community/languagepacks/$(TINYMCE_VERSION)/langs.zip;\
 unzip -o langs.zip;\
 rm -f langs.zip
	date > $@

$(ABSSRCDIR)/node_modules/tinymce/package.json: package-lock.json

#@private
tinymce-clean:
	rm -rf $(ABSSRCDIR)/3rdparty/tinymce/*
.PHONY: tinymce-clean

# Third-party patch, applied if the generated file is missing
$(DW_APP_CONFIG): node_modules
	if ! [ -f $(DW_APP_CONFIG) ]; then\
 cd $(ABSSRCDIR);\
 patch -p1 < $(ABSSRCDIR)/patches/@rotdrop+nextcloud-app-dokuwiki+1.2.2.patch;\
fi

#@@ Regenerates the app-calendar patch from the installed npm package.
fabricate-app-calendar-patch:
	$(BASH) $(ABSSRCDIR)/dev-scripts/fabricate-app-calendar-patch.sh
.PHONY: fabricate-app-calendar-patch

# Packages which are provided by the cloud itself are removed from the
# archive, this needs the cloud's 3rdparty/ directory.
CLOUD_3RDPARTY_DIR = $(ABSSRCDIR)/../../3rdparty

#@private
appstore-core-exclude: word-splitter
	mkdir -p $(APPSTORE_SIGN_DIR)/$(APP_NAME)/python
	$(RSYNC) -a $(SPLITTER_ARCHIVE) $(SPLITTER_DICTIONARY) $(APPSTORE_SIGN_DIR)/$(APP_NAME)/python/
	mkdir -p $(APPSTORE_SIGN_DIR)/$(APP_NAME)/3rdparty
	$(RSYNC) -a -L $(APPSTORE_BLACKLISTED:%=--exclude '%') 3rdparty/tinymce $(APPSTORE_SIGN_DIR)/$(APP_NAME)/3rdparty
	$(SILENT)if [ -d "$(CLOUD_3RDPARTY_DIR)" ]; then\
 ( cd $(CLOUD_3RDPARTY_DIR); find . -mindepth 2 -maxdepth 2 -type d )\
 | while read DIR; do rm -rf "$(APPSTORE_SIGN_DIR)/$(APP_NAME)/vendor/$${DIR#./}"; done;\
else\
 echo "No cloud 3rdparty/ directory, not excluding cloud packages from the archive." 1>&2;\
fi
.PHONY: appstore-core-exclude

# Builds the source and appstore package
.PHONY: dist
dist:
	make source
	make appstore

APP_PREV_BUILD_HASH = $(shell cat $(ABSSRCDIR)/$(APP_BUILD_HASH) 2> /dev/null || echo)
APP_GIT_BUILD_HASH = $(shell { $(ABSSRCDIR:%=D=%; echo $$D; git -C $$D rev-parse HEAD;) })

ifneq ($(APP_PREV_BUILD_HASH), $(APP_GIT_BUILD_HASH))
.PHONY: $(APP_BUILD_HASH)
endif
$(APP_BUILD_HASH):
	@echo "GIT repository of the app has changed"
	@echo "OLD HASH $(APP_PREV_BUILD_HASH)"
	@echo "NEW HASH $(APP_GIT_BUILD_HASH)"
	echo $(APP_GIT_BUILD_HASH) > $@

###############################################################################
#
# START DOCS

###############################################################################
#
# Test-data for the members companion app

MEMBERS_APP_DIR ?= $(ABSSRCDIR)/../cafevdbmembers
MEMBERS_REPO_URL ?= https://github.com/Camerata-Academica-Freibug-e-V/cafevdb-members.git
MEMBERS_BRANCH ?= stable35
MEMBERS_PUSH ?= 0
MEMBERS_TEST_DATA_DIR = $(MEMBERS_APP_DIR)/tests/phpunit/data
TEST_ARTIFACTS_DIR = $(ABSBUILDDIR)/artifacts/tests/phpunit
TEST_ARTIFACT_PREFIX = OCA\CAFEVDB\Tests\Unit\ArtifactsTest::testArtifacts-
CLOUD_CONNECTOR_DUMP = $(TEST_ARTIFACTS_DIR)/$(TEST_ARTIFACT_PREFIX)cafevdb_cloud_connector.sql
APP_DUMP = $(TEST_ARTIFACTS_DIR)/$(TEST_ARTIFACT_PREFIX)cafevdb.sql
# The dumps contain the Doctrine migrations table, regenerate if a migration is missing.
MIGRATION_CLASSES = $(basename $(notdir $(wildcard $(ABSSRCDIR)/lib/Maintenance/Migrations/Version*.php)))

#@@ Copy the SQL dumps generated by the ArtifactsTest to the members app
#@ (MEMBERS_APP_DIR) and commit them there. The view order of the cloud
#@ connector dump is fixed (Files before FileData). If MEMBERS_APP_DIR does
#@ not exist, MEMBERS_REPO_URL (branch MEMBERS_BRANCH) is cloned there first.
#@ With MEMBERS_PUSH=1 the commit is pushed to the remote (e.g. in CI, put
#@ credentials into MEMBERS_REPO_URL). Only run explicitly. If the SQL dumps do
#@ not exist or lack a migration from lib/Maintenance/Migrations/,
#@ only the ArtifactsTest of the PHPUnit suite is run to generate them. It is an
#@ error if a migration is still missing after that.
members-test-data:
	@missing() { \
 for migration in $(MIGRATION_CLASSES); do \
  grep -q "$$migration" '$(APP_DUMP)' || { echo "$$migration"; return 0; }; \
 done; return 1; }; \
regenerate=0; \
if ! [ -f '$(CLOUD_CONNECTOR_DUMP)' ] || ! [ -f '$(APP_DUMP)' ]; then \
 regenerate=1; \
elif m=$$(missing); then \
 echo "Migration $$m is missing in $(APP_DUMP)."; regenerate=1; \
fi; \
if [ $$regenerate = 1 ]; then \
 echo "Test artifacts missing or outdated in $(TEST_ARTIFACTS_DIR), running the ArtifactsTest."; \
 $(MAKE) $(PHPUNIT) && $(PHP) $(PHPUNIT) -c phpunit.xml --no-coverage --display-all-issues --filter 'ArtifactsTest' || exit 1; \
 if ! [ -f '$(CLOUD_CONNECTOR_DUMP)' ] || ! [ -f '$(APP_DUMP)' ]; then \
  echo "ArtifactsTest did not produce the SQL dumps in $(TEST_ARTIFACTS_DIR)." 1>&2; exit 1; \
 fi; \
 if m=$$(missing); then \
  echo "Migration $$m is still missing in $(APP_DUMP) after regenerating it." 1>&2; exit 1; \
 fi; \
fi
	@if ! [ -d '$(MEMBERS_APP_DIR)/.git' ]; then \
 git clone --branch '$(MEMBERS_BRANCH)' '$(MEMBERS_REPO_URL)' '$(MEMBERS_APP_DIR)' || exit 1; \
fi
	mkdir -p '$(MEMBERS_TEST_DATA_DIR)'
	$(ABSSRCDIR)/dev-scripts/fix-cloud-connector-dump.py '$(CLOUD_CONNECTOR_DUMP)' '$(MEMBERS_TEST_DATA_DIR)/cloud_connector.sql'
	cp '$(APP_DUMP)' '$(MEMBERS_TEST_DATA_DIR)/app.sql'
	git -C '$(MEMBERS_APP_DIR)' add tests/phpunit/data/cloud_connector.sql tests/phpunit/data/app.sql
	@if git -C '$(MEMBERS_APP_DIR)' diff --cached --quiet -- tests/phpunit/data; then \
 echo "Members test data is already up to date."; \
else \
 git -C '$(MEMBERS_APP_DIR)' commit -m "Update test databases from cafevdb ArtifactsTest" -- tests/phpunit/data/cloud_connector.sql tests/phpunit/data/app.sql || exit 1; \
 if [ '$(MEMBERS_PUSH)' = 1 ]; then git -C '$(MEMBERS_APP_DIR)' push origin 'HEAD:$(MEMBERS_BRANCH)'; fi; \
fi
.PHONY: members-test-data

#@@ Build the documentation. May take a long time
doc: phpdoc doxygen jsdoc tsdoc
.PHONY: doc

GH_PAGES_BUILD_DIR = $(DOC_BUILD_DIR)/gh-pages/
GH_PAGES_PHPDOC_HTML = $(GH_PAGES_BUILD_DIR)/docs/phpdoc/html/
GH_PAGES_DOXYGEN_HTML = $(GH_PAGES_BUILD_DIR)/docs/doxygen/html/
GH_PAGES_JSDOC_HTML = $(GH_PAGES_BUILD_DIR)/docs/jsdoc/html/
GH_PAGES_TSDOC_HTML = $(GH_PAGES_BUILD_DIR)/docs/typedoc/html/

$(GH_PAGES_BUILD_DIR):
	if [ -d "$@" ]; then\
  git -C "$@" checkout gh-pages;\
  git -C "$@" reset --hard origin/gh-pages;\
else\
  git clone --depth 2 -b gh-pages git@github.com:Camerata-Academica-Freibug-e-V/cafevdb.git $@;\
fi

.PHONY: gh-pages
gh-pages: $(GH_PAGES_PHPDOC_HTML)/index.html $(GH_PAGES_DOXYGEN_HTML)/index.html $(GH_PAGES_JSDOC_HTML)/index.html $(GH_PAGES_TSDOC_HTML)/index.html
	cd $(GH_PAGES_BUILD_DIR); \
 git add .; \
 if git diff --cached --quiet; then echo "API docs are up to date."; exit 0; fi; \
 git commit -m "Update API docs"; \
 git push

$(GH_PAGES_PHPDOC_HTML)/index.html: $(GH_PAGES_BUILD_DIR) $(PHPDOC_HTML)/index.html
	mkdir -p $(GH_PAGES_PHPDOC_HTML)
	cp -a $(PHPDOC_HTML)/. $(GH_PAGES_PHPDOC_HTML)/.

DOXYGEN_HTML = $(DOC_BUILD_DIR)/doxygen/html/

.PHONY: doxygen
doxygen: $(DOXYGEN_HTML)/index.html

$(GH_PAGES_DOXYGEN_HTML)/index.html: $(GH_PAGES_BUILD_DIR) $(DOXYGEN_HTML)/index.html
	mkdir -p $(GH_PAGES_DOXYGEN_HTML)
	cp -a $(DOXYGEN_HTML)/. $(GH_PAGES_DOXYGEN_HTML)/.

$(DOXYGEN_HTML)/index.html: doc/doxygen/Doxyfile $(APP_BUILD_HASH) $(MAKEFILE_DEP)
	rm -rf $(DOXYGEN_HTML)
	mkdir -p $(DOXYGEN_HTML)
	cd doc/doxygen && doxygen

JSDOC_HTML = $(DOC_BUILD_DIR)/jsdoc/

.PHONY: jsdoc
jsdoc: $(JSDOC_HTML)/index.html

$(GH_PAGES_JSDOC_HTML)/index.html: $(GH_PAGES_BUILD_DIR) $(JSDOC_HTML)/index.html
	mkdir -p $(GH_PAGES_JSDOC_HTML)
	cp -a $(JSDOC_HTML)/. $(GH_PAGES_JSDOC_HTML)/.

$(JSDOC_HTML)/index.html: doc/jsdoc/jsdoc.json $(APP_BUILD_HASH) $(WEBPACK_DEPS) $(MAKEFILE_DEP)
	rm -rf $(JSDOC_HTML)
	mkdir -p $(JSDOC_HTML)
	$(NPM) run generate-docs

TSDOC_HTML = $(DOC_BUILD_DIR)/typedoc/

.PHONY: tsdoc
tsdoc: $(TSDOC_HTML)/index.html

$(GH_PAGES_TSDOC_HTML)/index.html: $(GH_PAGES_BUILD_DIR) $(TSDOC_HTML)/index.html
	mkdir -p $(GH_PAGES_TSDOC_HTML)
	cp -a $(TSDOC_HTML)/. $(GH_PAGES_TSDOC_HTML)/.

$(TSDOC_HTML)/index.html: doc/typedoc/typedoc.mjs $(APP_BUILD_HASH) $(WEBPACK_DEPS) $(MAKEFILE_DEP)
	rm -rf $(TSDOC_HTML)
	mkdir -p $(TSDOC_HTML)
	$(NPM) run generate-typedoc

# END DOCS
#
###############################################################################

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


.PHONY: orm-clear-cache
#@private
orm-clear-cache: $(ABSSRCDIR)/$(SCOPED_VENDOR_DIR)
	$(ORM_CLI) orm:clear-cache:metadata

.PHONY: verifydb
verifydb: orm-clear-cache $(ABSSRCDIR)/$(SCOPED_VENDOR_DIR)
	$(ORM_CLI) orm:validate-schema
	$(ORM_CLI) orm:schema-tool:update --dump-sql

.PHONY: updatesql
updatesql: orm-clear-cache $(ABSSRCDIR)/$(SCOPED_VENDOR_DIR)
	$(ORM_CLI) orm:schema-tool:update --dump-sql

#@private
run-jstest:
	npm run test
.PHONY: run-jstest

#@@ Runs integration test for TypeScript code
jstest: dev-setup ts-app-config ts-types-files run-jstest post-build
.PHONY: jstest


# .PHONY: l10n
# l10n: translationfiles/update.sh translationfiles/templates/cafevdb.pot
# 	translationfiles/update.sh
