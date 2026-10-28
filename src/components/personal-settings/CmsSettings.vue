<template>
  <AdminSettingsTab :entry="entry" :groups="groups" @error="emit('error', $event)" />
</template>

<script setup lang="ts">
import type { AdminSettingField, AdminSettingGroup, AdminSettingOption } from './AdminSettingsTab.vue'
import type { TabEntry } from './settings-tree.ts'

import { translate as t } from '@nextcloud/l10n'
import AdminSettingsTab from './AdminSettingsTab.vue'
import {
  CMS_CATEGORIES,
  CMS_MODULES,
  CMS_PREFIX,
  CMS_TEMPLATES,
} from '../../../build/ts-types/php-modules/Settings/ConfigConstants.ts'
import { appName as appId } from '../../config.ts'
import getInitialState from '../../toolkit/util/initial-state.ts'

const props = defineProps<{ entry: TabEntry }>()
const emit = defineEmits<{ error: [error: Error] }>()
const state = getInitialState<{
  webPageCategories?: Array<{ id: number|string, name: string, level?: number }>
  webPageTemplates?: Array<{ id: number|string, name: string }>
  webPageModules?: Array<{ id: number|string, name: string }>
}>({ section: 'adminSettings', defaults: {} })
const keyFor = (slug: string) => CMS_PREFIX + slug
  .split('-')
  .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
  .join('')
const field = (key: string, label: string): AdminSettingField => ({
  key,
  label: t(appId, label),
  type: 'text',
})
const options = (items: Array<{ id: number|string, name: string, level?: number }> = []): AdminSettingOption[] =>
  items.map(({ id, name, level = 0 }) => ({
    value: id,
    label: `${'   '.repeat(level)}${name} [${id}]`,
  }))
const group = (index: number, fallback: string, fields: AdminSettingField[]): AdminSettingGroup => ({
  id: props.entry.children[index]?.id ?? fallback,
  name: props.entry.children[index]?.name ?? t(appId, fallback),
  fields,
})

const groups = [
  group(0, 'Redaxo Categories', CMS_CATEGORIES.map((slug) => ({
    ...field(
      keyFor(slug),
      `Id of Redaxo ${slug.charAt(0).toUpperCase() + slug.slice(1)} Category`,
    ),
    ...(state?.webPageCategories?.length
      ? { type: 'select' as const, options: options(state.webPageCategories) }
      : {}),
  }))),
  group(1, 'Redaxo Templates', CMS_TEMPLATES.map((slug) => ({
    ...field(keyFor(slug) + 'Template', `Id of Redaxo ${slug} Template`),
    ...(state?.webPageTemplates?.length
      ? { type: 'select' as const, options: options(state.webPageTemplates) }
      : {}),
  }))),
  group(2, 'Redaxo Modules', CMS_MODULES.map((slug) => ({
    ...field(
      keyFor(slug) + 'Module',
      `Id of Redaxo ${slug.charAt(0).toUpperCase() + slug.slice(1)} Module`,
    ),
    ...(state?.webPageModules?.length
      ? { type: 'select' as const, options: options(state.webPageModules) }
      : {}),
  }))),
]
</script>
