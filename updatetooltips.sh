#! /bin/bash
DIRS="lib ajax templates js"
FUNC=Config::toolTips
RE="s/.*"${FUNC}"\s*(['\"]\([^'\"]\\+\)['\"].*/\\1/gI"
OUTPUT=lib/tooltips.txt
for dir in $DIRS; do
    find $dir -name "*.php" -exec fgrep -ih ${FUNC} {} \;|\
        sed -e $RE |\
        sort -u
done > $OUTPUT

php lib/tooltipsort.php
