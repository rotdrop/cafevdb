# Unified Vue select

`src/components/VueSelect.vue` replaces both `PmeSelect.vue` and
`ChosenSelect.vue`. It wraps Nextcloud's `NcSelect`; neither legacy widget is
required. The mountable-component provider registers it once as `VUE_SELECT`.
Shared types live in `src/types/vue-select.ts`.

```vue
<VueSelect v-model="instruments"
           label="Instruments"
           :options="[
             { value: '1', label: 'Violin', groups: ['strings'] },
             { value: '2', label: 'Viola', groups: ['strings'] },
           ]"
           :groups="[{ value: 'strings', label: 'Strings' }]"
           multiple
           sortable
           dropup="auto"
/>
```

Values are strings, ordered string arrays for multiple selects, or `null`.
Options support `value`, `label`, `groups`, `disabled`, and plain-text `title`.
Groups support `value`, `label`, and `disabled`. Search is case- and
diacritic-insensitive and matches every query word. Group headings cannot be
selected; an option may belong to multiple groups.

## Features and behavior

The default behavior preserves selection order, retains temporarily missing
values, searches labels and values, leaves selected options visible, permits
single deselection, and removes the last multiple selection on Backspace.
Native-select behavior is available through the same component, not a second
implementation:

| Prop | Behavior |
| --- | --- |
| `nativeOrder` | Order values by the options instead of selection order; `sortable` takes precedence |
| `hideSelected` | Hide already selected multiple choices |
| `blankPlaceholder` | Treat a blank single option as a placeholder rather than a dropdown choice |
| `retainMissingValues` | Keep unavailable selected values; defaults to `true` |
| `searchValues` | Search values as well as labels; defaults to `true` |
| `allowSingleDeselect` | Allow clearing single selections; defaults to `true` |
| `removeOnBackspace` | Permit empty-search Backspace removal; defaults to `true` |
| `restoreOnBackspace` | Restore the removed option's label to the search input |
| `disableSearch`, `disableSearchThreshold` | Disable search always, or below a single-select option-count threshold |
| `noResultsText` | Plain-text no-results message, followed by the search text |
| `labelOutside`, `multiplePlaceholder` | Show an outside label / a multiple-select placeholder; both default to `true` |
| `notEmpty` | Prevent user removal of the final selection, independently of `required` |
| `sortable` | Drag-and-drop ordering and accessible move buttons |
| `showMoveButtons` | `true` (default), `false`, or `'focus'`; hidden focus-only buttons remain keyboard reachable |
| `closeAfterSelect` | Close a multiple-select dropdown after selection |
| `dropup` | `false` (default), `true`, or `'auto'` |

Auto direction prefers down when the menu fits below, otherwise choosing up
only when there is more space above. It respects the viewport and clipping
ancestors, recalculates on filtering, scrolling, resizing, and layout changes,
and removes observers/listeners on close or unmount.

`required` validates the visible input, including when searching is disabled.
Typing search text is not a selection. `disabled`, `readonly`, and `locked`
block editing without discarding values. Optional `name` renders hidden form
inputs in selection order; disabled fields are excluded from submission,
whereas readonly/locked fields remain successful controls.

`create: true` enables local creation. An object accepts `url`, `post`,
`inputField`, `valueField`, and `labelField` for authenticated form-encoded
validation requests, or `resolve(input, signal)` for custom asynchronous
creation. The default field names are `input`, `value`, and `text`.
Only nonempty string values and labels are accepted. `createOnBlur` enables
blur creation; commas and the dropdown creation entry can also commit input.
`persist` retains created options after deselection.

Creation errors preserve selection, display an inline message, emit `error`,
and block invalid form submission. Concurrent creation is prevented. Pending
work is cancelled on value/state replacement, configuration changes,
locking/disabling, or unmount.

`change` and `update:modelValue` report changes, including sorting. `create`
fires before the associated selection change. `open` and `close` report
dropdown visibility. External updates do not echo change events.

Exposed controls are `getValue`, `setValue(value, silent = false)`, `getOptions`,
`setOptions(options, groups?)`, `setState(props)`, `setRequired`, `clear`,
`moveItem`, `createItem`, `focus`, `open`, `close`, `lock`, `unlock`, `disable`,
and `enable`, plus `isLocked`, `isDisabled`, and `creating`.
`setState` silently refreshes native-adapter props and selection and cancels
pending creation. Programmatic setters remain usable while interaction is
locked.

## Legacy mounting and CSS

Both adapters request the same `VUE_SELECT` component. They retain their
existing public entry points so callers can preserve native versus ordered
form-submission semantics:

| Entry point | Native marker | Form submission and defaults |
| --- | --- | --- |
| `mountSelectize` in `services/mountable-select.ts` | `cafevdb-vue-select--ordered` | Ordered hidden inputs; selection order, creation, and sorting configuration |
| `mountChosen` in `services/mountable-chosen.ts`, or `.chosen()` | `cafevdb-vue-select` | Original native select; native option order, hidden selected choices, no Backspace removal |

Both accept a select, a container, or a selector and expose value, refresh,
required, readonly/disabled, focus, and destruction controls. The Vue
mountable-component provider must already be registered. Native `change`
listeners see current form values synchronously. `select-utils.ts` works with
either adapter. Cleanup restores the native control.

The ordered adapter reads `data-vue-select-options` (explicit mount options
override it) and `data-initial-values`. Existing `drag_drop` plugin configuration
enables sorting. `mountedSelectScope` and `destroyMountedSelects` manage panel
lifetimes and pending mounts. `address-book-select.ts` supplies validated
free-form contacts without exposing widget internals.

The native adapter retains the `.chosen()` API and `chosen:*` events as
compatibility entry points, including `updated`, `open`, `activate`, and
`close`. It delegates validation to the visible input, handles form resets,
and destroys disconnected controls. Supported options remain declared in
`src/app/jquery-types.d.ts`; legacy tooltip/private widget markup is not
recreated.

All rendering and styling hooks now use the app prefix:
`cafevdb-vue-select` for the root, `cafevdb-vue-select-control` for the host, and
`cafevdb-vue-select__*` for component elements. SCSS uses
`#{$appName}-vue-select`, not a hardcoded prefix. Hosts use the ID
`<select-id>-vue-select-control` where an ID is supplied by the native adapter.
The old `chosen-container`, `selectize-control`, `pme-select`, and
`*-vue-select-control` variant classes are no longer emitted.

Native configuration classes are `cafevdb-vue-select--up` (force up),
`cafevdb-vue-select--auto` (automatic direction), and
`cafevdb-vue-select--width-auto` (native-adapter automatic width).
Explicit native-adapter `dropup` options override direction classes; up wins
over auto when both are present. `no-vue-select` excludes a field from PME's
automatic native-adapter initialization. The effective component direction
uses `cafevdb-vue-select--dropup`.

PME comparison filters style `.pme-filter-controls` for both a native select
and its mounted `cafevdb-vue-select-control` host. The comparison control is
fixed at 4.5em and 40px high beside a flexible text input; a single shared border
highlights on hover or focus. Multi-select filter hosts fill their flex cell,
stay at a 40px minimum when empty or on one line, and grow naturally when
selected values wrap. They retain Nextcloud's default clickable-area sizing.

PME navigation and export actions remain separate components:
[`PmeNavigationSelect`](pme-navigation-select.md) and
[`PmeExportMenu`](pme-export-menu.md).
