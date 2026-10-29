<!-- General, encryption and database settings. -->
<template>
  <AdminSettingsTab :entry="entry"
                    :groups="groups"
                    @error="emit('error', $event)"
                    @saved="onSaved"
  >
    <template #general-settings>
      <LocaleInfo :localeSymbol="localeInfo.localeSymbol"
                  :currencySymbol="localeInfo.currencySymbol"
                  :currencyCode="localeInfo.currencyCode"
                  :timeZone="localeInfo.dateTimeZone"
      />
    </template>
    <template #encryption-settings>
      <div class="admin-actions">
        <NcPasswordField v-model="oldSystemKey" :label="t(appId, 'Current Key')" autocomplete="current-password" />
        <NcPasswordField v-model="newSystemKey" :label="t(appId, 'New Key')" autocomplete="new-password" />
        <NcButton :disabled="savingKey" @click="generateSystemKey">
          {{ t(appId, 'Generate') }}
        </NcButton>
        <NcButton variant="primary" :disabled="savingKey" @click="changeSystemKey">
          {{ t(appId, 'Change Encryption Key') }}
        </NcButton>
        <NcButton :disabled="savingKey" @click="distributeSystemKey">
          {{ t(appId, 'Distribute Encryption Key') }}
        </NcButton>
        <NcNoteCard v-if="keyMessage" :type="keyError ? 'error' : 'success'">
          {{ keyMessage }}
        </NcNoteCard>
      </div>
    </template>
    <template #database-settings>
      <div class="admin-actions">
        <NcPasswordField v-model="databasePassword" :label="t(appId, 'Database password')" autocomplete="new-password" />
        <NcButton :disabled="testingDatabase" @click="testDatabasePassword">
          {{ t(appId, 'Test Database Password') }}
        </NcButton>
        <NcNoteCard v-if="databaseMessage" :type="databaseError ? 'error' : 'success'">
          {{ databaseMessage }}
        </NcNoteCard>
      </div>
    </template>
  </AdminSettingsTab>
</template>

<script setup lang="ts">
import type { AdminSettingField, AdminSettingGroup } from './AdminSettingsTab.vue'
import type { TabEntry } from './settings-tree.ts'

import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcNoteCard, NcPasswordField } from '@nextcloud/vue'
import { reactive, ref } from 'vue'
import AdminSettingsTab from './AdminSettingsTab.vue'
import LocaleInfo from './LocaleInfo.vue'
import {
  APP_DB_NAME,
  APP_DB_PASSWORD,
  APP_DB_SERVER,
  APP_DB_USER,
  ORCHESTRA_LOCALE_KEY,
  ORCHESTRA_NAME_KEY,
} from '../../../build/ts-types/php-modules/Settings/ConfigConstants.ts'
import { appName as appId } from '../../config.ts'
import { getAdminSetting, getLocaleInfo, setAdminSetting } from '../../services/admin-settings.ts'
import { handleSettingError } from '../../services/setting-feedback.ts'
import getInitialState from '../../toolkit/util/initial-state.ts'
import { showSuccess } from '../../toolkit/util/toasts.ts'

const props = defineProps<{ entry: TabEntry }>()
const emit = defineEmits<{ error: [error: Error] }>()
const state = getInitialState<{
  localeOptions?: Array<{ value: string, name: string }>
  orchestraLocale?: string
  orchestraCurrencySymbol?: string
  orchestraCurrencyCode?: string
  dateTimeZone?: string
}>({ section: 'adminSettings', defaults: {} })
const localeInfo = reactive({
  localeSymbol: state?.orchestraLocale ?? '',
  currencySymbol: state?.orchestraCurrencySymbol ?? '',
  currencyCode: state?.orchestraCurrencyCode ?? '',
  dateTimeZone: state?.dateTimeZone ?? 'UTC',
})
const onSaved = async (key: string) => {
  if (key !== ORCHESTRA_LOCALE_KEY) {
    return
  }
  try {
    Object.assign(localeInfo, (await getLocaleInfo('app')).data)
  } catch (error) {
    handleSettingError(error, (forwarded) => emit('error', forwarded))
  }
}
const localeOptions = (state?.localeOptions ?? []).map(({ value, name }) => ({ value, label: name }))

const field = (key: string, label: string, type: AdminSettingField['type'] = 'text'): AdminSettingField => ({
  key,
  label,
  type,
})

const groups: AdminSettingGroup[] = [
  {
    id: props.entry.children[0]?.id ?? 'general-settings',
    name: props.entry.children[0]?.name ?? t(appId, 'General settings'),
    slot: 'general-settings',
    fields: [
      field(ORCHESTRA_NAME_KEY, t(appId, 'Name of orchestra')),
      { ...field(ORCHESTRA_LOCALE_KEY, t(appId, 'Locale of the orchestra'), 'select'), options: localeOptions },
    ],
  },
  {
    id: props.entry.children[1]?.id ?? 'encryption-settings',
    name: props.entry.children[1]?.name ?? t(appId, 'Encryption settings'),
    fields: [],
    slot: 'encryption-settings',
  },
  {
    id: props.entry.children[2]?.id ?? 'database-settings',
    name: props.entry.children[2]?.name ?? t(appId, 'Database settings'),
    slot: 'database-settings',
    fields: [
      field(APP_DB_SERVER, t(appId, 'Database server')),
      field(APP_DB_NAME, t(appId, 'Database name')),
      field(APP_DB_USER, t(appId, 'Database user')),
    ],
  },
]

const oldSystemKey = ref('')
const newSystemKey = ref('')
const databasePassword = ref('')
const savingKey = ref(false)
const testingDatabase = ref(false)
const keyMessage = ref('')
const databaseMessage = ref('')
const keyError = ref(false)
const databaseError = ref(false)

const reportError = (error: unknown) => handleSettingError(error, (forwarded) => emit('error', forwarded))
const responseMessage = (data: { message?: string|string[], messages?: string[] }) => {
  const message = data.messages ?? data.message ?? ''
  return Array.isArray(message) ? message.join(' ') : message
}

const generateSystemKey = async () => {
  try {
    const response = await getAdminSetting('passwordgenerate')
    newSystemKey.value = String(response.data.value ?? '')
  } catch (error) {
    reportError(error)
  }
}

const changeSystemKey = async () => {
  keyMessage.value = ''
  keyError.value = false
  savingKey.value = true
  try {
    const response = await setAdminSetting('systemkey', {
      systemkey: newSystemKey.value,
      oldkey: oldSystemKey.value,
    })
    keyMessage.value = responseMessage(response.data) || t(appId, 'Encryption key changed.')
    showSuccess(keyMessage.value)
    oldSystemKey.value = ''
    newSystemKey.value = ''
  } catch (error) {
    keyError.value = true
    keyMessage.value = t(appId, 'Unable to change the encryption key.')
    reportError(error)
  } finally {
    savingKey.value = false
  }
}

const distributeSystemKey = async () => {
  keyMessage.value = ''
  keyError.value = false
  savingKey.value = true
  try {
    const response = await setAdminSetting('keydistribute')
    keyMessage.value = responseMessage(response.data) || t(appId, 'Encryption key distributed.')
    showSuccess(keyMessage.value)
  } catch (error) {
    keyError.value = true
    keyMessage.value = t(appId, 'Unable to distribute the encryption key.')
    reportError(error)
  } finally {
    savingKey.value = false
  }
}

const testDatabasePassword = async () => {
  databaseMessage.value = ''
  databaseError.value = false
  testingDatabase.value = true
  try {
    const response = await setAdminSetting(APP_DB_PASSWORD, databasePassword.value)
    databaseMessage.value = responseMessage(response.data)
    if (databaseMessage.value) {
      showSuccess(databaseMessage.value)
    }
    databasePassword.value = ''
  } catch (error) {
    databaseError.value = true
    databaseMessage.value = t(appId, 'Database password test failed.')
    reportError(error)
  } finally {
    testingDatabase.value = false
  }
}
</script>

<style lang="scss" scoped>
.admin-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: calc(var(--default-grid-baseline) * 2);

  :deep(.input-field) {
    flex: 1 1 14rem;
  }

  :deep(.notecard) {
    flex: 1 0 100%;
  }
}
</style>
