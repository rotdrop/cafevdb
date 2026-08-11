<!--
 * Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2022-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  <div class="files-tab" :class="[...cloudVersionClasses]">
    <ul>
      <li class="files-tab-entry flex clickable"
          @click="(event) => toggleMenuHandlerHelper(event, mailMergeOperations)"
      >
        <div class="files-tab-entry__avatar icon-play-white" />
        <div class="files-tab-entry__desc">
          {{ t(appId, 'Mail merge operations') }}
        </div>
        <NcActions ref="mailMergeOperations"
                   :class="[{ merging: merging, loading: merging }]"
        >
          <NcActionButton v-tooltip="hints['templates:cloud:integration:download']"
                          icon="icon-download"
                          :disabled="senderId <= 0"
                          :closeAfterClick="true"
                          :title="t(appId, 'Download Merged Document')"
                          @click="mailMergeHandlerHelper(MailMergeDownload)($event)"
          >
            {{ t(appId, 'download locally') }}
          </NcActionButton>
          <NcActionButton v-tooltip="hints['templates:cloud:integration:cloudstore']"
                          :closeAfterClick="true"
                          :disabled="senderId <= 0"
                          :title="t(appId, 'Merge Document into Cloud')"
                          @click="mailMergeHandlerHelper(MailMergeCloud)($event)"
          >
            <template #icon>
              <CloudUploadIcon />
            </template>
            {{ t(appId, 'save to cloud') }}
          </NcActionButton>
          <NcActionButton v-tooltip="hints['templates:cloud:integration:dataset']"
                          :closeAfterClick="true"
                          :disabled="senderId <= 0"
                          :title="t(appId, 'Download Replacement Data')"
                          @click="mailMergeHandlerHelper(MailMergeDataset)($event)"
          >
            <template #icon>
              <CodeJsonIcon />
            </template>
            {{ t(appId, 'download data') }}
          </NcActionButton>
        </NcActions>
      </li>
      <li class="files-tab-entry flex">
        <div class="files-tab-entry__avatar icon-user-white" />
        <div class="files-tab-entry__desc">
          <h5>{{ t(appId, 'Sender') }}</h5>
        </div>
      </li>
      <li class="files-tab-entry">
        <SelectMusicians v-model="sender"
                         :label="t(appId, 'Musicians')"
                         :tooltip="{ content: senderTooltip, html: true }"
                         :class="[{ empty: senderId <= 0 }]"
                         :placeholder="t(appId, 'e.g. John Doe')"
                         :multiple="false"
                         :resetAction="true"
                         :clearAction="false"
                         :submitButton="false"
                         searchScope="executive-board"
                         :searchable="false"
        />
      </li>
      <li class="files-tab-entry flex clickable"
          @click="(event) => toggleMenuHandlerHelper(event, recipientsSourceMenu)"
      >
        <div class="files-tab-entry__avatar icon-group-white" />
        <div class="files-tab-entry__desc">
          <h5>{{ t(appId, 'Recipients') }}</h5>
        </div>
        <NcActions id="files-tabs-entry__recipients-base"
                   ref="recipientsSourceMenu"
        >
          <NcActionRadio v-model:modelValue="recipientsSource"
                         name="recipientsSource"
                         value="database"
                         :disabled="senderId <= 0"
                         @change="closeMenu(recipientsSourceMenu)"
          >
            {{ t(appId, 'Musician\'s Datebase') }}
          </NcActionRadio>
          <NcActionRadio v-model:modelValue="recipientsSource"
                         name="recipientsSource"
                         value="contacts"
                         :disabled="senderId <= 0"
                         @change="closeMenu(recipientsSourceMenu)"
          >
            {{ t(appId, 'Addressbooks') }}
          </NcActionRadio>
          <NcActionRadio v-if="false"
                         v-model:modelValue="recipientsSource"
                         name="recipientsSource"
                         value="input"
                         :closeAfterClick="true"
                         :disabled="true || senderId <= 0"
                         @change="closeMenu(recipientsSourceMenu)"
          >
            {{ t(appId, 'Enter Address') }}
          </NcActionRadio>
        </NcActions>
      </li>
      <li v-show="showDatabaseRecipients" class="files-tab-entry recipients__database">
        <SelectMusicians v-model="recipients"
                         :tooltip="recipients.length ? false : hints['templates:cloud:integration:recipients:musicians']"
                         :label="t(appId, 'Musicians')"
                         :placeholder="t(appId, 'e.g. Jane Doe')"
                         :multiple="true"
                         :submitButton="false"
                         :clearAction="true"
                         :projectId="projectId"
                         :disabled="senderId <= 0"
                         searchScope="musicians"
        />
        <SelectProjects v-model="project"
                        :tooltip="hints['templates:cloud:integration:project']"
                        :label="t(appId, 'Project')"
                        :placeholder="t(appId, 'e.g. Auvergne2019')"
                        :multiple="false"
                        :submitButton="false"
                        :clearAction="false"
                        :disabled="senderId <= 0"
        />
      </li>
      <li v-show="showAddressBookRecipients" class="files-tab-entry recipients__addressbooks">
        <SelectContacts v-model="contacts"
                        :tooltip="contacts.length ? undefined : hints['templates:cloud:integration:recipients:contacts']"
                        :label="t(appId, 'Contacts')"
                        :placeholder="t(appId, 'e.g. Bilbo Baggins')"
                        :multiple="true"
                        :clearAction="true"
                        :onlyAddressBooks="onlyAddressBooks"
                        :allAddressBooks="allAddressBooks"
                        :disabled="senderId <= 0"
                        :selectAllOption="false"
                        :submitButton="false"
                        searchScope="contacts"
        />
        <SelectAddressBooks v-model="onlyAddressBooks"
                            :tooltip="hints['templates:cloud:integration:address-books']"
                            :label="t(appId, 'Address-Books')"
                            :multiple="true"
                            :resetButton="true"
                            :clearButton="false"
                            :disabled="senderId <= 0"
                            @update:addressBooks="(books) => allAddressBooks = books"
        />
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import type { LegacyFileInfo } from '@nextcloud/files'
import type { FilesInitialState as InitialState, MailMergeResponse } from '../../build/ts-types/php-modules/Controller/DTO.ts'
import type { MusicianIdObject } from '../components/SelectMusicians.vue'
import type { Project } from '../stores/app-data.ts'
import type { FrontEndEntity } from '../toolkit/services/entity-factory.ts'
import type { AddressBook, Contact } from '../types/address-book.d.ts'
import type {
  ContactKeys,
  MailMergeOperation,
  MailMergePayload,
} from '../types/ajax/mail-merge.ts'

import axios from '@nextcloud/axios'
import { showError, showSuccess, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import {
  NcActionButton,
  NcActionRadio,
  NcActions,
} from '@nextcloud/vue'
import vTooltip from '@rotdrop/nextcloud-vue-components/lib/directives/Tooltip'
import md5 from 'blueimp-md5'
import {
  computed,
  onBeforeMount,
  reactive,
  ref,
  watch,
} from 'vue'
import CloudUploadIcon from 'vue-material-design-icons/CloudUpload.vue'
import CodeJsonIcon from 'vue-material-design-icons/CodeJson.vue'
// import DatabaseIcon from 'vue-material-design-icons/Database.vue'
// import ContactsIcon from 'vue-material-design-icons/Contacts.vue'
import SelectAddressBooks from '../components/SelectAddressBooks.vue'
import SelectContacts from '../components/SelectContacts.vue'
import SelectMusicians from '../components/SelectMusicians.vue'
import SelectProjects from '../components/SelectProjects.vue'
import { END_POINT as mailMergeEndPoint } from '../../build/ts-types/php-modules/Controller/MailMergeController.ts'
import { appName } from '../config.ts'
import axiosFileDownload from '../toolkit/util/axios-file-download.ts'
import cloudVersionClasses from '../toolkit/util/cloud-version-classes.ts'
import { generateUrl as generateAppUrl } from '../toolkit/util/generate-url.ts'
import getInitialState from '../toolkit/util/initial-state.ts'
import {
  MailMergeCloud,
  MailMergeDataset,
  MailMergeDownload,
} from '../types/ajax/mail-merge.ts'
import Console from '../util/console.ts'
import { tooltips } from '../util/tooltips.ts'

const COMPONENT_NAME = 'FilesTab'
const logger = new Console(COMPONENT_NAME)

type Musician = FrontEndEntity<'Musician'>

interface TargetedMouseEvent extends MouseEvent {
  target: HTMLInputElement
}

type NcActionsType = typeof NcActions

const fileInfo = ref<null|LegacyFileInfo>(null)
const sender = ref<undefined|MusicianIdObject>(undefined)
const project = ref<undefined|Project>(undefined)
const recipients = ref<Musician[]>([])
const allAddressBooks = ref<Record<string, AddressBook>>({})
const onlyAddressBooks = ref<AddressBook[]>([])
const contacts = ref<Contact[]>([])
const recipientsSource = ref<null | string>(null)
const merging = ref(false)
const hints = reactive({
  'templates:cloud:integration:sender': '',
  'templates:cloud:integration:recipients:musicians': '',
  'templates:cloud:integration:recipients:contacts': '',
  'templates:cloud:integration:address-books': '',
  'templates:cloud:integration:project': '',
  'templates:cloud:integration:download': '',
  'templates:cloud:integration:cloudstore': '',
  'templates:cloud:integration:dataset': '',
})

const appId = computed(() => appName)
const projectId = computed(() => project.value ? project.value.id : 0)
const senderId = computed(() => sender.value && sender.value.id ? sender.value.id : 0)
const recipientIds = computed(() => {
  try {
    return recipients.value.filter((recipient) => !!recipient.id || recipient.id === 0).map((recipient) => recipient.id)
  } catch {
    return []
  }
})
const contactKeys = computed((): ContactKeys[] => {
  try {
    return contacts.value
      .filter((contact) => !!contact.key || contact.key === 0)
      .map((contact) => {
        return {
          key: contact.key,
          uri: contact.URI,
          uid: contact.UID,
          book: contact['addressbook-key'],
        }
      })
  } catch {
    return []
  }
})
const addressBookUris = computed(() => {
  const uris = {}
  for (const book of onlyAddressBooks.value) {
    uris[book.key] = book.uri
  }
  return uris
})
const senderTooltip = computed(() => {
  const hint = hints['templates:cloud:integration:sender']
  if (senderId.value <= 0) {
    return '<span style="font-weight:bold;">' + t(appName, 'Required.') + '</span>'
      + ' '
      + hint
  }
  return false
})
const showDatabaseRecipients = computed(() => recipientsSource.value === 'database')
const showAddressBookRecipients = computed(() => recipientsSource.value === 'contacts')

// const showGivenRecipient = computed(() => recipientsSource.value === 'input')
// const showDatabaseRecipientsIcon = computed(() => {
//   if (loading.value) {
//     return 'icon-loading-small'
//   }
//   if (showDatabaseRecipients.value) {
//     return 'icon-triangle-n'
//   }
//   return 'icon-triangle-s'
// })
// const showAddressBookRecipientsIcon = computed(() => {
//   if (loading.value) {
//     return 'icon-loading-small'
//   }
//   if (showAddressBookRecipients.value) {
//     return 'icon-triangle-n'
//   }
//   return 'icon-triangle-s'
// })
// const showGivenRecipientIcon = computed(() => {
//   if (loading.value) {
//     return 'icon-loading-small'
//   }
//   if (showGivenRecipient.value) {
//     return 'icon-triangle-n'
//   }
//   return 'icon-triangle-s'
// })

watch(onlyAddressBooks, (newVal, oldVal) => logger.info('TOP ADDRESS BOOK WATCH', newVal, oldVal))

/**
 * Update current fileInfo and fetch new data.
 *
 * @param newFileInfo Fhe current file FileInfo.
 */
const update = async (newFileInfo: LegacyFileInfo) => {
  fileInfo.value = newFileInfo
  resetState()
}

defineExpose({
  update,
})

/**
 * Fetch some needed data ...
 */
let initialState: null|InitialState

const getData = async () => {
  initialState = getInitialState<InitialState>({ section: 'files' })
  if (initialState && initialState.personal.musicianId > 0) {
    sender.value = {
      id: initialState.personal.musicianId,
      publicName: initialState.personal.musicianPublicName!,
    }
  }
  logger.info('INITIAL STATE', initialState)
  logger.info('SENDER', sender.value)
  Object.assign(hints, await tooltips(Object.keys(hints)))
}

/**
 * Reset the current view to its default state
 */
function resetState() {
  sender.value = undefined
  recipients.value = []
  project.value = undefined
  if (initialState && initialState.personal.musicianId > 0) {
    sender.value = {
      id: initialState.personal.musicianId,
      publicName: initialState.personal.musicianPublicName!,
    }
  }
}

onBeforeMount(async () => {
  await getData()
  logger.info('SENDER ID', senderId.value)
})

// @todo Check with Vue3 whether this idiotic trampoline is still
// necessary, it only exists in order to satisfy the only half-working
// TS support.
const toggleMenuHandlerHelper = (event: MouseEvent, menu?: NcActionsType) => handleToggleMenu(event, menu!)

/**
 * @param event TBD.
 *
 * @param menu TBD.
 */
function handleToggleMenu(event: MouseEvent, menu: NcActionsType) {
  if ((event.target! as HTMLInputElement).closest('.action-item')) {
    return
  }
  if (menu.opened) {
    menu.closeMenu()
  } else {
    menu.openMenu()
  }
}

const closeMenu = (menu?: NcActionsType) => {
  menu!.closeMenu()
}

const recipientsSourceMenu = ref<NcActionsType>()
const mailMergeOperations = ref<NcActionsType>()

const mailMergeHandlerHelper = (operation: MailMergeOperation) =>
  (event: MouseEvent) => handleMailMergeRequest(operation, event as TargetedMouseEvent)

/**
 * @param operation TBD.
 *
 * @param  event TBD.
 */
async function handleMailMergeRequest(operation: MailMergeOperation, event: TargetedMouseEvent) {
  logger.info('MAIL MERGE', operation, event)
  logger.info('FILE', fileInfo.value)

  merging.value = true

  const postData: MailMergePayload = {
    fileName: fileInfo.value!.path + '/' + fileInfo.value!.name,
    senderId: sender.value!.id,
    projectId: projectId.value,
    recipientIds: recipientIds.value,
    addressBooksUris: addressBookUris.value,
    contactKeys: contactKeys.value,
    operation,
  }
  const ajaxUrl = generateAppUrl(mailMergeEndPoint)

  try {
    switch (operation) {
      case MailMergeDataset:
        postData.limit = 1 // maybe ...
        // fallthrough
      case MailMergeDownload:
        try {
          await axiosFileDownload(ajaxUrl, postData)
        } catch {
          showError(
            t(appName, 'File download failed for {fileName}.', { fileName: postData.fileName! }),
            { timeout: TOAST_PERMANENT_TIMEOUT },
          )
        }
        break
      case MailMergeCloud: {
        const response = await axios.post<MailMergeResponse>(ajaxUrl, postData)
        const cloudFolder = response.data.cloudFolder
        const message = response.data.messages.join(' ')
        logger.info('CLOUD RESPONSE', response)
        const folderLinkMessage = `<a class="external link ${appName}" target="${md5(cloudFolder)}" href="${generateUrl('apps/files')}?dir=${cloudFolder}"><span class="icon-external link-text" style="padding-left:20px;background-position:left;">${cloudFolder}/</span></a>`
        showSuccess(message + ' ' + folderLinkMessage, { isHTML: true, timeout: TOAST_PERMANENT_TIMEOUT })
        break
      }
    }
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
  } catch (e: any) {
    logger.error('ERROR', e)
    let message = t(appName, 'reason unknown')
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    let errorData: any = {}
    if (e.response) {
      errorData = e.response.data || {}
      if (
        e.request.responseType === 'blob'
        && errorData instanceof Blob
        && errorData.type
        && errorData.type.toLowerCase().indexOf('json') !== -1
      ) {
        try {
          errorData = JSON.parse(await errorData.text())
        } catch {
          errorData = {}
        }
      }
    } else if (e.request) {
      message = t(appName, 'no response received from {ajaxUrl}', { ajaxUrl })
    }
    logger.error('ERROR DATA', errorData)
    message = errorData.message || message
    showError(t(appName, 'Could not perform mail-merge: {message}', { message }), { timeout: TOAST_PERMANENT_TIMEOUT })
  }

  merging.value = false
}
</script>

<style lang="scss" scoped>
.files-tab {
  .bulk-operations {
    display: flex;
    align-items: center;
  }
  :deep(form.select-musicians) {
    &.empty .multiselect-vue {
      &, multiselect__tags {
        border:1px solid red;
      }
    }
  }
  .files-tab-entry {
    min-height:44px;
    &.flex {
      display:flex;
      align-items:center;
    }
    &.clickable {
      &, & * {
        cursor:pointer;
      }
    }
    .files-tab-entry__avatar {
      width: 32px;
      height: 32px;
      line-height: 32px;
      font-size: 18px;
      background-color: var(--color-text-maxcontrast);
      border-radius: 50%;
      flex-shrink: 0;
    }
    .files-tab-entry__desc {
      flex: 1 1;
      padding: 8px;
      line-height: 1.2em;
      min-width:0;
      h5 {
        white-space: nowrap;
        text-overflow: ellipsis;
        overflow: hidden;
        max-width: inherit;
      }
    }
  }
}
</style>
