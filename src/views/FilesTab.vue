<script>
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
</script>
<template>
  <div class="files-tab">
    <div class="bulk-operations">
      <span class="bulk-operations-title">{{ t(appName, 'Mail merge operation:') }}</span>
      <Actions :class="[{ merging: merging, loading: merging }]">
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
            <Cloud />
          </template>
          <!-- {{ hints['templates:cloud:integration:cloudstore'] }} -->
        </ActionButton>
      </Actions>
    </div>
    <SelectMusicians v-model="sender"
                     v-tooltip="senderTooltip"
                     :class="[{ empty: senderId <= 0 }]"
                     :label="t(appName, 'Sender')"
                     :multiple="false"
                     :submit-button="false"
                     search-scope="executive-board"
                     open-direction="bottom"
                     :searchable="false"
    />
    <SelectProjects v-model="project"
                    v-tooltip="hints['templates:cloud:integration:project']"
                    :label="t(appName, 'Project')"
                    :multiple="false"
                    :submit-button="false"
                    :disabled="senderId <= 0"
                    open-direction="bottom"
    />
    <SelectMusicians v-model="recipients"
                     v-tooltip="hints['templates:cloud:integration:recipients']"
                     :label="t(appName, 'Recipients')"
                     :multiple="true"
                     :submit-button="false"
                     :project-id="projectId"
                     :disabled="senderId <= 0"
                     open-direction="bottom"
                     search-scope="musicians"
    />
  </div>
</template>
<script>

import { appName } from '../app/app-info.js'
import { getCurrentUser as getCloudUser } from '@nextcloud/auth/dist/user'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import Cloud from 'vue-material-design-icons/Cloud'
import axios from '@nextcloud/axios'
import { showError, showSuccess, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import { getInitialState } from '../services/initial-state-service'
import SelectMusicians from '../components/SelectMusicians'
import SelectProjects from '../components/SelectProjects'
import fileDownload from '../services/axios-file-download.js'
import tooltip from '../mixins/tooltips.js'
import md5 from 'blueimp-md5'

export default {
  name: 'FilesTab',
  components: {
    AppSidebar,
    AppSidebarTab,
    SelectMusicians,
    SelectProjects,
    Actions,
    ActionButton,
    Cloud,
  },
  mixins: [
    tooltip,
  ],
  data() {
    return {
      sender: '',
      recipients: [],
      project: '',
      hints: {
        'templates:cloud:integration:sender': '',
        'templates:cloud:integration:recipients': '',
        'templates:cloud:integration:project': '',
        'templates:cloud:integration:download': '',
        'templates:cloud:integration:cloudstore': '',
      },
      initialState: {},
      merging: false,
    };
  },
  created() {
    this.getData()
    console.info('SENDER ID', this.senderId)
  },
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
    senderTooltip() {
      const hint = this.hints['templates:cloud:integration:sender']
      if (this.senderId <= 0) {
        return '<span style="font-weight:bold;">' + t(appName, 'Required.') + '</span>'
             + ' '
             + hint
      }
      return hint
    },
  },
  // watch: {
  //   project(newVal, oldVal) {
  //    console.info('NEW PROJECT', newVal, oldVal)
  //   },
  // },
  methods: {
    info() {
      console.info.apply(null, arguments)
    },
     /**
     * Update current fileInfo and fetch new data
     * @param {Object} fileInfo the current file FileInfo
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
      console.info('SENDER', this.sender)
      this.hints = await this.tooltips(Object.keys(this.hints))
    },
    async handleMailMergeRequest(destination) {
      console.info('MAIL MERGE', arguments)
      console.info('FILE', this.fileInfo)

      this.merging = true

      const postData = {
        fileId: this.fileInfo.id,
        fileName: this.fileInfo.path + '/' + this.fileInfo.name,
        senderId: this.sender.id,
        projectId: this.projectId,
        recipientIds: this.recipientIds,
        destination,
      }
      const ajaxUrl = generateUrl('/apps/' + appName + '/documents/mail-merge')

      try {
        if (destination === 'download') {
          await fileDownload(ajaxUrl, postData)
        } else {
          const response = await axios.post(ajaxUrl, postData)
          const cloudFolder = response.data.cloudFolder
          const message = response.data.message
          console.info('CLOUD RESPONSE', response)
          const folderLinkMessage = `<a class="external link ${appName}" target="${md5(cloudFolder)}" href="${generateUrl('apps/files')}?dir=${cloudFolder}"><span class="icon-external link-text" style="padding-left:20px;background-position:left;">${cloudFolder}/</span></a>`
          showSuccess(message + ' ' + folderLinkMessage, { isHTML: true, timeout: TOAST_PERMANENT_TIMEOUT })
        }
      } catch (e) {
        console.error('ERROR', e)
        let message = t(appName, 'reason unknown')
        let errorData = {}
        if (e.response) {
          errorData = e.response.data || {}
          if (
            e.request.responseType === 'blob' &&
            errorData instanceof Blob &&
            errorData.type &&
            errorData.type.toLowerCase().indexOf('json') != -1
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
        message = errorData.message || message;
        showError(t(appName, 'Could not perform mail-merge: {message}', { message }), { timeout: TOAST_PERMANENT_TIMEOUT });
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
      border:1px solid red;
    }
  }
}
</style>
