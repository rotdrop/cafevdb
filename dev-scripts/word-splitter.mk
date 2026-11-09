# SPDX-License-Identifier: AGPL-3.0-or-later

ABSSRCDIR ?= $(CURDIR)
BUILDDIR ?= $(ABSSRCDIR)/build
SPLITTER_PYTHON ?= python3.13
SPLITTER_SOURCE = $(ABSSRCDIR)/python/compound-word-splitting
SPLITTER_BUILD_DIR = $(abspath $(BUILDDIR))/word-splitter
SPLITTER_DIST_DIR = $(ABSSRCDIR)/python
SPLITTER_TOOLS_DIR = $(SPLITTER_BUILD_DIR)/tools-$(notdir $(SPLITTER_PYTHON))
SPLITTER_SITE_PACKAGES = $(SPLITTER_BUILD_DIR)/site-packages
SPLITTER_ARCHIVE = $(SPLITTER_DIST_DIR)/german-word-splitter.pyz
SPLITTER_DICTIONARY = $(SPLITTER_DIST_DIR)/german_utf8_linux.dic
SPLITTER_SOURCES = $(shell find $(SPLITTER_SOURCE)/src -type f -name '*.py' 2>/dev/null)

word-splitter: $(SPLITTER_ARCHIVE) $(SPLITTER_DICTIONARY)
.PHONY: word-splitter

$(SPLITTER_TOOLS_DIR)/.ready: $(ABSSRCDIR)/dev-scripts/word-splitter-requirements.txt
	$(SPLITTER_PYTHON) -c 'import sys; assert sys.version_info >= (3, 13), "The splitter requires Python >= 3.13"'
	$(SPLITTER_PYTHON) -m venv $(SPLITTER_TOOLS_DIR)
	$(SPLITTER_TOOLS_DIR)/bin/python -m pip install -r $<
	touch $@

$(SPLITTER_ARCHIVE): $(SPLITTER_TOOLS_DIR)/.ready $(SPLITTER_SOURCE)/pyproject.toml $(SPLITTER_SOURCES) $(ABSSRCDIR)/dev-scripts/word-splitter.mk
	mkdir -p $(SPLITTER_DIST_DIR)
	rm -rf $(SPLITTER_SITE_PACKAGES)
	$(SPLITTER_TOOLS_DIR)/bin/python -m pip install --no-compile --target $(SPLITTER_SITE_PACKAGES) $(SPLITTER_SOURCE)
	$(SPLITTER_TOOLS_DIR)/bin/python -m shiv --site-packages $(SPLITTER_SITE_PACKAGES) \
	  --python "/usr/bin/env python$$($(SPLITTER_TOOLS_DIR)/bin/python -c 'import sys; print("%d.%d" % sys.version_info[:2])')" \
	  --entry-point german_word_splitter.main:run --output-file $@.tmp
	mv $@.tmp $@

$(SPLITTER_DICTIONARY): $(SPLITTER_SOURCE)/Makefile
	rm -f $@.tmp
	$(MAKE) -C $(SPLITTER_SOURCE) --eval='.DELETE_ON_ERROR:' dictionary \
	  BUILD_DIR=$(SPLITTER_BUILD_DIR)/dictionary DICT_DIR=$(SPLITTER_DIST_DIR) \
	  DICT_UTF8=$@.tmp SHELL=bash '.SHELLFLAGS=-ec -o pipefail'
	test -s $@.tmp
	mv $@.tmp $@
