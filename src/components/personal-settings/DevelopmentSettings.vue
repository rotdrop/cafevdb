<template>
  <AdminSettingsTab :entry="entry" :groups="groups" @error="emit('error', $event)" />
</template>

<script setup lang="ts">
import type { AdminSettingField, AdminSettingGroup } from './AdminSettingsTab.vue'
import type { TabEntry } from './settings-tree.ts'

import { translate as t } from '@nextcloud/l10n'
import AdminSettingsTab from './AdminSettingsTab.vue'
import { appName as appId } from '../../config.ts'

const props = defineProps<{ entry: TabEntry }>()
const emit = defineEmits<{ error: [error: Error] }>()
const field = (key: string, label: string): AdminSettingField => ({
  key,
  label: t(appId, label),
  type: 'text',
})
const groups: AdminSettingGroup[] = [{
  id: props.entry.children[0]?.id ?? 'links',
  name: props.entry.children[0]?.name ?? t(appId, 'Links'),
  fields: [
    field('phpmyadmin', `Link to the database ${appId}@phpMyAdmin`),
    field('phpmyadmincloud', 'Link to the database Cloud@phpMyAdmin'),
    field('sourcecode', 'Link to the source-code'),
    field('sourcedocs', 'Link to the source-code documentation'),
    field('clouddev', 'Link to cloud Developer Information'),
    field('cspfailurereporting', 'Link for uploading CSP failure information'),
  ],
}]
</script>
