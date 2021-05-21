#! /bin/bash
DIRS="lib templates"
FUNC=toolTipsService
EFUNC="(\\\$this->$FUNC|\\\$$FUNC\\s*)[([]"
RE="s/.*${FUNC}\\s*[[]\\s*([^[]*)\\s*].*/\\1/gI"

OUTPUT_TMP=$(mktemp /tmp/cafevdb-update-tooltips.XXXXXX)

function cleanup() {
    rm -f "$OUTPUT_TMP"
}

trap cleanup EXIT

OUTPUT=$OUTPUT_TMP
for dir in $DIRS; do
    find "$dir" -name "*.php" -exec egrep -h ${EFUNC} {} \;|\
        sed -E -e "$RE" |\
        fgrep -v '$' |\
        sort -u
done > "$OUTPUT"

cat "$OUTPUT"
echo "$EFUNC"
echo "$RE"
