<template>
  <AdminSettingsTab :entry="entry" :groups="groups" @error="emit('error', $event)" />
</template>

<script setup lang="ts">
import type { AdminSettingField, AdminSettingGroup } from './AdminSettingsTab.vue'
import type { TabEntry } from './settings-tree.ts'

import { translate as t } from '@nextcloud/l10n'
import AdminSettingsTab from './AdminSettingsTab.vue'
import {
  BANK_ACCOUNT_BANK_HOLIDAYS,
  BANK_ACCOUNT_BANK_NAME,
  BANK_ACCOUNT_BIC,
  BANK_ACCOUNT_BLZ,
  BANK_ACCOUNT_CREDITOR_IDENTIFIER,
  BANK_ACCOUNT_IBAN,
  BANK_ACCOUNT_OWNER,
  DOCUMENT_TEMPLATES,
  EVENT_DURATION_KEY,
  STREET_ADDRESS_CITY,
  STREET_ADDRESS_COUNTRY,
  STREET_ADDRESS_HOUSE_NUMBER,
  STREET_ADDRESS_NAME_01,
  STREET_ADDRESS_NAME_02,
  STREET_ADDRESS_STREET,
  STREET_ADDRESS_ZIP,
} from '../../../build/ts-types/php-modules/Settings/ConfigConstants.ts'
import { appName as appId } from '../../config.ts'
import getInitialState from '../../toolkit/util/initial-state.ts'

const props = defineProps<{ entry: TabEntry }>()
const emit = defineEmits<{ error: [error: Error] }>()
const state = getInitialState<{
  executiveBoardMembers?: Array<{ value: string|number, name: string }>
  localeCountryNames?: Record<string, string>
  userGroupMembers?: string[]
  userGroups?: Array<{ value: string, name: string }>
}>({ section: 'adminSettings', defaults: {} })
const field = (key: string, label: string, type: AdminSettingField['type'] = 'text'): AdminSettingField => ({
  key,
  label: t(appId, label),
  type,
})
const group = (index: number, fallback: string, fields: AdminSettingField[]): AdminSettingGroup => ({
  id: props.entry.children[index]?.id ?? fallback,
  name: props.entry.children[index]?.name ?? t(appId, fallback),
  fields,
})
const options = (values: Array<{ value: string|number, name: string }> = []) =>
  values.map(({ value, name }) => ({ value, label: name }))

const groups: AdminSettingGroup[] = [
  group(0, 'Street Address', [
    field(STREET_ADDRESS_NAME_01, 'Name'),
    field(STREET_ADDRESS_NAME_02, 'Additional name'),
    field(STREET_ADDRESS_STREET, 'Street'),
    field(STREET_ADDRESS_HOUSE_NUMBER, 'House number'),
    field(STREET_ADDRESS_ZIP, 'ZIP'),
    field(STREET_ADDRESS_CITY, 'City'),
    {
      ...field(STREET_ADDRESS_COUNTRY, 'Country', 'select'),
      options: Object.entries(state?.localeCountryNames ?? {}).map(([value, label]) => ({ value, label })),
    },
    field('registerName', 'Register name'),
    field('registerNumber', 'Register number'),
    field('phoneNumber', 'Phone number'),
  ]),
  group(1, 'Bank Account', [
    field(BANK_ACCOUNT_OWNER, 'Account owner'),
    field(BANK_ACCOUNT_IBAN, 'IBAN'),
    field(BANK_ACCOUNT_BLZ, 'Bank code'),
    field(BANK_ACCOUNT_BIC, 'BIC'),
    field(BANK_ACCOUNT_BANK_NAME, 'Bank name'),
    field(BANK_ACCOUNT_CREDITOR_IDENTIFIER, 'Creditor identifier'),
    field(BANK_ACCOUNT_BANK_HOLIDAYS, 'Bank holiday region'),
    field(EVENT_DURATION_KEY, 'Default event duration (minutes)', 'number'),
  ]),
  group(2, 'Document templates', Object.entries(DOCUMENT_TEMPLATES).map(([key, info]) => ({
    key,
    label: t(appId, info.name),
    type: 'text' as const,
    initialValueKey: key + 'FileName',
  }))),
  group(3, 'Executive board and club members', [
    field('memberProject', 'Club members project'),
    field('executiveBoardProject', 'Executive board project'),
    ...(['president', 'secretary', 'treasurer'] as const).flatMap((role) => [
      {
        ...field(`${role}Id`, `${role} participant`, 'select'),
        options: options(state?.executiveBoardMembers),
      },
      {
        ...field(`${role}UserId`, `${role} cloud user`, 'select'),
        options: (state?.userGroupMembers ?? []).map((value) => ({ value, label: value })),
      },
      {
        ...field(`${role}GroupId`, `${role} group`, 'select'),
        options: options(state?.userGroups),
      },
      field(`${role}Email`, `${role} email`),
    ]),
  ]),
]
</script>
