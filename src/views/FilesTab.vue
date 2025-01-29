<!--
 * Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2022, 2023, 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  <div :class="['files-tab', ...cloudVersionClasses]">
    <ul>
      <li class="files-tab-entry flex clickable"
          @click="(event) => handleToggleMenu($refs.mailMergeOperations, event)"
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
                          :close-after-click="true"
                          :title="t(appId, 'Download Merged Document')"
                          @click="mailMergeHandlerHelper(MailMergeDownload)"
          >
            {{ t(appId, 'download locally') }}
          </NcActionButton>
          <NcActionButton v-tooltip="hints['templates:cloud:integration:cloudstore']"
                          :close-after-click="true"
                          :disabled="senderId <= 0"
                          :title="t(appId, 'Merge Document into Cloud')"
                          @click="mailMergeHandlerHelper(MailMergeCloud)"
          >
            <template #icon>
              <CloudUploadIcon />
            </template>
            {{ t(appId, 'save to cloud') }}
          </NcActionButton>
          <NcActionButton v-tooltip="hints['templates:cloud:integration:dataset']"
                          :close-after-click="true"
                          :disabled="senderId <= 0"
                          :title="t(appId, 'Download Replacement Data')"
                          @click="mailMergeHandlerHelper(MailMergeDataset)"
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
                         :tooltip="{ content: senderTooltip, html: true }"
                         :class="[{ empty: senderId <= 0 }]"
                         :placeholder="t(appId, 'e.g. John Doe')"
                         :multiple="false"
                         :reset-action="true"
                         :clear-action="false"
                         :submit-button="false"
                         search-scope="executive-board"
                         :searchable="false"
        />
      </li>
      <li class="files-tab-entry flex clickable"
          @click="(event) => handleToggleMenu($refs.recipientsSource, event)"
      >
        <div class="files-tab-entry__avatar icon-group-white" />
        <div class="files-tab-entry__desc">
          <h5>{{ t(appId, 'Recipients') }}</h5>
        </div>
        <NcActions id="files-tabs-entry__recipients-base"
                   ref="recipientsSource"
        >
          <NcActionRadio ref="radioDatabase"
                         name="recipientsSource"
                         value="database"
                         :checked="recipientsSource === 'database'"
                         :disabled="senderId <= 0"
                         @change="toggleRecipientsSource"
          >
            {{ t(appId, 'Musician\'s Datebase') }}
          </NcActionRadio>
          <NcActionRadio ref="radioContacts"
                         name="recipientsSource"
                         value="contacts"
                         :checked="recipientsSource === 'contacts'"
                         :disabled="senderId <= 0"
                         @change="toggleRecipientsSource"
          >
            {{ t(appId, 'Addressbooks') }}
          </NcActionRadio>
          <NcActionRadio v-if="false"
                         ref="givenContact"
                         name="recipientsSource"
                         value="input"
                         :checked="recipientsSource === 'input'"
                         :disabled="true || senderId <= 0"
                         @change="toggleRecipientsSource"
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
                         :submit-button="false"
                         :clear-action="true"
                         :project-id="projectId"
                         :disabled="senderId <= 0"
                         search-scope="musicians"
        />
        <SelectProjects v-model="project"
                        :tooltip="hints['templates:cloud:integration:project']"
                        :label="t(appId, 'Project')"
                        :placeholder="t(appId, 'e.g. Auvergne2019')"
                        :multiple="false"
                        :submit-button="false"
                        :clear-action="false"
                        :disabled="senderId <= 0"
        />
      </li>
      <li v-show="showAddressBookRecipients" class="files-tab-entry recipients__addressbooks">
        <SelectContacts v-model="contacts"
                        :tooltip="contacts.length ? undefined : hints['templates:cloud:integration:recipients:contacts']"
                        :label="t(appId, 'Contacts')"
                        :placeholder="t(appId, 'e.g. Bilbo Baggins')"
                        :multiple="true"
                        :clear-action="true"
                        :only-address-books="onlyAddressBooks"
                        :all-address-books="allAddressBooks"
                        :disabled="senderId <= 0"
                        :select-all-option="false"
                        :submit-button="false"
                        search-scope="contacts"
        />
        <SelectAddressBooks v-model="onlyAddressBooks"
                            :tooltip="hints['templates:cloud:integration:address-books']"
                            :label="t(appId, 'Address-Books')"
                            :multiple="true"
                            :reset-button="true"
                            :clear-button="false"
                            :disabled="senderId <= 0"
                            @update:address-books="(books) => allAddressBooks = books"
        />
      </li>
    </ul>
  </div>
</template>
<script lang="ts">
import { appName } from '../config.ts'
import cloudVersionClasses from '../toolkit/util/cloud-version-classes.js'
import Vue from 'vue'
import {
  NcActions,
  NcActionButton,
  NcActionRadio,
  Tooltip,
} from '@nextcloud/vue'
import { createPinia, PiniaVuePlugin } from 'pinia'
import CloudUploadIcon from 'vue-material-design-icons/CloudUpload.vue'
import CodeJsonIcon from 'vue-material-design-icons/CodeJson.vue'
// import DatabaseIcon from 'vue-material-design-icons/Database.vue'
// import ContactsIcon from 'vue-material-design-icons/Contacts.vue'
import axios from '@nextcloud/axios'
import { showError, showSuccess, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { generateUrl as generateAppUrl } from '../toolkit/util/generate-url.js'
import { generateUrl } from '@nextcloud/router'
import { getInitialState } from '../services/initial-state-service.ts'
import SelectContacts from '../components/SelectContacts.vue'
import SelectAddressBooks from '../components/SelectAddressBooks.vue'
import SelectMusicians from '../components/SelectMusicians.vue'
import SelectProjects from '../components/SelectProjects.vue'
import fileDownload from '../services/axios-file-download.ts'
import tooltips from '../mixins/tooltips.ts'
import consoleMixin from '../mixins/console.ts'
import l10nMixin from '../mixins/l10n.ts'
import md5 from 'blueimp-md5'
import type { LegacyFileInfo } from '@nextcloud/files'
import type { Project } from '../stores/app-data.ts'
import type { Contact, AddressBook, Musician } from '../components/types/address-book.d.ts'

Vue.mixin({ data() { return { appName } }, methods: { t, n } })
Vue.directive('tooltip', Tooltip)
Vue.use(PiniaVuePlugin)
const pinia = createPinia()

export {
  Vue,
  pinia,
}

interface InitialState {
  personal: {
    userId: string,
    musicianId: number,
  },
}

type MusicianModel = {
  id: number
}

const MailMergeDataset = 'dataset'
const MailMergeDownload = 'downlaod'
const MailMergeCloud = 'cloud'
type MailMergeOperations = typeof MailMergeDataset
  | typeof MailMergeDownload
  | typeof MailMergeCloud

type ContactKeys = {
  key: string|number,
  uri: string|undefined,
  uid: string,
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  book: any,
}

type MailMergePayload = {
  fileId: number,
  fileName: string,
  senderId: number,
  projectId: number,
  recipientIds: (number|string)[],
  addressBooksUris: Record<string, string>,
  contactKeys: ContactKeys[],
  operation: MailMergeOperations,
  limit?: number,
}

interface RadioInputEvent extends Event {
  target: HTMLInputElement,
}

interface TargetedMouseEvent extends MouseEvent {
  target: HTMLInputElement,
}

export default {
  name: 'FilesTab',
  components: {
    // AppSidebar,
    // AppSidebarTab,
    // ContactsIcon,
    // DatabaseIcon,
    CloudUploadIcon,
    CodeJsonIcon,
    NcActionButton,
    NcActionRadio,
    NcActions,
    SelectAddressBooks,
    SelectContacts,
    SelectMusicians,
    SelectProjects,
  },
  mixins: [
    tooltips,
    consoleMixin,
    l10nMixin,
  ],
  props: {},
  data() {
    return {
      cloudVersionClasses,
      fileInfo: null as null|LegacyFileInfo,
      sender: null as null|MusicianModel,
      project: null as null|Project,
      recipients: [] as Musician[],
      allAddressBooks: {},
      onlyAddressBooks: [] as AddressBook[],
      contacts: [] as Contact[],
      hints: {
        'templates:cloud:integration:sender': '',
        'templates:cloud:integration:recipients:musicians': '',
        'templates:cloud:integration:recipients:contacts': '',
        'templates:cloud:integration:address-books': '',
        'templates:cloud:integration:project': '',
        'templates:cloud:integration:download': '',
        'templates:cloud:integration:cloudstore': '',
        'templates:cloud:integration:dataset': '',
      },
      initialState: {} as InitialState,
      merging: false,
      recipientsSource: null as null|string,
    }
  },
  computed: {
    appId() {
      return appName
    },
    projectId() {
      try {
        return this.project!.id
      } catch (ignoreMe) {
        return 0
      }
    },
    senderId() {
      if (this.sender && this.sender.id) {
        return this.sender.id
      } else {
        return 0
      }
    },
    recipientIds() {
      try {
        return this.recipients.filter((recipient) => !!recipient.id || recipient.id === 0).map((recipient) => recipient.id)
      } catch (ignoreMe) {
        return []
      }
    },
    contactKeys(): ContactKeys[] {
      try {
        return this
          .contacts
          .filter((contact) => !!contact.key || contact.key === 0)
          .map((contact) => {
            return {
              key: contact.key,
              uri: contact.URI,
              uid: contact.UID,
              book: contact['addressbook-key'],
            }
          })
      } catch (ignoreMe) {
        return []
      }
    },
    addressBookUris() {
      const uris = {}
      for (const book of this.onlyAddressBooks) {
        uris[book.key] = book.uri
      }
      return uris
    },
    senderTooltip() {
      const hint = this.hints['templates:cloud:integration:sender']
      if (this.senderId <= 0) {
        return '<span style="font-weight:bold;">' + t(appName, 'Required.') + '</span>'
             + ' '
             + hint
      }
      return false
    },
    showDatabaseRecipients() {
      return this.recipientsSource === 'database'
    },
    showAddressBookRecipients() {
      return this.recipientsSource === 'contacts'
    },
    showGivenRecipient() {
      return this.recipientsSource === 'input'
    },
    showDatabaseRecipientsIcon() {
      if (this.loading) {
        return 'icon-loading-small'
      }
      if (this.showDatabaseRecipients) {
        return 'icon-triangle-n'
      }
      return 'icon-triangle-s'
    },
    showAddressBookRecipientsIcon() {
      if (this.loading) {
        return 'icon-loading-small'
      }
      if (this.showAddressBookRecipients) {
        return 'icon-triangle-n'
      }
      return 'icon-triangle-s'
    },
    showGivenRecipientIcon() {
      if (this.loading) {
        return 'icon-loading-small'
      }
      if (this.showGivenRecipient) {
        return 'icon-triangle-n'
      }
      return 'icon-triangle-s'
    },
  },
  watch: {
    onlyAddressBooks(newVal, oldVal) {
      this.info('TOP ADDRESS BOOK WATCH', newVal, oldVal)
    },
  },
  created() {
    this.getData()
    this.info('SENDER ID', this.senderId)
  },
  methods: {
    /**
     * Update current fileInfo and fetch new data.
     *
     * @param {object} fileInfo Fhe current file FileInfo.
     */
    async update(fileInfo: LegacyFileInfo) {
      this.fileInfo = fileInfo
      this.resetState()
    },
    /**
     * Fetch some needed data ...
     */
    async getData() {
      this.initialState = getInitialState()
      if (this.initialState.personal.musicianId > 0) {
        this.sender = { id: this.initialState.personal.musicianId }
      }
      this.info('INITIAL STATE', this.initialState)
      this.info('SENDER', this.sender)
      this.hints = await this.tooltips(Object.keys(this.hints))
    },
    handleToggleMenu(menu: typeof NcActions, event: MouseEvent) {
      if ((event as TargetedMouseEvent).target.closest('.action-item')) {
        return
      }
      if (menu.opened) {
        menu.closeMenu()
      } else {
        menu.openMenu()
      }
    },
    toggleRecipientsSource(event: RadioInputEvent) {
      this.info('RECIPIENTS', this.recipientsSource)
      this.info('EVENT', event)
      this.recipientsSource = event!.target.value
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const blah = this.$refs!.recipientsSource as any
      blah.closeMenu()
    },
    mailMergeHandlerHelper(operation: MailMergeOperations) {
      return (event: MouseEvent) => this.handleMailMergeRequest(operation, event as TargetedMouseEvent);
    },
    async handleMailMergeRequest(operation: MailMergeOperations, event: TargetedMouseEvent) {
      this.info('MAIL MERGE', operation, event)
      this.info('FILE', this.fileInfo)

      this.merging = true

      const postData: MailMergePayload = {
        fileId: this.fileInfo!.id,
        fileName: this.fileInfo!.path + '/' + this.fileInfo!.name,
        senderId: this.sender!.id,
        projectId: this.projectId,
        recipientIds: this.recipientIds,
        addressBooksUris: this.addressBookUris,
        contactKeys: this.contactKeys,
        operation,
      }
      const ajaxUrl = generateAppUrl('documents/mail-merge')

      try {
        switch (operation) {
        case MailMergeDataset:
          postData.limit = 1 // maybe ...
          // fallthrough
        case MailMergeDownload:
          await fileDownload(ajaxUrl, postData)
          break
        case MailMergeCloud: {
          const response = await axios.post(ajaxUrl, postData)
          const cloudFolder = response.data.cloudFolder
          const message = response.data.message
          this.info('CLOUD RESPONSE', response)
          const folderLinkMessage = `<a class="external link ${appName}" target="${md5(cloudFolder)}" href="${generateUrl('apps/files')}?dir=${cloudFolder}"><span class="icon-external link-text" style="padding-left:20px;background-position:left;">${cloudFolder}/</span></a>`
          showSuccess(message + ' ' + folderLinkMessage, { isHTML: true, timeout: TOAST_PERMANENT_TIMEOUT })
          break
        }
        }
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
      } catch (e: any) {
        this.error('ERROR', e)
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
            } catch (ignoreMe) {
              errorData = {}
            }
          }
        } else if (e.request) {
          message = t(appName, 'no response received from {ajaxUrl}', { ajaxUrl })
        }
        this.error('ERROR DATA', errorData)
        message = errorData.message || message
        showError(t(appName, 'Could not perform mail-merge: {message}', { message }), { timeout: TOAST_PERMANENT_TIMEOUT })
      }

      this.merging = false
    },
    /**
     * Reset the current view to its default state
     */
    resetState() {
      this.sender = null
      this.recipients = []
      this.project = null
      if (this.initialState.personal.musicianId > 0) {
        this.sender = { id: this.initialState.personal.musicianId }
      }
    },
  },
}
</script>
<style lang="scss" scoped>
.files-tab {
  .bulk-operations {
    display: flex;
    align-items: center;
  }
  &::v-deep form.select-musicians {
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
