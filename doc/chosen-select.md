# Native select adapter

The former `ChosenSelect.vue` and `PmeSelect.vue` implementations are merged
into [`VueSelect.vue`](vue-select.md).

`mountChosen` and the `.chosen()` compatibility facade now mount that shared
component. They preserve native option ordering, form submission, reset
handling, and existing `chosen:*` events. CSS hooks use the configured app's
`vue-select` prefix instead of `chosen-*`.

See the [unified select documentation](vue-select.md) for props, direction
handling, CSS names, and integration details.
