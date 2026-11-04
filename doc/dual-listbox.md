# Email recipients dual list

`src/components/DualListbox.vue` replaces Bootstrap Dual Listbox in the email
recipients tab. It is registered as `DUAL_LISTBOX` with the mountable-component
provider.

The two lists show remaining and selected recipients. Choosing names moves them
immediately to the other list (keyboard and multiple selection are supported).
Each list has an independent, case-insensitive text filter and a clear button.
Filters use the shared `TextFieldWithTrailingButton` with its `clear` variant
and a leading search icon.
Each pane's preferred width uses the longest recipient label across both lists,
with a minimum of `40ch`. Moving recipients does not change this width; panes
still shrink and wrap on narrow screens.
Select all / Remove all move only the visible, enabled options. Filtered counts
and empty-list messages use the existing translation strings.

`DualListboxService.mount(select).ready` mounts the component beside a native
multiple select. The native select remains authoritative for form serialization;
each move updates its selected options before dispatching one bubbling `change`
event, preserving the email form's recipient-history snapshots.

Call `getMountedDualListbox(select)?.refresh()` after changing native selection
properties programmatically. Native changes and DOM mutations (including AJAX
option replacement, disabled and readonly attributes) are also observed.
Filters survive refreshes. Readonly locks the UI without removing submitted
values. The email form's readonly helper recognizes the adapter without
generating placeholder options.

`destroyMountedSelects(container)` tears down the adapter before replacing email
panels. Detached controls and legacy-page cleanup also destroy instances.
Mount failures restore the native control and are reported by the email form.
The component deliberately does not implement the unused Bootstrap plugin API.
