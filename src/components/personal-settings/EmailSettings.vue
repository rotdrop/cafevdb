<template>
  <AdminSettingsTab :entry="entry" :groups="groups" @error="emit('error', $event)">
    <template #mailing-list-actions>
      <NcButton :disabled="runningAction" @click="runAction('announcementsMailingListAutoconf')">
        {{ t(appId, 'Autoconfigure announcement mailing list') }}
      </NcButton>
      <NcNoteCard v-if="actionMessage" :type="actionError ? 'error' : 'success'">
        {{ actionMessage }}
      </NcNoteCard>
    </template>
    <template #email-test-actions>
      <NcButton :disabled="runningAction" @click="runAction('emailtest')">
        {{ t(appId, 'Test Email Settings') }}
      </NcButton>
      <NcNoteCard v-if="testMessage" :type="testError ? 'error' : 'success'">
        {{ testMessage }}
      </NcNoteCard>
    </template>
  </AdminSettingsTab>
</template>

<script setup lang="ts">
import type { AdminSettingField, AdminSettingGroup, AdminSettingOption } from './AdminSettingsTab.vue'
import type { TabEntry } from './settings-tree.ts'

import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcNoteCard } from '@nextcloud/vue'
import { ref } from 'vue'
import AdminSettingsTab from './AdminSettingsTab.vue'
import {
  ANNOUNCEMENTS_MAILING_LIST_KEY,
  ATTACHMENT_LINK_EXPIRATION_LIMIT,
  ATTACHMENT_LINK_SIZE_LIMIT,
  BULK_EMAIL_PRIVACY_NOTICE,
  BULK_EMAIL_SUBJECT_TAG,
  EMAIL_FROM_ADDRESS_KEY,
  EMAIL_FROM_DOMAIN_KEY,
  EMAIL_FROM_NAME_KEY,
  EMAIL_PASSWORD,
  EMAIL_TEST_ADDRESS_KEY,
  EMAIL_TEST_MODE,
  EMAIL_TEST_NAME_KEY,
  EMAIL_USER,
  IMAP_PORT,
  IMAP_SECURITY,
  IMAP_SERVER,
  MAILING_LIST_CONFIG,
  MAILING_LIST_REST_CONFIG,
  PRE_SEND_VALIDATION_EXTERNAL_LINKS_ENFORCE_HTTPS,
  PRE_SEND_VALIDATION_EXTERNAL_LINKS_SSL_VERIFY,
  SMTP_PORT,
  SMTP_SECURITY,
  SMTP_SERVER,
} from '../../../build/ts-types/php-modules/Settings/ConfigConstants.ts'
import { appName as appId } from '../../config.ts'
import { setAdminSetting } from '../../services/admin-settings.ts'
import getInitialState from '../../toolkit/util/initial-state.ts'

const props = defineProps<{ entry: TabEntry }>()
const emit = defineEmits<{ error: [error: Error] }>()
const state = getInitialState<Record<string, unknown>>({ section: 'adminSettings', defaults: {} })
const field = (
  key: string,
  label: string,
  type: AdminSettingField['type'] = 'text',
  options?: AdminSettingOption[],
): AdminSettingField => ({ key, label: t(appId, label), type, options })
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
const transportOptions: AdminSettingOption[] = ['insecure', 'starttls', 'ssl'].map((value) => ({
  value,
  label: value.toUpperCase(),
}))
const groups = [
  group(0, 'Server Settings', [
    field(SMTP_SERVER, 'SMTP Server'),
    field(SMTP_PORT, 'SMTP Port', 'number'),
    field(SMTP_SECURITY, 'SMTP Security', 'select', transportOptions),
    field(IMAP_SERVER, 'IMAP Server'),
    field(IMAP_PORT, 'IMAP Port', 'number'),
    field(IMAP_SECURITY, 'IMAP Security', 'select', transportOptions),
  ]),
  group(1, 'Email Account', [
    field(EMAIL_USER, 'Email user'),
    field(EMAIL_PASSWORD, 'Email password', 'password'),
  ]),
  group(2, 'Bulk Email Composition', [
    field(ANNOUNCEMENTS_MAILING_LIST_KEY, 'Announcements mailing list'),
    {
      ...field(EMAIL_FROM_ADDRESS_KEY, 'Sender email address'),
      initialValue: state?.[EMAIL_FROM_NAME_KEY]
        ? `${state[EMAIL_FROM_NAME_KEY]} <${state[EMAIL_FROM_ADDRESS_KEY] ?? ''}>`
        : state?.[EMAIL_FROM_ADDRESS_KEY],
    },
    field(EMAIL_FROM_DOMAIN_KEY, 'Sender email domain'),
    field(BULK_EMAIL_SUBJECT_TAG, 'Bulk email subject tag'),
    field(ATTACHMENT_LINK_SIZE_LIMIT, 'Attachment link size limit'),
    field(ATTACHMENT_LINK_EXPIRATION_LIMIT, 'Attachment link expiration limit'),
    field(PRE_SEND_VALIDATION_EXTERNAL_LINKS_SSL_VERIFY, 'Verify external-link SSL certificates', 'checkbox'),
    field(PRE_SEND_VALIDATION_EXTERNAL_LINKS_ENFORCE_HTTPS, 'Require HTTPS for external links', 'checkbox'),
  ]),
  group(3, 'Bulk Email Privacy Notice', [
    field(BULK_EMAIL_PRIVACY_NOTICE, 'Bulk Email Privacy Notice', 'textarea'),
  ]),
  group(4, 'Mailing List Service', [
    field(MAILING_LIST_REST_CONFIG.url, 'Mailing list service URL'),
    field(MAILING_LIST_REST_CONFIG.user, 'Mailing list service user'),
    field(MAILING_LIST_REST_CONFIG.password, 'Mailing list service password', 'password'),
    field(MAILING_LIST_CONFIG.domain, 'Mailing list email domain'),
    field(MAILING_LIST_CONFIG.web, 'Mailing list web pages'),
    field(MAILING_LIST_CONFIG.owner, 'Default mailing list owner'),
    field(MAILING_LIST_CONFIG.moderator, 'Default mailing list moderator'),
  ], 'mailing-list-actions'),
  group(5, 'Test Settings', [
    field(EMAIL_TEST_MODE, 'Email test mode', 'checkbox'),
    {
      ...field(EMAIL_TEST_ADDRESS_KEY, 'Test recipient address'),
      initialValue: state?.[EMAIL_TEST_NAME_KEY]
        ? `${state[EMAIL_TEST_NAME_KEY]} <${state[EMAIL_TEST_ADDRESS_KEY] ?? ''}>`
        : state?.[EMAIL_TEST_ADDRESS_KEY],
    },
  ], 'email-test-actions'),
]

const runningAction = ref(false)
const actionMessage = ref('')
const actionError = ref(false)
const testMessage = ref('')
const testError = ref(false)

const runAction = async (endpoint: string) => {
  runningAction.value = true
  const isEmailTest = endpoint === 'emailtest'
  if (isEmailTest) {
    testMessage.value = ''
    testError.value = false
  } else {
    actionMessage.value = ''
    actionError.value = false
  }
  try {
    const response = await setAdminSetting(endpoint, '')
    const messages = response.data.messages ?? response.data.message ?? ''
    const result = Array.isArray(messages) ? messages.join(' ') : messages
    if (isEmailTest) {
      testMessage.value = result
    } else {
      actionMessage.value = result
    }
  } catch (error) {
    if (isEmailTest) {
      testError.value = true
      testMessage.value = t(appId, 'Unable to test the email settings.')
    } else {
      actionError.value = true
      actionMessage.value = t(appId, 'Unable to configure the mailing list.')
    }
    emit('error', error instanceof Error ? error : new Error(String(error)))
  } finally {
    runningAction.value = false
  }
}
</script>
