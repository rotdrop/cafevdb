# PME select component

`src/components/PmeSelect.vue` is a standalone Vue replacement for the Selectize
features used by PME fields. It uses the existing Nextcloud select component,
not Selectize or jQuery. Legacy mounting, PHP attribute parsing, and
`select-utils.ts` integration are deliberately not implemented yet; existing
pages continue to use Selectize.

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
`clear(silent = false)`, `moveItem(index, offset)`, `createItem(input)`,
`focus()`, `lock()`, `unlock()`, `disable()`, and `enable()`.
`isLocked`, `isDisabled`, and `creating` expose current state.
Programmatic setters can replace values even while interaction is locked.
Unlocking/enabling does not override a corresponding prop.

An optional `name` renders hidden form inputs in selection order. Pass the exact
field name, including `[]` for PHP array fields. Disabled fields are omitted from
submission; readonly/locked fields retain their submitted values. Do not supply
`name` if a future bridge already synchronizes an existing native select.
`required` uses native browser validation independently of `notEmpty`.

The future bridge must map the PHP configuration to these props, synchronize
the original select and its change events, connect the shared select utilities,
and register instances for cleanup on legacy DOM replacement. Email-composer
and SEPA-specific features, a Selectize-compatible instance facade, and arbitrary
Selectize plugins are outside this component's current scope.
