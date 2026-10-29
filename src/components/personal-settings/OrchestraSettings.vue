<template>
  <AdminSettingsTab :entry="entry" :groups="groups" @error="emit('error', $event)">
    <template v-for="template in documentTemplates" :key="template" #[`field-${template}`]="{ value, setValue, settings }">
      <NcButton v-tooltip="hints[`templates:upload:cloud:${template}`]"
                :aria-label="t(appId, 'Choose a template file from the cloud storage.')"
                :disabled="!!busy[template] || !templateFolder(template, settings)"
                @click="chooseCloudTemplate(template, settings, setValue)"
      >
        <template #icon>
          <CloudIcon :size="20" />
        </template>
      </NcButton>
      <NcButton v-if="DOCUMENT_TEMPLATES[template].type === DOCUMENT_TYPE_TEMPLATE"
                v-tooltip="hints['templates:auto-fill-test']"
                :aria-label="t(appId, 'Auto-fill test')"
                :disabled="!value || !!busy[template]"
                @click="download(template, EnumSettingsGetApp.AUTO_FILL_TEST, { format: 'native' })"
      >
        <template #icon>
          <FileDocumentIcon :size="20" />
        </template>
      </NcButton>
      <NcButton v-if="DOCUMENT_TEMPLATES[template].type === DOCUMENT_TYPE_TEMPLATE"
                v-tooltip="hints['templates:auto-fill-test:pdf']"
                :aria-label="t(appId, 'Auto-fill test (PDF)')"
                :disabled="!value || !!busy[template]"
                @click="download(template, EnumSettingsGetApp.AUTO_FILL_TEST, { format: 'pdf' })"
      >
        <template #icon>
          <FilePdfIcon :size="20" />
        </template>
      </NcButton>
      <NcButton v-if="DOCUMENT_TEMPLATES[template].type === DOCUMENT_TYPE_TEMPLATE"
                v-tooltip="hints['templates:auto-fill-test:data']"
                :aria-label="t(appId, 'Test data')"
                :disabled="!!busy[template]"
                @click="download(template, EnumSettingsGetApp.AUTO_FILL_TEST_DATA)"
      >
        <template #icon>
          <DownloadIcon :size="20" />
        </template>
      </NcButton>
      <span class="document-template-delete">
        <NcButton v-tooltip="hints['templates:delete']"
                  :aria-label="t(appId, 'Delete')"
                  :disabled="!value || !!busy[template]"
                  @click="remove(template, setValue)"
        >
          <template #icon>
            <DeleteIcon :size="20" />
          </template>
        </NcButton>
      </span>
    </template>
  </AdminSettingsTab>
</template>

<script setup lang="ts">
import type { AdminSettingField, AdminSettingGroup } from './AdminSettingsTab.vue'
import type { TabEntry } from './settings-tree.ts'

import { FilePickerClosed, getFilePickerBuilder } from '@nextcloud/dialogs'
import { emit as emitFileEvent } from '@nextcloud/event-bus'
import { FileType } from '@nextcloud/files'
import { defaultRootPath, getClient, getDefaultPropfind, resultToNode } from '@nextcloud/files/dav'
import { translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import { reactive } from 'vue'
import CloudIcon from 'vue-material-design-icons/Cloud.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import FileDocumentIcon from 'vue-material-design-icons/FileDocument.vue'
import FilePdfIcon from 'vue-material-design-icons/FilePdfBox.vue'
import AdminSettingsTab from './AdminSettingsTab.vue'
import { EnumSettingsGetApp } from '../../../build/ts-types/php-modules/Controller.ts'
import {
  BANK_ACCOUNT_BANK_HOLIDAYS,
  BANK_ACCOUNT_BANK_NAME,
  BANK_ACCOUNT_BIC,
  BANK_ACCOUNT_BLZ,
  BANK_ACCOUNT_CREDITOR_IDENTIFIER,
  BANK_ACCOUNT_IBAN,
  BANK_ACCOUNT_OWNER,
  DOCUMENT_TEMPLATES,
  DOCUMENT_TEMPLATES_FOLDER,
  DOCUMENT_TYPE_TEMPLATE,
  EVENT_DURATION_KEY,
  SHARED_FOLDER,
  STREET_ADDRESS_CITY,
  STREET_ADDRESS_COUNTRY,
  STREET_ADDRESS_HOUSE_NUMBER,
  STREET_ADDRESS_NAME_01,
  STREET_ADDRESS_NAME_02,
  STREET_ADDRESS_STREET,
  STREET_ADDRESS_ZIP,
} from '../../../build/ts-types/php-modules/Settings/ConfigConstants.ts'
import { getAppUrl } from '../../app/settings-urls.ts'
import { appName as appId } from '../../config.ts'
import { setAdminSetting } from '../../services/admin-settings.ts'
import { handleSettingError, showSettingFeedback } from '../../services/setting-feedback.ts'
import useTooltipsStore from '../../stores/tooltips.ts'
import axiosFileDownload from '../../toolkit/util/axios-file-download.ts'
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

type DocumentTemplate = keyof typeof DOCUMENT_TEMPLATES
type SetTemplateValue = (value: unknown, downloadLink?: string) => void
const documentTemplates = Object.keys(DOCUMENT_TEMPLATES) as DocumentTemplate[]
const tooltipsProvider = useTooltipsStore()
tooltipsProvider.provideTooltips([
  'templates:delete',
  'templates:auto-fill-test',
  'templates:auto-fill-test:pdf',
  'templates:auto-fill-test:data',
  ...documentTemplates.map((template) => `templates:upload:cloud:${template}`),
])
const hints = tooltipsProvider.tooltipsData

const templateFolder = (template: DocumentTemplate, settings: Record<string, unknown>) => {
  if (!settings[SHARED_FOLDER] || !settings[DOCUMENT_TEMPLATES_FOLDER]) {
    return ''
  }
  const subFolderKey = DOCUMENT_TEMPLATES[template].folder
  return '/' + [
    settings[SHARED_FOLDER],
    settings[DOCUMENT_TEMPLATES_FOLDER],
    subFolderKey ? settings[subFolderKey] : '',
  ].filter(Boolean).join('/').split('/').filter(Boolean).join('/') + '/'
}

const busy = reactive<Record<string, boolean>>({})
const chooseCloudTemplate = async (
  template: DocumentTemplate,
  settings: Record<string, unknown>,
  setValue: SetTemplateValue,
) => {
  const folder = templateFolder(template, settings)
  if (!folder || busy[template]) {
    return
  }
  busy[template] = true
  try {
    let move = false
    const picker = getFilePickerBuilder(t(appId, 'Choose a template file from the cloud storage.'))
      .setMultiSelect(false)
      .allowDirectories(false)
      .setButtonFactory((nodes) => [
        {
          label: t(appId, 'Copy'),
          variant: 'secondary',
          disabled: nodes.length !== 1 || nodes[0]?.type !== FileType.File,
          callback: () => { move = false },
        },
        {
          label: t(appId, 'Move'),
          variant: 'primary',
          disabled: nodes.length !== 1 || nodes[0]?.type !== FileType.File,
          callback: () => { move = true },
        },
      ])
      .build()
    const [node] = await picker.pickNodes()
    const sourcePath = `${node.root}${node.path}`
    const destinationPath = `${defaultRootPath}${folder}${node.basename}`
    const client = getClient()
    if (sourcePath !== destinationPath) {
      if (move) {
        await client.moveFile(sourcePath, destinationPath)
      } else {
        await client.copyFile(sourcePath, destinationPath)
      }
    }
    const result = await client.stat(destinationPath, { data: getDefaultPropfind() })
    const newNode = resultToNode('data' in result ? result.data : result)
    if (sourcePath !== destinationPath) {
      emitFileEvent(move ? 'files:node:updated' : 'files:node:created', newNode)
    }
    const response = await setAdminSetting(template, newNode.basename)
    setValue(newNode.basename, newNode.source)
    showSettingFeedback(response.data, t(appId, DOCUMENT_TEMPLATES[template].name))
  } catch (error) {
    if (!(error instanceof FilePickerClosed)) {
      handleSettingError(error, (forwarded) => emit('error', forwarded))
    }
  } finally {
    busy[template] = false
  }
}

const download = async (
  template: string,
  endpoint: EnumSettingsGetApp,
  extra: Record<string, string> = {},
) => {
  busy[template] = true
  try {
    await axiosFileDownload(getAppUrl(endpoint), { documentTemplate: template, ...extra })
  } catch (error) {
    handleSettingError(error, (forwarded) => emit('error', forwarded))
  } finally {
    busy[template] = false
  }
}

const remove = async (template: string, setValue: SetTemplateValue) => {
  busy[template] = true
  try {
    const response = await setAdminSetting(`${template}Delete`, '')
    setValue('')
    showSettingFeedback(response.data, template)
  } catch (error) {
    handleSettingError(error, (forwarded) => emit('error', forwarded))
  } finally {
    busy[template] = false
  }
}

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
    downloadLinkKey: key + 'DownloadLink',
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

<style lang="scss" scoped>
.document-template-delete {
  display: inline-flex;
  margin-inline-start: var(--default-grid-baseline, 4px);
  padding-inline-start: calc(2 * var(--default-grid-baseline, 4px));
  border-inline-start: 1px solid var(--color-border);
}
</style>
