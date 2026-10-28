<!--
 - Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
<template>
  <div class="personal-settings-vue">
    <NcCheckboxRadioSwitch v-model="state.toolTipsEnabled"
                           v-tooltip="hints['show-tool-tips']"
                           @update:modelValue="applyBoolean('toolTipsEnabled', setToolTipsMode, $event)"
    >
      {{ t(appId, 'Tool-Tips') }}
    </NcCheckboxRadioSwitch>
    <NcCheckboxRadioSwitch v-model="state.restoreHistory"
                           v-tooltip="hints['restore-history']"
                           @update:modelValue="applyBoolean('restoreHistory', setRestoreHistory, $event)"
    >
      {{ t(appId, 'Restore Last View') }}
    </NcCheckboxRadioSwitch>
    <NcCheckboxRadioSwitch v-model="state.initialFilterVisibility"
                           v-tooltip="hints['filter-visibility']"
                           @update:modelValue="applyBoolean('initialFilterVisibility', setInitialFilterVisibility, $event)"
    >
      {{ t(appId, 'Filter-Controls') }}
    </NcCheckboxRadioSwitch>
    <NcCheckboxRadioSwitch v-model="state.directChange"
                           v-tooltip="hints['direct-change']"
                           @update:modelValue="applyBoolean('directChange', setDirectChange, $event)"
    >
      {{ t(appId, 'Quick Change-Dialog') }}
    </NcCheckboxRadioSwitch>
    <NcCheckboxRadioSwitch v-model="state.deselectInvisibleMiscRecs"
                           v-tooltip="hints['deselect-invisible-misc-recs']"
                           @update:modelValue="applyBoolean('deselectInvisibleMiscRecs', setDeselectInvisible, $event)"
    >
      {{ t(appId, 'Deselect Invisible') }}
    </NcCheckboxRadioSwitch>

    <div v-tooltip="hints['table-rows-per-page']" class="select-wrapper">
      <label for="personal-settings-page-rows">{{ t(appId, 'Display #Rows/Page in Tables') }}</label>
      <NcSelect v-model="state.pageRowsDefault"
                inputId="personal-settings-page-rows"
                class="page-rows"
                :options="pageRowsOptions"
                :reduce="(option: SelectOption<number>) => option.value"
                :clearable="false"
                :searchable="false"
                :placeholder="t(appId, '#Rows')"
                @update:modelValue="applyPageRows"
      />
    </div>

    <div v-tooltip="hints['wysiwyg-editor']" class="select-wrapper">
      <label for="personal-settings-wysiwyg-editor">{{ t(appId, 'WYSIWYG Text-Editor') }}</label>
      <NcSelect v-model="state.wysiwygEditor"
                inputId="personal-settings-wysiwyg-editor"
                class="wysiwyg-editor"
                :options="wysiwygEditorOptions"
                :reduce="(option: SelectOption<string>) => option.value"
                :selectable="(option: SelectOption<string>) => !option.disabled"
                :clearable="false"
                :searchable="false"
                :placeholder="t(appId, 'WYSIWYG Editor')"
                @update:modelValue="applyWysiwygEditor"
      />
    </div>

    <NcCheckboxRadioSwitch v-if="config.isTreasurer"
                           v-model="state.financeMode"
                           v-tooltip="hints['finance-mode']"
                           @update:modelValue="applyBoolean('financeMode', setFinanceMode, $event)"
    >
      {{ t(appId, 'Finance-Mode') }}
    </NcCheckboxRadioSwitch>
    <NcCheckboxRadioSwitch v-model="state.expertMode"
                           v-tooltip="hints['expert-operations']"
                           @update:modelValue="applyBoolean('expertMode', setExpertMode, $event)"
    >
      {{ t(appId, 'Expert-Mode') }}
    </NcCheckboxRadioSwitch>

    <div v-if="state.expertMode" class="expert-mode-container">
      <NcCheckboxRadioSwitch v-model="state.showDisabled"
                             v-tooltip="hints['show-disabled']"
                             @update:modelValue="applyBoolean('showDisabled', setShowDisabled, $event)"
      >
        {{ t(appId, 'Show Disabled Data-Sets') }}
      </NcCheckboxRadioSwitch>
      <div v-tooltip="hints['debug-mode']" class="select-wrapper debug-mode-container">
        <label for="personal-settings-debug-mode">{{ t(appId, 'Enable Debug Mode') }}</label>
        <NcSelect v-model="selectedDebugModes"
                  inputId="personal-settings-debug-mode"
                  class="debug-mode"
                  :options="debugModeOptions"
                  :reduce="(option: SelectOption<number>) => option.value"
                  :multiple="true"
                  :searchable="false"
                  :keepOpen="true"
                  :placeholder="t(appId, 'Enable Debug Mode')"
                  @update:modelValue="applyDebugModes"
        />
      </div>
    </div>

    <form class="encryption-key-form" @submit.prevent="setEncryptionKey">
      <input type="hidden"
             name="userId"
             :value="config.userId"
             autocomplete="username"
      >
      <div v-tooltip="hints['settings:personal:encryptionKey:own-password']">
        <NcPasswordField v-model="loginPassword"
                         :label="t(appId, 'Own Password')"
                         autocomplete="current-password"
                         :required="true"
                         :disabled="keyBusy"
        />
      </div>
      <div v-tooltip="hints['settings:personal:encryptionKey']">
        <NcPasswordField v-model="encryptionKey"
                         :label="t(appId, 'DB Encryption Key')"
                         autocomplete="off"
                         :disabled="keyBusy"
        />
      </div>
      <NcButton type="submit" :disabled="keyBusy">
        {{ t(appId, 'Set Encryption Key') }}
      </NcButton>
      <NcNoteCard v-if="keyStatus" :type="keyStatus.type">
        {{ keyStatus.message }}
      </NcNoteCard>
    </form>
  </div>
</template>

<script setup lang="ts">
import type { PersonalSettingsConfig } from '../types/personal-settings.d.ts'

import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import {
  NcButton,
  NcCheckboxRadioSwitch,
  NcNoteCard,
  NcPasswordField,
  NcSelect,
} from '@nextcloud/vue'
import { subscribe, unsubscribe } from '@rotdrop/async-nextcloud-event-bus'
import vTooltip from '@rotdrop/nextcloud-vue-components/lib/directives/Tooltip'
import {
  computed,
  inject,
  onBeforeUnmount,
  reactive,
  ref,
} from 'vue'
import { EnumPersonalSettingsKey } from '../../build/ts-types/php-modules/Controller.ts'
import globalState from '../app/globalstate.ts'
import * as Notification from '../app/notification.ts'
import { setPersonalUrl } from '../app/settings-urls.ts'
import setDebugModes from '../app/settings/debug-modes.ts'
import setDeselectInvisible from '../app/settings/deselect-invisible.ts'
import setDirectChange from '../app/settings/direct-change.ts'
import setExpertMode from '../app/settings/expert-mode.ts'
import setFinanceMode from '../app/settings/finance-mode.ts'
import setInitialFilterVisibility from '../app/settings/initial-filter-visibility.ts'
import setPageRows from '../app/settings/pagerows.ts'
import setRestoreHistory from '../app/settings/restore-history.ts'
import setShowDisabled from '../app/settings/show-disabled.ts'
import setToolTipsMode from '../app/settings/tooltips.ts'
import { appName } from '../config.ts'
import debugOptions from '../debug-modes.ts'
import {
  SET_DEBUG_MODES,
  SET_DESELECT_INVISIBLE,
  SET_DIRECT_CHANGE,
  SET_EXPERT_MODE,
  SET_FINANCE_MODE,
  SET_INITIAL_FILTER_VISIBILITY,
  SET_PAGE_ROWS,
  SET_RESTORE_HISTORY,
  SET_SHOW_DISABLED,
  SET_TOOLTIPS_MODE,
} from '../event-bus-events.ts'
import useTooltipsStore from '../stores/tooltips.ts'
import Console from '../util/console.ts'
import { showErrorToast } from '../util/toasts.ts'

const props = defineProps<{ config: PersonalSettingsConfig }>()

const emit = defineEmits<{
  encryptionKeySet: []
}>()

const COMPONENT_NAME = 'PersonalSettings'

const logger = new Console(COMPONENT_NAME)

interface SelectOption<T> {
  value: T
  label: string
  disabled?: boolean
}

type BooleanSettingKey = {
  [K in keyof PersonalSettingsConfig]: PersonalSettingsConfig[K] extends boolean ? K : never
}[keyof PersonalSettingsConfig]

const appId = inject<string>('appId', appName)

const MAX_PAGE_ROWS = 100

const state = reactive({
  toolTipsEnabled: props.config.toolTipsEnabled,
  restoreHistory: props.config.restoreHistory,
  initialFilterVisibility: props.config.initialFilterVisibility,
  directChange: props.config.directChange,
  deselectInvisibleMiscRecs: props.config.deselectInvisibleMiscRecs,
  showDisabled: props.config.showDisabled,
  expertMode: props.config.expertMode,
  financeMode: props.config.financeMode,
  // only multiples of 10 are offered, -1 means "all rows"
  pageRowsDefault: props.config.pageRowsDefault < 0
    ? -1
    : Math.min(MAX_PAGE_ROWS, Math.floor(props.config.pageRowsDefault / 10) * 10),
  debugMode: props.config.debugMode,
  wysiwygEditor: props.config.wysiwygEditor,
})

const pageRowsOptions: SelectOption<number>[] = [{ value: -1, label: '∞' }]
for (let rows = 10; rows <= MAX_PAGE_ROWS; rows += 10) {
  pageRowsOptions.push({ value: rows, label: '' + rows })
}

const wysiwygEditorOptions: SelectOption<string>[] = Object.entries(props.config.wysiwygOptions)
  .map(([value, { name, enabled }]) => ({ value, label: name, disabled: !enabled }))

const debugModeOptions: SelectOption<number>[] = Object.entries(debugOptions)
  .map(([value, label]) => ({ value: +value, label }))

const selectedDebugModes = computed({
  get: () => debugModeOptions.map(({ value }) => value).filter((flag) => (state.debugMode & flag) !== 0),
  set: (flags: number[]) => {
    state.debugMode = flags.reduce((mask, flag) => mask | flag, 0)
  },
})

const tooltipsProvider = useTooltipsStore()
const tooltipKeys = [
  'show-tool-tips',
  'restore-history',
  'filter-visibility',
  'direct-change',
  'deselect-invisible-misc-recs',
  'table-rows-per-page',
  'wysiwyg-editor',
  'finance-mode',
  'expert-operations',
  'show-disabled',
  'debug-mode',
  `settings:personal:${EnumPersonalSettingsKey.ENCRYPTION_KEY}`,
  `settings:personal:${EnumPersonalSettingsKey.ENCRYPTION_KEY}:own-password`,
]
tooltipsProvider.provideTooltips(tooltipKeys)
const hints = tooltipsProvider.tooltipsData

/**
 * Forward a changed value to the legacy setter which takes care of
 * persisting the value and of updating the remaining legacy code. On
 * failure the control is reverted.
 *
 * @param key Key into the local state.
 *
 * @param setter The legacy setter.
 *
 * @param value The new value.
 */
const applyBoolean = async (
  key: BooleanSettingKey,
  setter: (value: boolean) => Promise<unknown>,
  value: boolean,
) => {
  try {
    await setter(value)
  } catch (error) {
    logger.error('Unable to set value', { key, value, error })
    state[key] = !value
  }
}

const failureMessage = (error: unknown): string => {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const data = (error as any)?.response?.data
  if (Array.isArray(data?.messages) && data.messages.length > 0) {
    return data.messages.join(' ')
  }
  return data?.message || t(appId, 'reason unknown')
}

const applyPageRows = async (value: number) => {
  try {
    await setPageRows(value)
  } catch (error) {
    logger.error('Unable to set the number of rows', { value, error })
    state.pageRowsDefault = globalState.PHPMyEdit.pageRowsDefault ?? props.config.pageRowsDefault
  }
}

const applyDebugModes = async (flags: number[]) => {
  try {
    await setDebugModes(flags.map((value) => ({ value })))
  } catch (error) {
    logger.error('Unable to set the debug mode', { flags, error })
    state.debugMode = globalState.debugMode ?? props.config.debugMode
  }
}

const applyWysiwygEditor = async (value: string) => {
  const oldValue = globalState.wysiwygEditor ?? props.config.wysiwygEditor
  try {
    const response = await axios.post(setPersonalUrl(EnumPersonalSettingsKey.WYSIWYG_EDITOR), { value })
    Notification.messages(response.data.messages)
    globalState.wysiwygEditor = value
  } catch (error) {
    logger.error('Unable to set the WYSIWYG editor', { value, error })
    showErrorToast(failureMessage(error))
    state.wysiwygEditor = oldValue
  }
}

// Keep the displayed values in sync with changes originating elsewhere
// in the app, e.g. the tooltips toggle of the navigation bar.
const booleanSubscriptions: [typeof SET_TOOLTIPS_MODE, BooleanSettingKey][] = [
  [SET_TOOLTIPS_MODE, 'toolTipsEnabled'],
  [SET_RESTORE_HISTORY, 'restoreHistory'],
  [SET_INITIAL_FILTER_VISIBILITY, 'initialFilterVisibility'],
  [SET_DIRECT_CHANGE, 'directChange'],
  [SET_DESELECT_INVISIBLE, 'deselectInvisibleMiscRecs'],
  [SET_SHOW_DISABLED, 'showDisabled'],
  [SET_EXPERT_MODE, 'expertMode'],
  [SET_FINANCE_MODE, 'financeMode'],
]
const booleanHandlers = booleanSubscriptions.map(([eventName, key]) => {
  const handler = (event?: { value: boolean }) => {
    state[key] = !!event?.value
  }
  subscribe(eventName, handler)
  return () => unsubscribe(eventName, handler)
})
const pageRowsHandler = (event?: { value: number }) => {
  state.pageRowsDefault = +(event?.value ?? state.pageRowsDefault)
}
const debugModesHandler = (event?: { value: { value: number|string }[] }) => {
  state.debugMode = (event?.value ?? []).reduce((mask, { value }) => mask | +value, 0)
}
subscribe(SET_PAGE_ROWS, pageRowsHandler)
subscribe(SET_DEBUG_MODES, debugModesHandler)
onBeforeUnmount(() => {
  for (const unsubscribeHandler of booleanHandlers) {
    unsubscribeHandler()
  }
  unsubscribe(SET_PAGE_ROWS, pageRowsHandler)
  unsubscribe(SET_DEBUG_MODES, debugModesHandler)
})

/*
 * Encryption key
 */

const loginPassword = ref('')
const encryptionKey = ref('')
const keyBusy = ref(false)
const keyStatus = ref<{ type: 'success'|'error', message: string }|null>(null)

const setEncryptionKey = async () => {
  keyStatus.value = null
  if (loginPassword.value === '') {
    keyStatus.value = { type: 'error', message: t(appId, 'You must type in your login password.') }
    return
  }
  keyBusy.value = true
  try {
    // an empty key is allowed, it means "no encryption"
    await axios.post(
      setPersonalUrl(EnumPersonalSettingsKey.ENCRYPTION_KEY),
      {
        value: {
          [EnumPersonalSettingsKey.ENCRYPTION_KEY]: encryptionKey.value,
          loginpassword: loginPassword.value,
        },
      },
    )
    loginPassword.value = ''
    encryptionKey.value = ''
    keyStatus.value = { type: 'success', message: t(appId, 'The encryption key has been set successfully.') }
    emit('encryptionKeySet')
  } catch (error) {
    logger.error('Unable to set the encryption key', error)
    keyStatus.value = {
      type: 'error',
      message: t(appId, 'Unable to set the encryption key.') + ' ' + failureMessage(error),
    }
  } finally {
    keyBusy.value = false
  }
}
</script>

<style lang="scss" scoped>
.personal-settings-vue {
  display: flex;
  flex-direction: column;
  gap: 0.5ex;
  max-width: 40ex;

  .select-wrapper {
    display: flex;
    flex-direction: column;
    margin: 0.5ex 0;
  }

  .page-rows {
    width: 12ex;
  }

  .encryption-key-form {
    display: flex;
    flex-direction: column;
    gap: 1ex;
    margin-top: 2ex;
  }
}
</style>
