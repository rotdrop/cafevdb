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
  userGroupMembers?: string[]|Record<string, string>
  userGroups?: Array<{ value: string, name: string }>
}>({ section: 'adminSettings', defaults: {} })
const field = (key: string, label: string, type: AdminSettingField['type'] = 'text'): AdminSettingField => ({
  key,
  label,
  type,
})
const group = (index: number, fallback: string, fields: AdminSettingField[]): AdminSettingGroup => ({
  id: props.entry.children[index]?.id ?? `group-${index}`,
  name: props.entry.children[index]?.name ?? fallback,
  fields,
})
const options = (values: Array<{ value: string|number, name: string }>|Record<string, { value: string|number, name: string }> = []) =>
  Object.values(values).map(({ value, name }) => ({ value, label: name }))

const groups: AdminSettingGroup[] = [
  group(0, t(appId, 'Street Address'), [
    field(STREET_ADDRESS_NAME_01, t(appId, 'Name')),
    field(STREET_ADDRESS_NAME_02, t(appId, 'Additional name')),
    field(STREET_ADDRESS_STREET, t(appId, 'Street')),
    field(STREET_ADDRESS_HOUSE_NUMBER, t(appId, 'House number')),
    field(STREET_ADDRESS_ZIP, t(appId, 'ZIP')),
    field(STREET_ADDRESS_CITY, t(appId, 'City')),
    {
      ...field(STREET_ADDRESS_COUNTRY, t(appId, 'Country'), 'select'),
      options: Object.entries(state?.localeCountryNames ?? {}).map(([value, label]) => ({ value, label })),
    },
    field('registerName', t(appId, 'Register name')),
    field('registerNumber', t(appId, 'Register number')),
    field('phoneNumber', t(appId, 'Phone number')),
  ]),
  group(1, t(appId, 'Bank Account'), [
    field(BANK_ACCOUNT_OWNER, t(appId, 'Account owner')),
    field(BANK_ACCOUNT_IBAN, t(appId, 'IBAN')),
    field(BANK_ACCOUNT_BLZ, t(appId, 'Bank code')),
    field(BANK_ACCOUNT_BIC, t(appId, 'BIC')),
    field(BANK_ACCOUNT_BANK_NAME, t(appId, 'Bank name')),
    field(BANK_ACCOUNT_CREDITOR_IDENTIFIER, t(appId, 'Creditor identifier')),
    field(BANK_ACCOUNT_BANK_HOLIDAYS, t(appId, 'Bank holiday region')),
    field(EVENT_DURATION_KEY, t(appId, 'Default event duration (minutes)'), 'number'),
  ]),
  group(2, t(appId, 'Document templates'), Object.entries(DOCUMENT_TEMPLATES).map(([key, info]) => ({
    key,
    label: t(appId, info.name),
    type: 'text' as const,
    initialValueKey: key + 'FileName',
  }))),
  group(3, t(appId, 'Executive board and club members'), [
    field('memberProject', t(appId, 'Club members project')),
    field('executiveBoardProject', t(appId, 'Executive board project')),
    ...([
      ['president', t(appId, 'President')],
      ['secretary', t(appId, 'Secretary')],
      ['treasurer', t(appId, 'Treasurer')],
    ] as const).flatMap(([role, roleName]) => [
      {
        ...field(`${role}Id`, t(appId, '{role} participant', { role: roleName }), 'select'),
        heading: roleName,
        options: options(state?.executiveBoardMembers),
      },
      {
        ...field(`${role}UserId`, t(appId, '{role} cloud user', { role: roleName }), 'select'),
        options: Object.values(state?.userGroupMembers ?? []).map((value) => ({ value, label: value })),
      },
      {
        ...field(`${role}GroupId`, t(appId, '{role} group', { role: roleName }), 'select'),
        options: options(state?.userGroups),
      },
      field(`${role}Email`, t(appId, '{role} email', { role: roleName })),
    ]),
  ]),
]
</script>
