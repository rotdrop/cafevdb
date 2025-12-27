<!--
 - @copyright Copyright (c) 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
 -
 - @author Claus-Justus Heine <himself@claus-justus-heine.de>
 -
 - @license AGPL-3.0-or-later
 -
 - This program is free software: you can redistribute it and/or modify
 - it under the terms of the GNU Affero General Public License as
 - published by the Free Software Foundation, either version 3 of the
 - License, or (at your option) any later version.
 -
 - This program is distributed in the hope that it will be useful,
 - but WITHOUT ANY WARRANTY; without even the implied warranty of
 - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 - GNU Affero General Public License for more detail.s
 -
 - You should have received a copy of the GNU Affero General Public License
 - along with this program. If not, see <http://www.gnu.org/licenses/>.
 -
 -->
<template>
  <!-- eslint-disable vue/no-v-text-v-html-on-component, vue/no-v-html -->
  <component :is="'script'"
             id="cloudFileSystemOperations"
             ref="outer"
             type="text/html"
             v-html="template"
  />
</template>
<script lang="ts" setup>
import { appName } from '../../config.ts'
import { translate as t } from '@nextcloud/l10n'
import { computed } from 'vue'
import useTooltipsStore from '../../stores/tooltips.ts'
import { EnumFileUploadMode } from '../../../build/ts-types/php-modules/Controller.ts'
import type { TemplateFileUploadMode } from './oc-template-parameters.d.ts'

const tooltipsProvider = useTooltipsStore()
tooltipsProvider.provideTooltips(Object.values(EnumFileUploadMode).map(mode => 'cloud-file-system-operations:' + mode))
const hints = tooltipsProvider.tooltipsData

const modeData = computed<Record<TemplateFileUploadMode, { name: string, hint: string }> >(() => {
  console.info('UPDATE MODEDATE', { hints, currentHints: { ...hints } })
  const result = {}
  for (const mode of Object.values(EnumFileUploadMode).filter(value => value !== EnumFileUploadMode.TEST)) {
    result[mode] = { name: t(appName, mode), hint: hints[`cloud-file-system-operations:${mode}`] }
  }
  return result
})

const inputHtml = (mode: string, modeData: { name: string, hint: string }) => {
  console.info('CALL INPUT HTML', { mode, modeData: { ...modeData } })
  return `<input id = "{widgetCssClass}-${mode}-control"
       type="radio"
       class="radio {widgetCssClass} {widgetCssClass}-input {${mode}CssClass}"
       value="${mode}"
       name="{widgetRadioName}"
       {${mode}Disabled}
       {${mode}Selected}
/>
<label for="{widgetCssClass}-${mode}-control"
       class="{widgetCssClass} {widgetCssClass}-label tooltip-auto"
       title="${modeData.hint}"
>
  ${modeData.name}
</label>`
}

const template = computed<string>(() => {
  let result = `
<div ref="inner" class="{widgetCssClass}-wrapper {widgetCssClass} {operations}">
  <div class="{widgetCssClass} {widgetCssClass}-file-list font-monospace">
    {files}
  </div>
  <div class="{widgetCssClass} {widgetCssClass}-controls">`
  for (const [mode, data] of Object.entries(modeData.value)) {
    result += inputHtml(mode, data)
  }
  result += `
  </div>
</div>`
  console.info('UPDATED TEMPLATE', { result })
  return result
})

// const inner = ref<null|HTMLElement>(null)
// const outer = ref<null|HTMLScriptElement>(null)

// watch(modeData, async () => {
//   await nextTick()
//   if (outer.value && inner.value) {
//     outer.value.innerHTML = inner.value.outerHTML
//   }
//   console.info('INNER OUTER WATCHER', { outerInner: outer.value?.innerHTML, innerOuter: inner.value?.outerHTML })
// })

// onMounted(() => {
//   // outer.value!.innerHTML = inner.value!.outerHTML
// })
</script>
