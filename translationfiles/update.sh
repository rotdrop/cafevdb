#! /bin/bash

LANG=de

APPDIR=$(realpath "$(dirname "$0")/..")
APP=$(basename "$APPDIR")

CLOUDDIR=$(realpath "${APPDIR}/../..")
CLOUDTOOL="php ${CLOUDDIR}/tools/translationtool/translations/translationtool/translationtool.phar"

TMPFILE=$(mktemp)
TEMPLATE=${APPDIR}/translationfiles/templates/${APP}.pot
TRANSLATION=${APPDIR}/translationfiles/${LANG}/${APP}.po

function cleanup() {
    rm -f "${TMPFILE}"
}

cd "$APPDIR" || exit 1

${CLOUDTOOL} create-pot-files

cp "${TEMPLATE}" "${TMPFILE}"
for f in "${APPDIR}"/translationfiles/additions/*.pot ; do
    cat "$f" >> "${TMPFILE}"
done
if msguniq "${TMPFILE}" > /dev/null 2>&1 ; then
    msguniq -o "${TEMPLATE}" "${TMPFILE}"
else
    echo "Broken POT template file" 1>&2
    exit 1
fi

msgmerge -vU --previous --backup=numbered "${TRANSLATION}" "${TEMPLATE}"
${CLOUDTOOL} convert-po-files
