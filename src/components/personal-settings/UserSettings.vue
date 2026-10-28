<!--
 - Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2011-2016, 2020-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 - GNU Affero General Public License for more details.
 -
 - You should have received a copy of the GNU Affero General Public License
 - along with this program. If not, see <http://www.gnu.org/licenses/>.
 -->
<!-- Ported from templates/settings/personal-settings.php. Sub-sections replace the legacy accordion/fieldsets. -->
<template>
  <NcSettingsSection :id="entry.id" class="user-settings" :name="entry.name">
    <div :id="entry.children[0]?.id" class="settings-subsection">
      <h3>{{ entry.children[0]?.name }}</h3>
      <div v-for="setting in checkboxSettings"
           :key="setting.id"
           :class="[
             setting.key === 'showDisabled' ? 'expert-mode-container' : '',
             setting.key === 'showDisabled' && !isEnabled(initialState?.expertMode) ? hiddenCssClass : '',
           ]"
      >
        <input :id="setting.id"
               type="checkbox"
               :class="['checkbox', setting.class]"
               :checked="isEnabled(initialState?.[setting.key])"
        />
        <label :for="setting.id">{{ t(appId, setting.label) }}</label>
      </div>
    </div>
    <div :id="entry.children[1]?.id" class="settings-subsection">
      <h3>{{ entry.children[1]?.name }}</h3>
    </div>
  </NcSettingsSection>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import { NcSettingsSection } from '@nextcloud/vue'
import { appName as appId } from '../../config.ts'
import getInitialState from '../../toolkit/util/initial-state.ts'
import type { TabEntry } from './settings-tree.ts'
import { hiddenCssClass } from 'variables.module.scss'

type CheckboxSetting = {
  id: string,
  class: string,
  key:
    | 'toolTipsEnabled'
    | 'restoreHistory'
    | 'initialFilterVisibility'
    | 'directChange'
    | 'deselectInvisibleMiscRecs'
    | 'financeMode'
    | 'expertMode'
    | 'showDisabled',
  label: string,
}

const initialState = getInitialState<Partial<Record<CheckboxSetting['key'], boolean|string>> & {
  financeModeAllowed?: boolean,
}>({ section: 'CAFEVDB', defaults: {} })

const checkboxSettings: CheckboxSetting[] = [
  { id: 'tooltips', class: 'tooltips', key: 'toolTipsEnabled', label: 'Tool-Tips' },
  { id: 'restorehistory', class: 'restorehistory', key: 'restoreHistory', label: 'Restore Last View' },
  { id: 'filtervisibility', class: 'filtervisibility', key: 'initialFilterVisibility', label: 'Filter-Controls' },
  { id: 'directchange', class: 'directchange', key: 'directChange', label: 'Quick Change-Dialog' },
  { id: 'deselect-invisible-misc-recs', class: 'deselect-invisible-misc-recs', key: 'deselectInvisibleMiscRecs', label: 'Deselect Invisible' },
  ...(initialState?.financeModeAllowed
    ? [{
        id: 'finance-mode',
        class: 'finance-mode',
        key: 'financeMode',
        label: 'Finance-Mode',
      } as const]
    : []),
  { id: 'expert-mode', class: 'expert-mode', key: 'expertMode', label: 'Expert-Mode' },
  { id: 'showdisabled', class: 'showdisabled', key: 'showDisabled', label: 'Show Disabled Data-Sets' },
]

const isEnabled = (value: boolean|string|undefined) => value === true || value === 'on'

defineProps<{ entry: TabEntry }>()
</script>
