# This file is licensed under the Affero General Public License version 3 or
# later. See the COPYING file.
#
# @author Claus-Justus Heine <himself@claus-justus-heine.de>
# @copyright Claus-Justus Heine 2020
# @author Bernhard Posselt <dev@bernhard-posselt.com>
# @copyright Bernhard Posselt 2016

# Generic Makefile for building and packaging a Nextcloud app which uses npm and
# Composer.
#
# Dependencies:
# * make
# * which
# * curl: used if phpunit and composer are not installed to fetch them from the web
# * tar: for building the archive
# * npm: for building and testing everything JS
#
# If no composer.json is in the app root directory, the Composer step
# will be skipped. The same goes for the package.json which can be located in
# the app root or the js/ directory.
#
# The npm command by launches the npm build script:
#
#    npm run build
#
# The npm test command launches the npm test script:
#
#    npm run test
#
# The idea behind this is to be completely testing and build tool agnostic. All
# build tools and additional package managers should be installed locally in
# your project, since this won't pollute people's global namespace.
#
# The following npm scripts in your package.json install and update the bower
# and npm dependencies and use gulp as build system (notice how everything is
# run from the node_modules folder):
#
#    "scripts": {
#        "test": "node node_modules/gulp-cli/bin/gulp.js karma",
#        "prebuild": "npm install && node_modules/bower/bin/bower install && node_modules/bower/bin/bower update",
#        "build": "node node_modules/gulp-cli/bin/gulp.js"
#    },

app_name=$(notdir $(CURDIR))
BUILDDIR=./build
BUILDABSDIR=$(CURDIR)/build
build_tools_directory=$(BUILDDIR)/tools
source_build_directory=$(BUILDDIR)/artifacts/source
source_package_name=$(source_build_directory)/$(app_name)
appstore_build_directory=$(BUILDDIR)/artifacts/appstore
appstore_package_name=$(appstore_build_directory)/$(app_name)
BASH=$(shell which bash 2> /dev/null)
SHELL:=$(BASH)
npm=$(shell which npm 2> /dev/null)
COMPOSER_SYSTEM=$(shell which composer 2> /dev/null)
ifeq (, $(COMPOSER_SYSTEM))
COMPOSER=php $(build_tools_directory)/composer.phar
else
COMPOSER=$(COMPOSER_SYSTEM)
endif
COMPOSER_OPTIONS=-vvvvvv --prefer-dist

all: build

composer.json: # no dependency, cleared only by "make realclean"
	cp composer.json.in composer.json

stamp.composer-core-versions: composer.lock
	date > stamp.composer-core-versions

composer.lock: DRY:=
composer.lock: composer.json
	rm -f composer.lock
	$(COMPOSER) install $(COMPOSER_OPTIONS)
	env DRY=$(DRY) dev-scripts/tweak-composer-jons.sh || {\
 rm -f composer.lock;\
 $(COMPOSER) install $(COMPOSER_OPTIONS);\
}

# Fetches the PHP and JS dependencies and compiles the JS. If no composer.json
# is present, the composer step is skipped, if no package.json or js/package.json
# is present, the npm step is skipped
.PHONY: build
build: composer.json
	[ -n "$(wildcard $(CURDIR)/composer.json)" ] && make composer
ifneq (,$(wildcard $(CURDIR)/package.json))
	make npm
endif
ifneq (,$(wildcard $(CURDIR)/js/package.json))
	make npm
endif

.PHONY: provide-composer
provide-composer:
ifeq (, $(COMPOSER_SYSTEM))
	@echo "No composer command available, downloading a copy from the web"
	mkdir -p $(build_tools_directory)
	cd $(build_tools_directory) && curl -sS https://getcomposer.org/installer | php
endif

# Installs and updates the composer dependencies. If composer is not installed
# a copy is fetched from the web
.PHONY: composer
composer: provide-composer stamp.composer-core-versions
	$(COMPOSER) install $(COMPOSER_OPTIONS)

# Installs npm dependencies
.PHONY: npm
npm:
ifeq (,$(wildcard $(CURDIR)/package.json))
	cd js && $(npm) run build
else
	npm run build
endif

# Removes the appstore build
.PHONY: clean
clean:
	rm -rf ./build

# Same as clean but also removes dependencies installed by composer, bower and
# npm
.PHONY: distclean
distclean: clean
	rm -rf vendor
	rm -rf node_modules
	rm -rf js/vendor
	rm -rf js/node_modules

.PHONY: realclean
realclean: distclean
	rm -f composer.lock
	rm -f composer.json
	rm -f stamp.composer-core-versions

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

.PHONY: test
test: composer
	$(CURDIR)/vendor/phpunit/phpunit/phpunit -c phpunit.xml
	$(CURDIR)/vendor/phpunit/phpunit/phpunit -c phpunit.integration.xml
