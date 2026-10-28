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
      <NcCheckboxRadioSwitch id="tooltips"
                             v-tooltip="hints['show-tool-tips']"
                             :model-value="settings.toolTipsEnabled"
                             :disabled="saving.toolTipsEnabled"
                             @update:model-value="saveSetting('toolTipsEnabled', $event)"
      >
        {{ t(appId, 'Tool-Tips') }}
      </NcCheckboxRadioSwitch>
      <NcCheckboxRadioSwitch id="restorehistory"
                             v-tooltip="hints['restore-history']"
                             :model-value="settings.restoreHistory"
                             :disabled="saving.restoreHistory"
                             @update:model-value="saveSetting('restoreHistory', $event)"
      >
        {{ t(appId, 'Restore Last View') }}
      </NcCheckboxRadioSwitch>
      <NcCheckboxRadioSwitch id="filtervisibility"
                             v-tooltip="hints['filter-visibility']"
                             :model-value="settings.initialFilterVisibility"
                             :disabled="saving.initialFilterVisibility"
                             @update:model-value="saveSetting('initialFilterVisibility', $event)"
      >
        {{ t(appId, 'Filter-Controls') }}
      </NcCheckboxRadioSwitch>
      <NcCheckboxRadioSwitch id="directchange"
                             v-tooltip="hints['direct-change']"
                             :model-value="settings.directChange"
                             :disabled="saving.directChange"
                             @update:model-value="saveSetting('directChange', $event)"
      >
        {{ t(appId, 'Quick Change-Dialog') }}
      </NcCheckboxRadioSwitch>
      <NcCheckboxRadioSwitch id="deselect-invisible-misc-recs"
                             v-tooltip="hints['deselect-invisible-misc-recs']"
                             :model-value="settings.deselectInvisibleMiscRecs"
                             :disabled="saving.deselectInvisibleMiscRecs"
                             @update:model-value="saveSetting('deselectInvisibleMiscRecs', $event)"
      >
        {{ t(appId, 'Deselect Invisible') }}
      </NcCheckboxRadioSwitch>
      <div class="settings-control">
        <NcSelect v-model="pageRows"
                  v-tooltip="hints['table-rows-per-page']"
                  input-id="table-pagerows"
                  :input-label="t(appId, '#Rows/Page in Tables')"
                  :options="pageRowsOptions"
                  :clearable="false"
                  :disabled="saving.pageRowsDefault"
        />
      </div>
      <div class="settings-control">
        <NcSelect v-model="wysiwygEditor"
                  v-tooltip="hints['wysiwyg-editor']"
                  input-id="wysiwyg-editor"
                  :input-label="t(appId, 'WYSIWYG Text-Editor')"
                  :options="wysiwygOptions"
                  :clearable="false"
                  :disabled="saving.wysiwygEditor"
        />
      </div>
      <NcCheckboxRadioSwitch v-if="financeModeAllowed"
                             id="finance-mode"
                             v-tooltip="hints['finance-mode']"
                             :model-value="settings.financeMode"
                             :disabled="saving.financeMode"
                             @update:model-value="saveSetting('financeMode', $event)"
      >
        {{ t(appId, 'Finance-Mode') }}
      </NcCheckboxRadioSwitch>
      <NcCheckboxRadioSwitch id="expert-mode"
                             v-tooltip="hints['expert-mode']"
                             :model-value="settings.expertMode"
                             :disabled="saving.expertMode"
                             @update:model-value="saveSetting('expertMode', $event)"
      >
        {{ t(appId, 'Expert-Mode') }}
      </NcCheckboxRadioSwitch>
      <NcCheckboxRadioSwitch v-if="settings.expertMode"
                             id="showdisabled"
                             v-tooltip="hints['show-disabled']"
                             :model-value="settings.showDisabled"
                             :disabled="saving.showDisabled"
                             @update:model-value="saveSetting('showDisabled', $event)"
      >
        {{ t(appId, 'Show Disabled Data-Sets') }}
      </NcCheckboxRadioSwitch>
      <div v-if="settings.expertMode" class="settings-control">
        <NcSelect v-model="debugModes"
                  v-tooltip="hints['debug-mode']"
                  input-id="debug-modes-select"
                  :input-label="t(appId, 'Debug')"
                  :options="debugOptions"
                  :multiple="true"
                  :disabled="saving.debugMode"
        />
      </div>
      <NcTextField v-if="settings.expertMode && isDebugQuery"
                   v-model="sqlFilter"
                   :label="t(appId, 'SQL Filter')"
                   :placeholder="t(appId, 'SQL filter regexp')"
                   :helper-text="t(appId, 'A regular expression which selects matching SQL queries for logging.')"
                   :disabled="saving.debugQuerySqlFilter"
                   @blur="saveSqlFilter"
                   @keydown.enter="saveSqlFilter"
      />
    </div>
    <div :id="entry.children[1]?.id" class="settings-subsection">
      <h3>{{ entry.children[1]?.name }}</h3>
      <form class="encryption-key-form" @submit.prevent="submitEncryptionKey">
        <input type="hidden" name="userId" :value="userId" autocomplete="username" />
        <NcPasswordField v-model="loginPassword"
                         v-tooltip="hints['settings:personal:encryptionKey:own-password']"
                         :label="t(appId, 'Own Password')"
                         autocomplete="current-password"
                         :required="true"
                         :disabled="settingEncryptionKey"
        />
        <NcPasswordField v-model="encryptionKey"
                         v-tooltip="hints['settings:personal:encryptionKey']"
                         :label="t(appId, 'DB Encryption Key')"
                         autocomplete="new-password"
                         :disabled="settingEncryptionKey"
        />
        <NcButton type="submit"
                  variant="primary"
                  :disabled="settingEncryptionKey"
        >
          {{ t(appId, 'Set Encryption Key') }}
        </NcButton>
        <NcNoteCard v-if="keyMessage" :type="keyMessageType">
          {{ keyMessage }}
        </NcNoteCard>
      </form>
    </div>
    <LocaleInfo />
  </NcSettingsSection>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcCheckboxRadioSwitch, NcNoteCard, NcPasswordField, NcSelect, NcSettingsSection, NcTextField } from '@nextcloud/vue'
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { WYSIWYG_EDITORS } from '../../../build/ts-types/php-modules/Settings/ConfigConstants.ts'
import { appName as appId } from '../../config.ts'
import allDebugOptions, { DEBUG_QUERY } from '../../debug-modes.ts'
import type { PersonalSettingKey } from '../../services/personal-settings.ts'
import { setEncryptionKey, setPersonalSetting } from '../../services/personal-settings.ts'
import { showSettingFeedback } from '../../services/setting-feedback.ts'
import useTooltipsStore from '../../stores/tooltips.ts'
import { showInfo } from '../../toolkit/util/toasts.ts'
import getInitialState from '../../toolkit/util/initial-state.ts'
import LocaleInfo from './LocaleInfo.vue'
import type { TabEntry } from './settings-tree.ts'

type CheckboxKey = 'toolTipsEnabled' | 'restoreHistory' | 'initialFilterVisibility' | 'directChange'
  | 'deselectInvisibleMiscRecs' | 'financeMode' | 'expertMode' | 'showDisabled'

type SelectOption<T = number> = { value: T, label: string }

const initialState = getInitialState<Partial<Record<CheckboxKey, boolean|string>> & {
  financeModeAllowed?: boolean,
  pageRowsDefault?: number|string,
  debugMode?: number|string,
  debugQuerySqlFilter?: string,
  wysiwygEditor?: string,
  userId?: string,
}>({ section: 'CAFEVDB', defaults: {} })

const financeModeAllowed = !!initialState?.financeModeAllowed

const tooltipsProvider = useTooltipsStore()
tooltipsProvider.provideTooltips([
  'show-tool-tips',
  'restore-history',
  'filter-visibility',
  'direct-change',
  'deselect-invisible-misc-recs',
  'table-rows-per-page',
  'wysiwyg-editor',
  'finance-mode',
  'expert-mode',
  'show-disabled',
  'debug-mode',
  'settings:personal:encryptionKey',
  'settings:personal:encryptionKey:own-password',
])
const hints = tooltipsProvider.tooltipsData

const isEnabled = (value: boolean|string|undefined) => value === true || value === 'on'

const checkboxKeys: CheckboxKey[] = [
  'toolTipsEnabled', 'restoreHistory', 'initialFilterVisibility', 'directChange',
  'deselectInvisibleMiscRecs', 'financeMode', 'expertMode', 'showDisabled',
]
const settings = reactive(Object.fromEntries(
  checkboxKeys.map((key) => [key, isEnabled(initialState?.[key])]),
) as Record<CheckboxKey, boolean>)

const saving = reactive({
  ...Object.fromEntries(checkboxKeys.map((key) => [key, false])),
  pageRowsDefault: false,
  wysiwygEditor: false,
  debugMode: false,
  debugQuerySqlFilter: false,
} as Record<CheckboxKey|'pageRowsDefault'|'wysiwygEditor'|'debugMode'|'debugQuerySqlFilter', boolean>)

const emit = defineEmits<{
  error: [error: Error],
}>()

const emitError = (error: unknown) => emit('error', error instanceof Error ? error : new Error(String(error)))

const saveSetting = async (key: CheckboxKey, checked: boolean|string[]) => {
  const previousValue = settings[key]
  settings[key] = checked === true
  saving[key] = true
  try {
    showSettingFeedback((await setPersonalSetting(key, settings[key])).data, key)
  } catch (error) {
    settings[key] = previousValue
    emitError(error)
  } finally {
    saving[key] = false
  }
}

const pageRowsOptions: SelectOption[] = [-1, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100]
  .map((value) => ({ value, label: value === -1 ? '∞' : '' + value }))
const debugOptions: SelectOption[] = Object.entries(allDebugOptions)
  .map(([value, label]) => ({ value: +value, label }))
const wysiwygOptions: SelectOption<string>[] = Object.entries(WYSIWYG_EDITORS)
  .filter(([, { enabled }]) => enabled)
  .map(([value, { name }]) => ({ value, label: name }))

const pageRows = ref<SelectOption>(
  pageRowsOptions.find(({ value }) => value === +(initialState?.pageRowsDefault ?? 20)) ?? pageRowsOptions[2],
)
const wysiwygEditor = ref<SelectOption<string>|undefined>(
  wysiwygOptions.find(({ value }) => value === initialState?.wysiwygEditor) ?? wysiwygOptions[0],
)
const debugModes = ref<SelectOption[]>(
  debugOptions.filter(({ value }) => (+(initialState?.debugMode ?? 0) & value) !== 0),
)
const isDebugQuery = computed(() => debugModes.value.some(({ value }) => value === DEBUG_QUERY))
const sqlFilter = ref(initialState?.debugQuerySqlFilter ?? '')
let savedSqlFilter = sqlFilter.value

// Persist a select value; on failure restore the previous one and report the error.
// The saving flag is cleared after the next tick so that the revert does not trigger another save.
const persistWatcher = <T,>(
  target: { value: T },
  key: 'pageRowsDefault' | 'wysiwygEditor' | 'debugMode',
  payload: (value: T) => unknown,
) => watch(target, async (value, oldValue) => {
  if (saving[key] || value === undefined || oldValue === undefined) {
    return
  }
  saving[key] = true
  try {
    showSettingFeedback((await setPersonalSetting(key, payload(value))).data, key)
  } catch (error) {
    target.value = oldValue
    emitError(error)
  } finally {
    await nextTick()
    saving[key] = false
  }
})

persistWatcher(pageRows, 'pageRowsDefault', (option) => option.value)
persistWatcher(wysiwygEditor, 'wysiwygEditor', (option) => option?.value)
persistWatcher(debugModes, 'debugMode', (options) => options.map(({ value }) => ({ value })))

const saveSqlFilter = async () => {
  if (saving.debugQuerySqlFilter || sqlFilter.value === savedSqlFilter) {
    return
  }
  saving.debugQuerySqlFilter = true
  try {
    showSettingFeedback((await setPersonalSetting('debugQuerySqlFilter', sqlFilter.value)).data, 'debugQuerySqlFilter')
    savedSqlFilter = sqlFilter.value
  } catch (error) {
    sqlFilter.value = savedSqlFilter
    emitError(error)
  } finally {
    saving.debugQuerySqlFilter = false
  }
}

const userId = initialState?.userId ?? ''
const loginPassword = ref('')
const encryptionKey = ref('')
const settingEncryptionKey = ref(false)
const keyMessage = ref('')
const keyMessageType = ref<'success'|'error'>('success')

const submitEncryptionKey = async () => {
  keyMessage.value = ''
  if (loginPassword.value === '') {
    keyMessageType.value = 'error'
    keyMessage.value = t(appId, 'You must type in your login password.')
    return
  }
  settingEncryptionKey.value = true
  try {
    // An empty key is allowed, it means "no encryption".
    await setEncryptionKey(encryptionKey.value, loginPassword.value)
    loginPassword.value = ''
    encryptionKey.value = ''
    keyMessageType.value = 'success'
    keyMessage.value = t(appId, 'The encryption key has been set successfully.')
    showInfo(t(appId, 'Encryption key changed, reloading settings form.'))
    // The set of available settings depends on the encryption key.
    window.location.reload()
  } catch (error) {
    const messages = (error as { response?: { data?: { messages?: string[] } } })?.response?.data?.messages
    if (messages?.length) {
      keyMessageType.value = 'error'
      keyMessage.value = messages.join(' ')
    } else {
      keyMessageType.value = 'error'
      keyMessage.value = t(appId, 'Unable to set the encryption key.')
      emitError(error)
    }
  } finally {
    settingEncryptionKey.value = false
  }
}

defineProps<{ entry: TabEntry }>()
</script>

<style lang="scss" scoped>
.encryption-key-form {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: calc(var(--default-grid-baseline) * 2);

  :deep(.input-field) {
    flex: 1 1 14rem;
    min-width: 12rem;
    margin-block-start: 0;
  }

  // The note card spans the whole row below the controls.
  :deep(.notecard) {
    flex: 1 0 100%;
  }
}
</style>
