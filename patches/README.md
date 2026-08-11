# Patches

## @nextcloud/app-calendar

Already a patched version with some add-ons. In order to make it fit
for reusal in this app (we want to have the editor components) one has to
change some things:

- replace the path alias `@/` => `PACKAGE/src` by a relative path, e.g.:
``` shell
cd node_modules/@nextcloud/app-calendar/src
for i in $(grep -rFl "'@/"); do
  prefix="$(echo $i|sed  -E 's|[^/]+|..|g')/src/"
  sed -i "s|'@/|'$prefix|g" $i
done
```

- the app "uses" `raw` resource queries to include svg, however, it
  does not use the query string but just include a base64 inline uri
  and then tries to convert it back to svg with `atob()`. This fails
  as we do use the `raw` resource query in order to flag direct
  unmodified inclusion. Those btoa's have just to be removed.
