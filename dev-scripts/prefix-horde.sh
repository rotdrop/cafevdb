#!/bin/bash

cd vendor-wrapped/bytestream
for d in $(find . -name Horde -type d); do
    b=$(dirname $d)
    for i in OCA CAFEVDB Wrapped ; do
        ln -s . $b/$i
    done
done

find . -name "*php" -o -name composer.json -exec sed 's/Horde_/OCA_CAFEVDB_Wrapped_Horder_/g' {} \;
