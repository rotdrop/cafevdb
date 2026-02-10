# Patches

## @nextcloud/app-calendar

Already a patched version with some add-ons. In order to make it fit
for reusal in this (we want to have the editor components) one has to
replace the path alias `@/` => `PACKAGE/src` by a relative path, e.g.:

``` shell
cd node_modules/@nextcloud/app-calendar/src
for i in $(grep -rFl "'@/"); do
  prefix="$(echo $i|sed  -E 's|[^/]+|..|g')/src/"
  sed -i "s|'@/|'$prefix|g" $i
done
```
