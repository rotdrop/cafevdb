# PME select component

`src/components/PmeSelect.vue` is a Vue replacement for the Selectize features
used by PME fields. It uses the existing Nextcloud select component, not
Selectize or jQuery. The `mountSelectize` service adapts native selects to this
SFC through the mountable-component event bus. PME's initializer now mounts
`.selectize` fields through the service and destroys them when replacing the
PME container. The shared `select-utils.ts` functions dispatch to Vue-backed
controls as well as native, Chosen, and jQuery Selectize controls. Other
legacy Selectize consumers remain unchanged.

```ts
import { mountSelectize } from '../services/mountable-select.ts'

// Mount all `.selectize` selects below a legacy PME container.
const controls = await mountSelectize(document.querySelector('#pme-container')!)
controls[0]?.setValue(['1', '2'])
```

`mountSelectize(target, options?)` accepts an `HTMLElement` (a select or a
container) or a CSS selector. A selector may identify selects directly or
containers whose descendant selects have the `selectize` class. Options parsed
from `data-selectize-options` are overridden by the optional second argument.
The function returns a promise of service instances. It does not import or call
jQuery.

The adapter hides but preserves the original `<select>`, mirrors Vue selection
changes to its options, and dispatches native bubbling `change` events so
existing PME listeners continue to work. It removes the original select's
`name` while mounted to avoid duplicates and owns ordered hidden inputs for PHP
form submission, updating them synchronously before dispatching `change` so
legacy event handlers see the current ordered values. `refresh()` updates Vue
options from the native element;
native `change` events made elsewhere update the Vue selection. Instances are
unmounted and the native selects restored on `LEGACY_PAGE_CLEANUP`.

The returned service exposes `getValue()`, `setValue(value, silent?)`,
`clear(silent?)`, `refresh()`, `refreshProperties()`, `focus()`, `lock()`,
`unlock()`, `disable()`, `enable()`, `isLocked`, `isDisabled`, the visible host
`element`, and `destroy()`. Single-select values are
strings or `null`; multi-select values are ordered arrays of strings. Repeated
calls are idempotent while an instance remains mounted.

Legacy callers can continue using `selected`, `deselectAll`, option lookup,
`replaceOptions`, `refreshWidget`, `refreshWidgetProperties`, `locked`, and
widget lookup from `select-utils.ts`. Ordered values and the existing silent
setter / explicit change-event behavior are preserved. Changes to native
options require `refreshWidget`; changes only to disabled/readonly properties
can use `refreshWidgetProperties`. The readonly shim leaves form-input
management to the Vue adapter, avoiding duplicate placeholder inputs.

`getControlObject` returns the Vue service for Vue-backed selects.
`vueSelectActive` detects those controls; `getSelectize`, `selectizeActive`, and
`isSelectizedJQuery` continue to describe only genuine jQuery Selectize instances.
Code using Selectize-specific internals must not use them for Vue controls.

```vue
<PmeSelect v-model="instruments"
           label="Instruments"
           :options="[
             { value: '1', label: 'Violin', groups: ['strings'] },
             { value: '2', label: 'Viola', groups: ['strings'] },
           ]"
           :groups="[{ value: 'strings', label: 'Strings' }]"
           multiple
           sortable
/>
```

Types are exported from `src/types/pme-select.ts`. Values are strings; `v-model`
is a string or `null` for single selects and an ordered string array for multiple
selects. External value and option changes are reactive. Selected values remain
visible if an option is temporarily absent.
Searching is case- and diacritic-insensitive and matches all query words.

| PME / Selectize configuration | Component API |
| --- | --- |
| HTML options and optgroups | `options: { value, label, groups?, disabled? }[]` and `groups: { value, label, disabled? }[]` |
| Initial-values data attribute | `modelValue` / `v-model`, preserving selection order |
| `drag_drop` | `sortable`, with drag-and-drop and keyboard-accessible move buttons |
| `remove_button`, `clear_button` | Multiple-selection remove buttons; single-selection clear button |
| `not-empty` class | `notEmpty`, preventing removal of the final selection |
| `lockOptgroupOrder: true` | Groups always retain their declared order |
| `create: false` / `true` | `create: false` (default) / `true` for local creation |
| `create: { url, post, inputField }` | Same object in `create`; also put `valueField` and `labelField` inside it |
| `createOnBlur`, `persist` | Corresponding boolean props; both default to `false` |
| `hideSelected: false` | Selected options remain visible |

Dropdown opening follows Nextcloud's accessible keyboard/pointer interactions
rather than forcing Selectize's `openOnFocus` behavior. Use Arrow Down, Enter,
or a pointer click to open it.

For the validated email fields, for example:

```ts
const create = {
  url: 'validate/musicians/email',
  post: { failure: 'error' },
  inputField: 'PME_data_email', // Use the actual CGI field name.
  valueField: 'email',
  labelField: 'email',
};
```

The endpoint receives a form-encoded POST through Nextcloud's authenticated
Axios client. Only a response containing nonempty string value and label fields
is accepted. Defaults are `input`, `value`, and `text`, respectively. Failed
requests and malformed responses leave the selection unchanged, display an
inline error, and emit `error`. Concurrent creation requests are prevented;
pending requests are cancelled when the value is replaced, the control is
locked/disabled, its creation configuration changes, or the component unmounts.
The search input blocks native form submission while validation is pending or
an unresolved creation error is present.
New entries can be selected in the dropdown, committed with a comma, or created
on blur when `createOnBlur` is enabled.

`change` and `update:modelValue` report selection changes, including reordering.
`open` and `close` are available for legacy dropdown-visibility handling.
The exposed methods are `getValue()`, `setValue(value, silent = false)`,
`getOptions()`, `setOptions(options, groups?)`, `clear(silent = false)`,
`moveItem(index, offset)`, `createItem(input)`,
`focus()`, `lock()`, `unlock()`, `disable()`, and `enable()`.
`isLocked`, `isDisabled`, and `creating` expose current state.
Programmatic setters can replace values even while interaction is locked.
Unlocking/enabling does not override a corresponding prop.

In standalone use, an optional `name` renders hidden form inputs in selection
order. The mount service does not pass `name` to the component because it
manages the original select's field name and ordered hidden inputs itself.
Disabled fields are omitted from submission; readonly/locked fields retain
their submitted values. `required` uses native browser validation independently
of `notEmpty`.

Email-composer and SEPA-specific features, a fully compatible Selectize instance
facade, and arbitrary Selectize plugins are outside the current scope.
