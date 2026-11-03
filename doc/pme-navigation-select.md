# PME navigation select

`src/components/PmeNavigationSelect.vue` replaces each legacy PME navigation
combo of a caption button plus select (`Go to`, `Rows/Page`) by a single
button-like select. It is separate from `ChosenSelect.vue` and `PmeSelect.vue`.

The trigger is at most 28px tall, uses the button background
`var(--color-primary-element-light)`, and shows the caption followed by the
current value, e.g. **Go to** 3. Its width fits that text. The menu is
appended to the body and opens downward, flipping upward when there is not
enough room below.

## Legacy integration

`pmeInit()` mounts the controls when Chosen-style selects are enabled:

```ts
import { mountPmeNavigation, refreshPmeNavigation } from '../services/mountable-pme-navigation.ts';

await mountPmeNavigation(container, 'span.pme-goto > select.pme-goto', { searchThreshold: 10 });
await mountPmeNavigation(container, 'span.pme-pagerows > select.pme-pagerows');
```

The caption is read from the sibling submit button, which is hidden together
with the native select. The native select remains the source of truth: a
selection updates it and dispatches one bubbling `change`, so the existing PME
change handler submits the form. A disabled select cannot be changed; its
`data-placeholder` is shown when nothing is selected.

Call `refreshPmeNavigation(container)` after legacy code changes the caption
button or the select, as table transposition does for the rows caption.
Instances are destroyed on legacy-page cleanup or when their markup is removed;
the native controls are then visible again.

Search is enabled only when there are more options than `searchThreshold`.
Tooltip handling is not implemented.
