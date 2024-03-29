#!/bin/bash

#
# This tool is used to allow the use of PHP constants as translatable
# strings. Only self::NAME is supported, not static::
#

APPDIR=$(realpath "$(dirname "$0")/..")
EXTRACT_CONSTANTS=$APPDIR/dev-scripts/extract-constants.php

function extractConstant()
{
    CLASS=$1
    CONSTANT=$2
    php "$EXTRACT_CONSTANTS" "$CLASS" "$CONSTANT"
}

#TRANSLATION_RE='\Wt\((("((\\"|[^"])*)"|'"'"'([^'"'"']*)'"'"')\s*[.]\s*|([$][0-9a-zA-Z_\\\\]+\s*=\s*))?([0-9a-zA-Z_\\\\]+)::([^)]+)'
TRANSLATION_RE='\Wt\(([$][0-9a-zA-Z_\\\\]+\s*=\s*)?([0-9a-zA-Z_\\\\]+)::([^)]+)'
USE_RE='use\s+(\\?([0-9a-zA-Z_]+\\)*([0-9a-zA-Z_]+))(\s+as\s+([0-9a-zA-Z_]+))?';

declare -A CONSTANTS

while read -r MATCH; do
    FILE=$(echo "$MATCH"|cut -d: -f 1)
    SHORT_FILE=${FILE//$APPDIR\//}
    PREFIX=$(echo "$MATCH"|sed -E 's/^.*'"$TRANSLATION_RE"'.*$/\1/g')
    [ -n "$PREFIX" ] && PREFIX="$PREFIX . "
    CLASS=$(echo "$MATCH"|sed -E 's/^.*'"$TRANSLATION_RE"'.*$/\2/g')
    CONSTANT=$(echo "$MATCH"|sed -E 's/^.*'"$TRANSLATION_RE"'.*$/\3/g')
    if [ "$CLASS" = self ]; then
        NAMESPACE='\'$(grep -E '^namespace.*;$' "$FILE"|sed -E 's/^namespace\s+([^;]+);.*$/\1/g')
        CLASS=${NAMESPACE}\\$(basename "$FILE" .php)
    else
        DIR=$(dirname "$FILE")
        if [ -f "$DIR/$CLASS.php" ]; then
            NAMESPACE=\\$(grep -E '^\s*namespace' "$FILE"|sed -E 's/^namespace\s+([^;]+);.*$/\1/g')
            CLASS=${NAMESPACE}\\$CLASS
        else
            while read -r USE_LINE; do
                FULL_CLASS=$(echo "$USE_LINE"|sed -E 's/^.*'"$USE_RE"'.*$/\1/g')
                BASE_CLASS=$(echo "$USE_LINE"|sed -E 's/^.*'"$USE_RE"'.*$/\3/g')
                AS_CLASS=$(echo "$USE_LINE"|sed -E 's/^.*'"$USE_RE"'.*$/\5/g')
                if [ "$AS_CLASS" = "$CLASS" ] || [ "$BASE_CLASS" = "$CLASS" ]; then
                    break
                fi
                unset FULL_CLASS
            done < <(grep -E "$USE_RE" $FILE|grep -F "$CLASS")
            if [ -n "$FULL_CLASS" ]; then
                CLASS=\\"$FULL_CLASS"
                unset FULL_CLASS
            fi
        fi
    fi
    LINE=$(echo "$MATCH"|cut -d: -f 2)
    VALUE=$(extractConstant "$CLASS" "$CONSTANT")
    ARRAY_KEY=$(echo "$CLASS-$CONSTANT"|sed 's/\\/-/g')
    if [ -z "${CONSTANTS[$ARRAY_KEY]}" ]; then
        CONSTANTS[$ARRAY_KEY]=1
        cat <<EOF
#. TRANSLATORS: The expression in the sourcecode was
#. TRANSLATORS: $PREFIX$CLASS::$CONSTANT = '$VALUE';
EOF
    fi
    cat <<EOF
#: $SHORT_FILE:$LINE
#, php-format
msgid "$VALUE"
msgstr ""

EOF

done < <(grep -E -n -H -r "$TRANSLATION_RE" "$APPDIR"/lib|sort)
