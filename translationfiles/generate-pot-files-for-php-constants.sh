#!/bin/bash

#
# This tool is used to allow the use of PHP constants as translatable
# string. Only self::NAME is supported, not static::
#

APPDIR=$(realpath "$(dirname "$0")/..")
EXTRACT_CONSTANTS=$APPDIR/dev-scripts/extract-constants.php

function extractConstant()
{
    CLASS=$1
    CONSTANT=$2
    php "$EXTRACT_CONSTANTS" "$CLASS" "$CONSTANT"
}

TRANSLATION_RE='\Wt\(self::([^)]+)\)'

while read -r MATCH; do
    FILE=$(echo "$MATCH"|cut -d: -f 1)
    SHORT_FILE=${FILE//$APPDIR\//}
    NAMESPACE='\'$(grep namespace "$FILE"|sed -E 's/^namespace\s+([^;]+);.*$/\1/g')
    CLASS=$NAMESPACE'\'$(basename "$FILE" .php)
    LINE=$(echo "$MATCH"|cut -d: -f 2)
    CONSTANT=$(echo "$MATCH"|sed -E 's/^.*'"$TRANSLATION_RE"'.*$/\1/g')
    VALUE=$(extractConstant "$CLASS" "$CONSTANT")
    cat <<EOF
#: $SHORT_FILE:$LINE
#, php-format
msgid "$VALUE"
msgstr ""

EOF

done < <(grep -E -n -H -r "$TRANSLATION_RE" "$APPDIR"/lib)
