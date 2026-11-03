# PME export action menu

`PmeExportMenu.vue` is a dedicated mountable `NcActions` menu, independent of the
Chosen and PME select components. The Vue component defines the Excel and CSV
actions and translates their labels and its persistent "Export Table" caption.
There is no backing select or legacy change handler.

`Navigation::tableExportButton()` generates a 28px `type="button"` placeholder.
`pme-export.ts` only mounts the Vue menu through `PmeExportService`, supplying the
placeholder's associated form. NcActions handles keyboard navigation, closing
and space-aware popover positioning.

On each action the component reads the current form into `FormData`, sets
`exportFormat` to `EXCEL` or `CSV`, and downloads from `page/pme/export` using the
shared `axiosFileDownload` helper based on `@nextcloud/axios`. This preserves
repeated field names, filters and record selections without serializing other
tables' forms. The helper handles response blobs and download filenames.

During download the menu is disabled and the application busy state is pushed;
it is always popped afterwards. Errors are logged and shown as notifications.
Repeated exports retain the same menu caption. Tooltip handling is omitted.

The mounting service destroys the component on legacy-page cleanup or markup
removal. Mount failures restore the button and report an error. The placeholder
does not perform exports before Vue mounts.
