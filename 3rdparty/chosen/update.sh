#! /bin/bash
SOURCE="${HOME}/projects/jquery/jquery-chosen/chosen/public/"

for i in css/*; do
    f=$(basename $i)
    cp ${SOURCE}/$f css/
done

for i in js/*; do
    f=$(basename $i)
    cp ${SOURCE}/$f js/
done
