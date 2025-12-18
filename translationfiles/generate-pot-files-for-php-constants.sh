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
#TRANSLATION_RE='\Wt\(([$][0-9a-zA-Z_\\\\]+\s*=\s*)?([0-9a-zA-Z_\\\\]+)::([^)]+)'
#TRANSLATION_RE='\Wt\(([$][0-9a-zA-Z_\\\\]+\s*=\s*)?(([0-9a-zA-Z_\\\\]+)::([^)\s.]+)((\s*\.\s*)([0-9a-zA-Z_\\\\]+)::([^)\s.]+))?)\)'
PREFIX_RE='([$][0-9a-zA-Z_\\\\]+)\s*=\s*'
SUBEXPR_RE='([0-9a-zA-Z_\\\\]+)::([^) 	.]+)'
QUOTES_RE='"([^"]*)"|'"'([^']*)'"
SEPARATOR_RE='\s*\.\s*'
TRANSLATION_RE='\Wt\(('"${PREFIX_RE}"')?'
TRANSLATION_RE="${TRANSLATION_RE}${SUBEXPR_RE}"
TRANSLATION_RE="${TRANSLATION_RE}"'('"${SEPARATOR_RE}"'('"${SUBEXPR_RE}"'|'"${QUOTES_RE}"'))*'
TRANSLATION_RE="${TRANSLATION_RE}"'\)'
USE_RE='use\s+(\\?([0-9a-zA-Z_]+\\)*([0-9a-zA-Z_]+))(\s+as\s+([0-9a-zA-Z_]+))?'

declare -A SEEN
declare -a CLASSES
declare -a CONSTANTS

while read -r MATCH; do
    FILE=$(echo "$MATCH"|cut -d: -f 1)
    case "$FILE" in
        *~|*#*)
            continue
            ;;
    esac
    SHORT_FILE=${FILE//$APPDIR\//}
    PREFIX=$(echo "$MATCH"|sed -E 's/^.*'"$TRANSLATION_RE"'.*$/\2/g')
    [ -n "$PREFIX" ] && PREFIX="$PREFIX = "
    CLASSES=()
    CONSTANTS=()
    TMP=$(echo "$MATCH"|sed -E 's/^.*\Wt\(('"${PREFIX_RE}"')?//g')
    TMP_RE='^('"${SUBEXPR_RE}"'|'"${QUOTES_RE}"')('"${SEPARATOR_RE}"')?'
    while echo "$TMP"|grep -qsE "$TMP_RE"; do
        CLASS=$(echo "$TMP"|sed -E 's/'"$TMP_RE"'.*$/\2/g')
        CONSTANT=$(echo "$TMP"|sed -E 's/'"$TMP_RE"'.*$/\3/g')
        DQ_VALUE=$(echo "$TMP"|sed -E 's/'"$TMP_RE"'.*$/\4/g')
        SQ_VALUE=$(echo "$TMP"|sed -E 's/'"$TMP_RE"'.*$/\5/g')
        if [ -n "$CLASS" ] && [ -n "$CONSTANT" ]; then
            CLASSES+=("$CLASS")
            CONSTANTS+=("$CONSTANT")
        elif [ -n "$DQ_VALUE" ]; then
            CLASSES+=(__DQ_LITERAL__)
            CONSTANTS+=("$SQ_VALUE")
        elif [ -n "$SQ_VALUE" ]; then
            CLASSES+=(__SQ_LITERAL__)
            CONSTANTS+=("$SQ_VALUE")
        else
            break
        fi
        TMP=$(echo "$TMP"|sed -E 's/'"$TMP_RE"'(.*)$/\7/g')
    done
    VALUE=''
    EXPRESSION=''
    EXPR_JOIN=''
    for ((i = 0 ; i < ${#CLASSES[@]}; i++ )); do
        CLASS=${CLASSES[$i]}
        CONSTANT=${CONSTANTS[$i]}
        if [ "$CLASS" = __DQ_LITERAL__ ]; then
            VALUE="${VALUE}${CONSTANT}"
            EXPRESSION="${EXPRESSION}${EXPR_JOIN}\"${CONSTANT}\""
            EXPR_JOIN=' . '
            continue
        elif [ "$CLASS" = __SQ_LITERAL__ ]; then
            VALUE="${VALUE}${CONSTANT}"
            EXPRESSION="${EXPRESSION}${EXPR_JOIN}'${CONSTANT}'"
            EXPR_JOIN=' . '
            continue
        elif [ "$CLASS" = self ] || [ "$CLASS" = static ]; then
            NAMESPACE='\'$(grep -E '^namespace.*;$' "$FILE"|sed -E 's/^namespace\s+([^;]+);.*$/\1/g')
            CLASS=${NAMESPACE}\\$(basename "$FILE" .php)
        else
            case "$CLASS" in
                \\*)
                    # fully qualified class already
                    NAMESPACE_USE=""
                    ;;
                *\\*)
                    NUM_NS_PARTS=$(echo "$CLASS"|awk -F\\ '{ print NF; }')
                    NAMESPACE_TAIL=$(echo "$CLASS"|cut -d\\ -f 2-"$NUM_NS_PARTS")
                    NAMESPACE_USE=$(echo "$CLASS"|cut -d\\ -f 1)
                    ;;
                *)
                    NAMESPACE_TAIL=""
                    NAMESPACE_USE="$CLASS"
                    ;;
            esac
            DIR=$(dirname "$FILE")
            if [ -f "$DIR/$CLASS.php" ]; then
                NAMESPACE=\\$(grep -E '^\s*namespace' "$FILE"|sed -E 's/^namespace\s+([^;]+);.*$/\1/g')
                CLASS=${NAMESPACE}\\$CLASS
            elif [ -n "$NAMESPACE_USE" ]; then
                while read -r USE_LINE; do
                    if [ -z "$NAMESPACE_TAIL" ]; then
                        FULL_CLASS=$(echo "$USE_LINE"|sed -E 's/^.*'"$USE_RE"'.*$/\1/g')
                    else
                        FULL_CLASS="$(echo "$USE_LINE"|sed -E 's/^.*'"$USE_RE"'.*$/\1/g')\\$NAMESPACE_TAIL"
                    fi
                    BASE_NS=$(echo "$USE_LINE"|sed -E 's/^.*'"$USE_RE"'.*$/\3/g')
                    AS_CLASS=$(echo "$USE_LINE"|sed -E 's/^.*'"$USE_RE"'.*$/\5/g')
                    if [ "$AS_CLASS" = "$NAMESPACE_USE" ] || [ "$BASE_NS" = "$NAMESPACE_USE" ]; then
                        break
                    fi
                    unset FULL_CLASS
                done < <(grep -E "$USE_RE" "$FILE"|grep -F "$NAMESPACE_USE")
                if [ -n "$FULL_CLASS" ]; then
                    CLASS=\\"$FULL_CLASS"
                    unset FULL_CLASS
                fi
            fi
        fi
        VALUE="${VALUE}$(extractConstant "$CLASS" "$CONSTANT")"
        EXPRESSION="${EXPRESSION}${EXPR_JOIN}$CLASS::$CONSTANT"
        EXPR_JOIN=' . '
    done
    # ARRAY_KEY=$(echo "$EXPRESSION"|sed 's/\\/-/g')
    ARRAY_KEY=${EXPRESSION//\\/-}
    LINE=$(echo "$MATCH"|cut -d: -f 2)
    if [ -z "${SEEN[$ARRAY_KEY]}" ]; then
        SEEN[$ARRAY_KEY]=1
        cat <<EOF
#. TRANSLATORS: The expression in the sourcecode was
#. TRANSLATORS: $PREFIX$EXPRESSION = '$VALUE';
EOF
    fi
    cat <<EOF
#: $SHORT_FILE:$LINE
#, php-format
msgid "$VALUE"
msgstr ""

EOF

done < <(grep -E -n -H -r "$TRANSLATION_RE" "$APPDIR"/lib|sort)
