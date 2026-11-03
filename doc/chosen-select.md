# Mountable Chosen replacement

`src/components/ChosenSelect.vue` is independent of `PmeSelect.vue`. It uses
Nextcloud's `NcSelect`, not the Chosen jQuery widget. Its scope is the Chosen
features used in `src/app/*.ts`; it is not a general Chosen implementation.
Tooltip integration is deliberately omitted.

Numeric/date comparison filters use a `.pme-filter-controls` flex wrapper.
The comparison select stays compact while the adjacent text input fills the
remaining space. Both controls have a 40px height and no vertical margins;
the comparison toggle uses the normal input background and rounded corners.
The pair has no gap, square adjoining corners, and a shared outer boundary
that highlights on hover or focus of either control.
Multi-select filters place the negation checkbox in a separate, vertically
centered button after the select, leaving its floating label unobstructed.
Their empty and single-line controls are 40px high and grow when selections wrap.

PME's goto and rows-per-page controls do not use it; they use the button-like
[`PmeNavigationSelect`](pme-navigation-select.md) instead.
The export control is a separate [`PmeExportMenu`](pme-export-menu.md)
action menu using `NcActions`, not a select widget.

The component is registered as `CHOSEN_SELECT` in the mountable-component
provider. Its typed props and exposed controls are in
`src/types/chosen-select.ts`. It supports single and multiple selection,
search, disabled options and optgroups, optional single deselection,
placeholders, required validation, readonly/disabled states, and upward-opening
dropdowns. Option labels and no-results messages are rendered as text.

## Legacy integration

`globals.ts` loads `jquery-chosen.ts` instead of Chosen's JavaScript and CSS.
The small compatibility facade preserves the legacy calls:

```typescript
$select.chosen({
  width: '100%',
  inherit_select_classes: true,
  disable_search_threshold: 10,
  allow_single_deselect: true,
});
$select.trigger('chosen:updated');
$select.trigger('chosen:open');
$select.chosen('destroy');
```

The facade accepts `width`, `inherit_select_classes`, `disable_search`,
`disable_search_threshold`, `allow_single_deselect`, and `no_results_text`.
Backspace does not remove selections when the search is empty, matching the
legacy initializers' `single_backstroke_delete: false` behavior.
`title_attributes` is accepted but ignored along with tooltip handling.

### Dropdown direction

Direction is downward by default. The existing `chosen-dropup` class still
forces upward opening. Opt into space-aware positioning with the `chosen-auto`
class, or pass `dropup: 'auto'` to `.chosen()` or `mountChosen()`. Explicit
`dropup: true` and `dropup: false` force up and down respectively. An explicit
option overrides the classes; `chosen-dropup` takes precedence over
`chosen-auto` when both classes are present.

The standalone Vue component accepts the same `dropup: boolean | 'auto'` prop.
Auto mode prefers down whenever the menu fits below. Otherwise it opens up
only when there is more room above; if neither direction fits, it uses the side
with more room (down on a tie). Available space is bounded by the viewport and
clipping/scrolling ancestors. Direction is recalculated when opening, filtering,
resizing, scrolling, or changing the menu's size, with listeners removed on
close or unmount. Borders and chevrons follow the effective direction.

The bridge emits `chosen:ready`, `chosen:update`,
`chosen:before_showing_dropdown`, `chosen:showing_dropdown`, and
`chosen:hiding_dropdown`. It handles `chosen:updated`, `chosen:open`,
`chosen:activate`, and `chosen:close`. It does not expose Chosen's private
search-results DOM or tooltip event payloads.

`ChosenService` keeps the original select's name, options, selection, and
disabled state authoritative for form submission. UI changes update that select
before emitting a bubbling native `change` event. Native `change` and
`chosen:updated` refresh the Vue control without generating another change.
Required validation is delegated to the visible input while mounted; readonly
controls remain successful form fields, unlike disabled controls. Native form
resets also reset the visible selection.

Existing `select-utils.ts` functions work with both mounted select services.
The host retains the `.chosen-container` class and `<select-id>_chosen` ID for
legacy layout selectors, but does not reproduce Chosen's internal markup.
Instances are destroyed by `.chosen('destroy')`, legacy-page cleanup, or removal
of a previously connected select or host. Destroying during asynchronous loading
prevents the component from mounting afterward. Loading failures restore the
native control and are reported by the legacy facade.

## Direct mounting without jQuery

```typescript
import { mountChosen } from './services/mountable-chosen.ts';

const [control] = await mountChosen(selectElement, {
  allow_single_deselect: true,
});
control.setValue('value', true); // silent native and visible update
control.refresh(); // after changing native options or properties
control.open();
control.destroy();
```

`mountChosen` accepts a native select, a container (matching `select.chosen`),
or a CSS selector. The Vue mountable-component provider must already be
registered. The service also exposes `clear`, `focus`, `close`, `setRequired`,
`lock`, `unlock`, `disable`, and `enable`. Values are strings, string arrays
for multiple selects, or `null` for no selection. Unlike sortable PME selects,
multiple values follow native option order.
