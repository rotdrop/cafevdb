#! /bin/bash
DIRS="lib ajax templates js"
FUNC=Config::toolTips
RE="s/.*"${FUNC}"(['\"]\([^'\"]\\+\)['\"]).*/\\1/g"
OUTPUT=lib/tooltips.txt
for dir in $DIRS; do
    find $dir -name "*.php" -exec fgrep -h ${FUNC} {} \;|sed $RE|sort -u
done > $OUTPUT

php lib/tooltipsort.php
