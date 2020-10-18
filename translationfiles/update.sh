#! /bin/bash

APPDIR=$(realpath $(dirname $0)/..)
APP=$(basename $APPDIR)

cd $APPDIR

php ../../tools/translationtool/translations/translationtool/translationtool.phar create-pot-files
msgmerge -vU --previous --backup=numbered translationfiles/de/${APP}.po  translationfiles/templates/${APP}.pot
php ../../tools/translationtool/translations/translationtool/translationtool.phar convert-po-files 
