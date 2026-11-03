# Ordered select adapter

The former `PmeSelect.vue` and `ChosenSelect.vue` implementations are merged
into [`VueSelect.vue`](vue-select.md).

`mountSelectize` now mounts that shared component, preserving selection order,
sorting, asynchronous creation, and ordered form submission. The native marker
is `cafevdb-vue-select--ordered`, and configuration is read from
`data-vue-select-options`. CSS uses `#{$appName}-vue-select` hooks instead of
`selectize-*` or `pme-select`.

See the [unified select documentation](vue-select.md) for props, mounting,
creation, cleanup, and integration details.
