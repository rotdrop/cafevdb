<template>
  <AdminSettingsTab :entry="entry" :groups="groups" @error="emit('error', $event)">
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
      <div class="admin-actions">
        <NcButton :disabled="runningAction" @click="runAction('userSqlBackendRecreateViews')">
          {{ t(appId, 'Recreate cloud-user views') }}
        </NcButton>
        <NcButton :disabled="runningAction" @click="runAction('musicianPersonalizedViewsRecreateViews')">
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

const props = defineProps<{ entry: TabEntry }>()
const emit = defineEmits<{ error: [error: Error] }>()
const state = getInitialState<{
  cloudUserRequirements?: { hints?: string[] }|string[]
}>({ section: 'adminSettings', defaults: {} })
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
): AdminSettingField => ({ key, label: t(appId, label), type })
const group = (
  index: number,
  fallback: string,
  fields: AdminSettingField[],
  slot?: string,
): AdminSettingGroup => ({
  id: props.entry.children[index]?.id ?? fallback,
  name: props.entry.children[index]?.name ?? t(appId, fallback),
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
  group(0, 'Share owner', [
    field(SHARE_OWNER_KEY, 'Share owner user ID', 'owner'),
  ], 'share-owner-password'),
  group(1, 'Calendars', calendarKeys.map((key) => field(key, displayName(key)))),
  group(2, 'Contacts', [
    field(GENERAL_ADDRESS_BOOK_KEY, 'General address book'),
    field(MUSICIANS_ADDRESS_BOOK_KEY, 'Musicians address book'),
  ]),
  group(3, 'Shared folder', folderKeys.map((key) => field(key, displayName(key), 'folder'))),
  group(4, 'Members as Cloud-Users', [
    field('importClubMembersAsCloudUsers', 'Import club members as cloud users', 'checkbox'),
    field('cloudUserViewsDatabase', 'Cloud-user views database'),
    field('musicianPersonalizedViews', 'Musician personalized views', 'checkbox'),
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
    const response = await setAdminSetting(endpoint, '')
    actionMessage.value = (response.data.messages ?? []).join(' ')
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
