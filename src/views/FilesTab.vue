<!--
 * Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2022, 2023, 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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
          @click="handleToggleMenu($refs.mailMergeOperations, ...arguments)"
      >
        <div class="files-tab-entry__avatar icon-play-white" />
        <div class="files-tab-entry__desc">
          {{ t(appName, 'Mail merge operations') }}
        </div>
        <Actions ref="mailMergeOperations"
                 :class="[{ merging: merging, loading: merging }]"
        >
          <ActionButton v-tooltip="hints['templates:cloud:integration:download']"
                        icon="icon-download"
                        :disabled="senderId <= 0"
                        :close-after-click="true"
                        :title="t(appName, 'Download Merged Document')"
                        @click="handleMailMergeRequest('download', ...arguments)"
          >
            <!-- {{ hints['templates:cloud:integration:download'] }} -->
          </ActionButton>
          <ActionButton v-tooltip="hints['templates:cloud:integration:cloudstore']"
                        :close-after-click="true"
                        :disabled="senderId <= 0"
                        :title="t(appName, 'Merge Document into Cloud')"
                        @click="handleMailMergeRequest('cloud', ...arguments)"
          >
            <template #icon>
              <CloudIcon />
            </template>
            <!-- {{ hints['templates:cloud:integration:cloudstore'] }} -->
          </ActionButton>
          <ActionButton v-tooltip="hints['templates:cloud:integration:dataset']"
                        :close-after-click="true"
                        :disabled="senderId <= 0"
                        :title="t(appName, 'Download Replacement Data')"
                        @click="handleMailMergeRequest('dataset', ...arguments)"
          >
            <template #icon>
              <CodeJsonIcon />
            </template>
          </ActionButton>
        </Actions>
      </li>
      <li class="files-tab-entry flex">
        <div class="files-tab-entry__avatar icon-user-white" />
        <div class="files-tab-entry__desc">
          <h5>{{ t(appName, 'Sender') }}</h5>
        </div>
      </li>
      <li class="files-tab-entry">
        <SelectMusicians v-model="sender"
                         :tooltip="{ content: senderTooltip, html: true }"
                         :class="[{ empty: senderId <= 0 }]"
                         :placeholder="t(appName, 'e.g. John Doe')"
                         :multiple="false"
                         :reset-button="true"
                         :clear-button="false"
                         search-scope="executive-board"
                         open-direction="bottom"
                         :searchable="false"
        />
      </li>
      <li class="files-tab-entry flex clickable"
          @click="handleToggleMenu($refs.recipientsSource, ...arguments)"
      >
        <div class="files-tab-entry__avatar icon-group-white" />
        <div class="files-tab-entry__desc">
          <h5>{{ t(appName, 'Recipients') }}</h5>
        </div>
        <Actions id="files-tabs-entry__recipients-base"
                 ref="recipientsSource"
        >
          <ActionRadio ref="radioDatabase"
                       name="recipientsSource"
                       value="database"
                       :checked="recipientsSource === 'database'"
                       :disabled="senderId <= 0"
                       @change="toggleRecipientsSource"
          >
            {{ t(appName, 'Musician\'s Datebase') }}
          </ActionRadio>
          <ActionRadio ref="radioContacts"
                       name="recipientsSource"
                       value="contacts"
                       :checked="recipientsSource === 'contacts'"
                       :disabled="senderId <= 0"
                       @change="toggleRecipientsSource"
          >
            {{ t(appName, 'Addressbooks') }}
          </ActionRadio>
          <ActionRadio v-if="false"
                       ref="givenContact"
                       name="recipientsSource"
                       value="input"
                       :checked="recipientsSource === 'input'"
                       :disabled="true || senderId <= 0"
                       @change="toggleRecipientsSource"
          >
            {{ t(appName, 'Enter Address') }}
          </ActionRadio>
        </Actions>
      </li>
      <li v-show="showDatabaseRecipients" class="files-tab-entry recipients__database">
        <SelectMusicians v-model="recipients"
                         :tooltip="recipients.length ? false : hints['templates:cloud:integration:recipients:musicians']"
                         :label="t(appName, 'Musicians')"
                         :placeholder="t(appName, 'e.g. Jane Doe')"
                         :multiple="true"
                         :clear-button="true"
                         :project-id="projectId"
                         :disabled="senderId <= 0"
                         search-scope="musicians"
        />
        <SelectProjects v-model="project"
                        :tooltip="hints['templates:cloud:integration:project']"
                        :label="t(appName, 'Project')"
                        :placeholder="t(appName, 'e.g. Auvergne2019')"
                        :multiple="false"
                        :clear-button="false"
                        :disabled="senderId <= 0"
        />
      </li>
      <li v-show="showAddressBookRecipients" class="files-tab-entry recipients__addressbooks">
        <SelectContacts v-model="contacts"
                        :tooltip="contacts.length ? false : hints['templates:cloud:integration:recipients:contacts']"
                        :label="t(appName, 'Contacts')"
                        :placeholder="t(appName, 'e.g. Bilbo Baggins')"
                        :multiple="true"
                        :clear-button="true"
                        :only-address-books="onlyAddressBooks"
                        :all-address-books="allAddressBooks"
                        :disabled="senderId <= 0"
                        :select-all-option="false"
                        search-scope="contacts"
        />
        <SelectAddressBooks v-model="onlyAddressBooks"
                            :tooltip="hints['templates:cloud:integration:address-books']"
                            :label="t(appName, 'Address-Books')"
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
<script>
import { appName } from '../app/app-info.js'
import cloudVersionClasses from '../toolkit/util/cloud-version-classes.js'
import {
  NcActions as Actions,
  NcActionButton as ActionButton,
  NcActionRadio as ActionRadio,
  // NcAppSidebar as AppSidebar,
  // NcAppSidebarTab as AppSidebarTab,
} from '@nextcloud/vue'
// import ActionRadio from '../components/action-radio/NcActionRadio'
import CloudIcon from 'vue-material-design-icons/Cloud.vue'
import CodeJsonIcon from 'vue-material-design-icons/CodeJson.vue'
// import DatabaseIcon from 'vue-material-design-icons/Database.vue'
// import ContactsIcon from 'vue-material-design-icons/Contacts.vue'
import axios from '@nextcloud/axios'
import { showError, showSuccess, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import { getInitialState } from '../services/initial-state-service.js'
import SelectContacts from '../components/SelectContacts.vue'
import SelectAddressBooks from '../components/SelectAddressBooks.vue'
import SelectMusicians from '../components/SelectMusicians.vue'
import SelectProjects from '../components/SelectProjects.vue'
import fileDownload from '../services/axios-file-download.js'
import tooltip from '../mixins/tooltips.js'
import md5 from 'blueimp-md5'

export default {
  name: 'FilesTab',
  components: {
    // AppSidebar,
    // AppSidebarTab,
    SelectContacts,
    SelectAddressBooks,
    SelectMusicians,
    SelectProjects,
    Actions,
    ActionButton,
    ActionRadio,
    CloudIcon,
    CodeJsonIcon,
    // DatabaseIcon,
    // ContactsIcon,
  },
  mixins: [
    tooltip,
  ],
  data() {
    return {
      cloudVersionClasses,
      sender: '',
      project: '',
      recipients: [],
      allAddressBooks: {},
      onlyAddressBooks: [],
      contacts: [],
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
      initialState: {},
      merging: false,
      recipientsSource: null,
    }
  },
  /* watch: {
   *   onlyAddressBooks(newVal, oldVal) {
   *     console.info('TOP ADDRESS BOOK WATCH', newVal, oldVal)
   *   },
   * }, */
  computed: {
    projectId() {
      try {
        return this.project.id
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
    contactKeys() {
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
  created() {
    this.getData()
    console.info('SENDER ID', this.senderId)
  },
  // watch: {
  //   project(newVal, oldVal) {
  //    console.info('NEW PROJECT', newVal, oldVal)
  //   },
  // },
  methods: {
    info(...args) {
      console.info.apply(null, ...args)
    },
    /**
     * Update current fileInfo and fetch new data.
     *
     * @param {object} fileInfo Fhe current file FileInfo.
     */
    async update(fileInfo) {
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
      console.info('INITIAL STATE', this.initialState)
      console.info('SENDER', this.sender)
      this.hints = await this.tooltips(Object.keys(this.hints))
    },
    handleToggleMenu(menu, event) {
      if (event.target.closest('.action-item')) {
        return
      }
      if (menu.opened) {
        menu.closeMenu()
      } else {
        menu.openMenu()
      }
    },
    toggleRecipientsSource(event) {
      console.info('EVENT', event)
      this.recipientsSource = event.target.value
      console.info('RECIPIENTS', this.recipientsSource)
      this.$refs.recipientsSource.closeMenu()
    },
    async handleMailMergeRequest(operation, ...args) {
      console.info('MAIL MERGE', operation, ...args)
      console.info('FILE', this.fileInfo)

      this.merging = true

      const postData = {
        fileId: this.fileInfo.id,
        fileName: this.fileInfo.path + '/' + this.fileInfo.name,
        senderId: this.sender.id,
        projectId: this.projectId,
        recipientIds: this.recipientIds,
        addressBooksUris: this.addressBookUris,
        contactKeys: this.contactKeys,
        operation,
      }
      const ajaxUrl = generateUrl('/apps/' + appName + '/documents/mail-merge')

      try {
        switch (operation) {
        case 'dataset':
          postData.limit = 1 // maybe ...
          // fallthrough
        case 'download':
          await fileDownload(ajaxUrl, postData)
          break
        case 'cloud': {
          const response = await axios.post(ajaxUrl, postData)
          const cloudFolder = response.data.cloudFolder
          const message = response.data.message
          console.info('CLOUD RESPONSE', response)
          const folderLinkMessage = `<a class="external link ${appName}" target="${md5(cloudFolder)}" href="${generateUrl('apps/files')}?dir=${cloudFolder}"><span class="icon-external link-text" style="padding-left:20px;background-position:left;">${cloudFolder}/</span></a>`
          showSuccess(message + ' ' + folderLinkMessage, { isHTML: true, timeout: TOAST_PERMANENT_TIMEOUT })
          break
        }
        }
      } catch (e) {
        console.error('ERROR', e)
        let message = t(appName, 'reason unknown')
        let errorData = {}
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
        console.error('ERROR DATA', errorData)
        message = errorData.message || message
        showError(t(appName, 'Could not perform mail-merge: {message}', { message }), { timeout: TOAST_PERMANENT_TIMEOUT })
      }

      this.merging = false
    },
    /**
     * Reset the current view to its default state
     */
    resetState() {
      this.sender = ''
      this.recipients = []
      this.project = ''
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
