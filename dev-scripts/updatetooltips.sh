#! /bin/bash
DIRS="lib templates"
FUNC=toolTipsService
RE="s/.*"${FUNC}"\s*(['\"]\([^'\"]\\+\)['\"].*/\\1/gI"
OUTPUT=lib/tooltips.txt
for dir in $DIRS; do
    find $dir -name "*.php" -exec fgrep -ih ${FUNC} {} \;|\
        sed -e $RE |\
        sort -u
done > $OUTPUT
