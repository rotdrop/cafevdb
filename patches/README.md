# Patches

## @nextcloud/app-calendar

After installing the npm dependencies, regenerate the patch manually with:

``` shell
make fabricate-app-calendar-patch
# Or, from any working directory:
/path/to/cafevdb/dev-scripts/fabricate-app-calendar-patch.sh
```

The script applies the changes below to the installed package and invokes
the local `patch-package` executable to write its versioned patch in `patches/`.
Only files under the package's `src/` directory are included; test directories
(`test`, `tests`, `__tests__`) and `*.test.*` / `*.spec.*` files are excluded,
including any pre-existing test modifications in the installed package.
Alias replacements climb from the importing file's directory to the package
root, then descend through `src/`, matching the 6.6.2 patch: for example,
`src/appointments/main-booking.js` imports `../../src/views/Appointments/Booking.vue`,
and files directly under `src/` use `../src/`. The filename itself must not
count as another directory level.
It can be run again on an already transformed package, also correcting existing
`../.../src/` imports with an incorrect number of parent-directory segments.
Run the generator regression tests with
`node --test tests/scripts/fabricate-app-calendar-patch.test.mjs`.
The script invokes
`patch-package` with an empty temporary `--patch-dir` under `build/`, using
a relative path as required by `patch-package`, so existing patches
cannot be applied to the clean package used for comparison. After successful
generation, the new patch is moved into `patches/` and obsolete
`@nextcloud+app-calendar+*.patch` files are removed. If generation fails or
does not produce exactly one Calendar patch, existing patches remain unchanged.
Patches for other packages are left untouched. The script creates a local Git
commit containing only the generated Calendar patch and obsolete Calendar
patch deletions, leaving unrelated staged changes out of that commit.
An unchanged patch does not create another commit. Git commit failures are
reported; the generated patch remains available for inspection and retry.
Review the generated commit before pushing.

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
  unmodified inclusion. Remove the SVG's `atob()` decoding and use the raw
  import directly; retain unrelated `btoa()` calls such as the CSP nonce.

``` diff
diff --git a/node_modules/@nextcloud/app-calendar/src/contactsMenu.js b/node_modules/@nextcloud/app-calendar/src/contactsMenu.js
index 8a8301b..f2a237f 100644
--- a/node_modules/@nextcloud/app-calendar/src/contactsMenu.js
+++ b/node_modules/@nextcloud/app-calendar/src/contactsMenu.js
@@ -3,14 +3,14 @@
  * SPDX-License-Identifier: AGPL-3.0-or-later
  */
 
-import CalendarBlankSvg from '@mdi/svg/svg/calendar-blank.svg'
+import CalendarBlankSvg from '@mdi/svg/svg/calendar-blank.svg?raw'
 import { getRequestToken } from '@nextcloud/auth'
 import { translate as t } from '@nextcloud/l10n'
 import { linkTo } from '@nextcloud/router'
 import { registerContactsMenuAction } from '@nextcloud/vue/functions/contactsMenu'
 
 import 'core-js/stable/index.js'
-import '@/styles/calendar.scss'
+import '../src/styles/calendar.scss'
 
 // CSP config for webpack dynamic chunk loading
 
```

- the EditFull component needs an outer &lt;div&gt;...&lt;/div&gt;
  wrapper because of the v-if in the outer-most NcModal
  component. Other there will be rendering error when Vue does not
  find and parent for the comment node which replaces the NcModal if
  v-if evaluates to false.

``` diff
diff --git a/node_modules/@nextcloud/app-calendar/src/views/EditFull.vue b/node_modules/@nextcloud/app-calendar/src/views/EditFull.vue
index 192cfda..639c413 100644
--- a/node_modules/@nextcloud/app-calendar/src/views/EditFull.vue
+++ b/node_modules/@nextcloud/app-calendar/src/views/EditFull.vue
@@ -3,7 +3,7 @@
   - SPDX-License-Identifier: AGPL-3.0-or-later
 -->
 
-<template>
+<template><div>
 	<NcModal
 		v-if="calendarObjectInstance"
 		v-model="showFullModal"
@@ -352,7 +352,7 @@
 			:buttons="cancelButtons"
 			@update:open="showCancelDialog = $event" />
 	</NcModal>
-</template>
+</div></template>
 
 <script>
 import IconCancel from '@mdi/svg/svg/cancel.svg?raw'

```
