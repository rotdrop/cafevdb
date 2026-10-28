<template>
  <AdminSettingsTab :entry="entry"
                    :groups="groups"
                    @error="emit('error', $event)"
                    @saved="onSaved"
  >
    <template #share-owner-password>
      <div class="admin-actions">
        <NcPasswordField v-model="shareOwnerPassword" :label="t(appId, 'Share-owner password')" autocomplete="new-password" />
        <NcButton :disabled="runningPasswordAction" @click="generateShareOwnerPassword">
          {{ t(appId, 'Generate') }}
        </NcButton>
        <NcButton variant="primary" :disabled="runningPasswordAction" @click="changeShareOwnerPassword">
          {{ t(appId, 'Change') }}
        </NcButton>
      </div>
      <NcNoteCard v-if="passwordMessage" :type="passwordError ? 'error' : 'success'">
        {{ passwordMessage }}
      </NcNoteCard>
    </template>
    <template #members-as-cloud-users>
      <ul v-if="cloudUserRequirements.length" class="requirements">
        <li v-for="(requirement, index) in cloudUserRequirements" :key="index">
          {{ requirement }}
        </li>
      </ul>
      <NcNoteCard v-if="dbUser" type="info">
        {{ t(appId, 'Please make sure the database user "{user}@{server}" has all -- and in particular GRANT -- privileges on the dedicated database. The views must live in a dedicated database, otherwise the cloud core could alter the view definitions.', { user: dbUser, server: dbServer }) }}
      </NcNoteCard>
      <NcNoteCard v-if="!viewsDatabase" type="warning">
        {{ t(appId, 'Please enter the name of a dedicated database for the SQL-views.') }}
      </NcNoteCard>
      <div class="admin-actions">
        <NcButton :disabled="runningAction || !viewsDatabase" @click="runAction('userSqlBackendRecreateViews')">
          {{ t(appId, 'Recreate cloud-user views') }}
        </NcButton>
        <NcButton :disabled="runningAction || !viewsDatabase" @click="runAction('musicianPersonalizedViewsRecreateViews')">
          {{ t(appId, 'Recreate personalized views') }}
        </NcButton>
      </div>
      <NcNoteCard v-if="actionMessage" :type="actionError ? 'error' : 'success'">
        {{ actionMessage }}
      </NcNoteCard>
    </template>
  </AdminSettingsTab>
</template>

<script setup lang="ts">
import type { AdminSettingField, AdminSettingGroup } from './AdminSettingsTab.vue'
import type { TabEntry } from './settings-tree.ts'

import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcNoteCard, NcPasswordField } from '@nextcloud/vue'
import { computed, ref } from 'vue'
import AdminSettingsTab from './AdminSettingsTab.vue'
import {
  BALANCES_FOLDER,
  CALENDAR_KEY_POSTFIX,
  CALENDARS,
  DOCUMENT_TEMPLATES_FOLDER,
  FINANCE_FOLDER,
  GENERAL_ADDRESS_BOOK_KEY,
  MUSICIANS_ADDRESS_BOOK_KEY,
  OUTBOX_FOLDER,
  POSTBOX_FOLDER,
  PROJECT_PARTICIPANTS_FOLDER,
  PROJECT_POSTERS_FOLDER,
  PROJECT_PUBLIC_DOWNLOADS_FOLDER,
  PROJECTS_FOLDER,
  SHARE_OWNER_KEY,
  SHARE_OWNER_PASSWORD_KEY,
  SHARED_FOLDER,
  TRANSACTIONS_FOLDER,
} from '../../../build/ts-types/php-modules/Settings/ConfigConstants.ts'
import { appName as appId } from '../../config.ts'
import { getAdminSetting, setAdminSetting } from '../../services/admin-settings.ts'
import getInitialState from '../../toolkit/util/initial-state.ts'
import { showSuccess } from '../../toolkit/util/toasts.ts'

const props = defineProps<{ entry: TabEntry }>()
const emit = defineEmits<{ error: [error: Error] }>()
const state = getInitialState<{
  cloudUserRequirements?: { hints?: string[] }|string[]
  cloudUserViewsDatabase?: string
  dbuser?: string
  dbserver?: string
}>({ section: 'adminSettings', defaults: {} })
const viewsDatabase = ref(String(state?.cloudUserViewsDatabase ?? '').trim())
const dbUser = computed(() => state?.dbuser ?? '')
const dbServer = computed(() => state?.dbserver ?? '')
const onSaved = (key: string, value: unknown) => {
  if (key === 'cloudUserViewsDatabase') {
    viewsDatabase.value = String(value ?? '').trim()
  }
}
const cloudUserRequirements = computed(() => {
  const requirements = state?.cloudUserRequirements
  if (Array.isArray(requirements)) {
    return requirements
  }
  return requirements?.hints ?? []
})
const field = (
  key: string,
  label: string,
  type: AdminSettingField['type'] = 'text',
): AdminSettingField => ({ key, label, type })
const group = (
  index: number,
  fallback: string,
  fields: AdminSettingField[],
  slot?: string,
): AdminSettingGroup => ({
  id: props.entry.children[index]?.id ?? `group-${index}`,
  name: props.entry.children[index]?.name ?? fallback,
  fields,
  slot,
})

const calendarKeys = Object.values(CALENDARS).map(({ uri }) => uri + CALENDAR_KEY_POSTFIX)
const folderKeys = [
  SHARED_FOLDER,
  POSTBOX_FOLDER,
  OUTBOX_FOLDER,
  DOCUMENT_TEMPLATES_FOLDER,
  PROJECTS_FOLDER,
  PROJECT_PARTICIPANTS_FOLDER,
  PROJECT_POSTERS_FOLDER,
  PROJECT_PUBLIC_DOWNLOADS_FOLDER,
  FINANCE_FOLDER,
  TRANSACTIONS_FOLDER,
  BALANCES_FOLDER,
]
const displayName = (key: string) => key
  .replace(/Folder$/, '')
  .replace(/([a-z])([A-Z])/g, '$1 $2')
  .replace(/^./, (letter) => letter.toUpperCase())

const groups: AdminSettingGroup[] = [
  group(0, t(appId, 'Share owner'), [
    field(SHARE_OWNER_KEY, t(appId, 'Share owner user ID'), 'owner'),
  ], 'share-owner-password'),
  group(1, t(appId, 'Calendars'), calendarKeys.map((key) => field(key, displayName(key)))),
  group(2, t(appId, 'Contacts'), [
    field(GENERAL_ADDRESS_BOOK_KEY, t(appId, 'General address book')),
    field(MUSICIANS_ADDRESS_BOOK_KEY, t(appId, 'Musicians address book')),
  ]),
  group(3, t(appId, 'Shared folder'), folderKeys.map((key) => field(key, displayName(key), 'folder'))),
  group(4, t(appId, 'Members as Cloud-Users'), [
    field('importClubMembersAsCloudUsers', t(appId, 'Import club members as cloud users'), 'checkbox'),
    field('cloudUserViewsDatabase', t(appId, 'Dedicated views database')),
    field('musicianPersonalizedViews', t(appId, 'Musician personalized views'), 'checkbox'),
  ], 'members-as-cloud-users'),
]

const runningAction = ref(false)
const actionMessage = ref('')
const actionError = ref(false)
const shareOwnerPassword = ref('')
const runningPasswordAction = ref(false)
const passwordMessage = ref('')
const passwordError = ref(false)

const generateShareOwnerPassword = async () => {
  runningPasswordAction.value = true
  passwordMessage.value = ''
  passwordError.value = false
  try {
    const response = await getAdminSetting('passwordgenerate')
    shareOwnerPassword.value = String(response.data.value ?? '')
  } catch (error) {
    passwordError.value = true
    passwordMessage.value = t(appId, 'Unable to generate a password.')
    emit('error', error instanceof Error ? error : new Error(String(error)))
  } finally {
    runningPasswordAction.value = false
  }
}

const changeShareOwnerPassword = async () => {
  runningPasswordAction.value = true
  passwordMessage.value = ''
  passwordError.value = false
  try {
    const response = await setAdminSetting(SHARE_OWNER_PASSWORD_KEY, shareOwnerPassword.value)
    const messages = response.data.messages ?? response.data.message ?? ''
    passwordMessage.value = Array.isArray(messages) ? messages.join(' ') : messages
    if (passwordMessage.value) {
      showSuccess(passwordMessage.value)
    }
    shareOwnerPassword.value = ''
  } catch (error) {
    passwordError.value = true
    passwordMessage.value = t(appId, 'Unable to change the share-owner password.')
    emit('error', error instanceof Error ? error : new Error(String(error)))
  } finally {
    runningPasswordAction.value = false
  }
}

const runAction = async (endpoint: string) => {
  runningAction.value = true
  actionMessage.value = ''
  actionError.value = false
  try {
    const response = await setAdminSetting(endpoint)
    actionMessage.value = (response.data.messages ?? []).join(' ')
    if (actionMessage.value) {
      showSuccess(actionMessage.value)
    }
  } catch (error) {
    actionError.value = true
    actionMessage.value = t(appId, 'Unable to update cloud-user views.')
    emit('error', error instanceof Error ? error : new Error(String(error)))
  } finally {
    runningAction.value = false
  }
}
</script>

<style lang="scss" scoped>
.requirements {
  padding-inline-start: 2rem;
  list-style: disc;
}

.admin-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: calc(var(--default-grid-baseline) * 2);

  :deep(.input-field) {
    flex: 1 1 14rem;
  }
}
</style>
