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
      <template v-for="setting in checkboxSettings" :key="setting.id">
        <div v-if="setting.key !== 'showDisabled' || settings.expertMode">
          <input :id="setting.id"
                 type="checkbox"
                 :class="['checkbox', setting.class]"
                 :checked="settings[setting.key]"
                 :disabled="saving[setting.key]"
                 @change="saveSetting(setting, $event)"
          />
          <label :for="setting.id">{{ t(appId, setting.label) }}</label>
        </div>
      </template>
    </div>
    <div :id="entry.children[1]?.id" class="settings-subsection">
      <h3>{{ entry.children[1]?.name }}</h3>
    </div>
  </NcSettingsSection>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import { NcSettingsSection } from '@nextcloud/vue'
import { reactive } from 'vue'
import { appName as appId } from '../../config.ts'
import type { PersonalSettingKey } from '../../services/personal-settings.ts'
import { setPersonalSetting } from '../../services/personal-settings.ts'
import getInitialState from '../../toolkit/util/initial-state.ts'
import type { TabEntry } from './settings-tree.ts'

type CheckboxSetting = {
  id: string,
  class: string,
  key: PersonalSettingKey,
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

const settings = reactive(Object.fromEntries(
  checkboxSettings.map(({ key }) => [key, isEnabled(initialState?.[key])]),
) as Record<CheckboxSetting['key'], boolean>)
const saving = reactive(Object.fromEntries(
  checkboxSettings.map(({ key }) => [key, false]),
) as Record<CheckboxSetting['key'], boolean>)

const emit = defineEmits<{
  error: [error: Error],
}>()

const saveSetting = async ({ key }: CheckboxSetting, event: Event) => {
  const control = event.currentTarget
  if (!(control instanceof HTMLInputElement)) {
    emit('error', new Error('Personal setting control is not an input element.'))
    return
  }
  const previousValue = settings[key]
  settings[key] = control.checked
  saving[key] = true
  try {
    await setPersonalSetting(key, settings[key])
  } catch (error) {
    settings[key] = previousValue
    emit('error', error instanceof Error ? error : new Error(String(error)))
  } finally {
    saving[key] = false
  }
}

defineProps<{ entry: TabEntry }>()
</script>
