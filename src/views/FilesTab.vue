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
    <SelectMusicians v-model="sender"
                     :label="t(appName, 'Sender')"
                     :hint="hints['templates:cloud:integration:sender']"
                     :multiple="false"
                     :submit-button="false"
                     search-scope="executive-board"
                     open-direction="bottom"
                     :searchable="false"
    />
    <SelectProjects v-model="project"
                    :label="t(appName, 'Project')"
                    :hint="hints['templates:cloud:integration:project']"
                    :multiple="false"
                    :submit-button="false"
                    open-direction="bottom"
    />
    <SelectMusicians v-model="recipients"
                     :label="t(appName, 'Recipients')"
                     :hint="hints['templates:cloud:integration:recipients']"
                     :multiple="true"
                     :submit-button="false"
                     :project-id="projectId"
                     open-direction="bottom"
                     search-scope="musicians"
    />
    <div class="bulk-operations">
      <span class="bulk-operations-title">{{ t(appName, 'Mail merge operation:') }}</span>
      <Actions>
        <ActionButton icon="icon-download"
                      :close-after-click="true"
                      :title="t(appName, 'Download Merged Document')"
                      @click="handleMailMergeRequest('download', ...arguments)"
        >
          {{ hints['templates:cloud:integration:download'] }}
        </ActionButton>
        <ActionButton :close-after-click="true"
                      :title="t(appName, 'Merge Document into Cloud')"
                      @click="handleMailMergeRequest('cloud', ...arguments)"
        >
          <template #icon>
            <Cloud />
          </template>
          {{ hints['templates:cloud:integration:cloudstore'] }}
        </ActionButton>
      </Actions>
    </div>
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
import { showError, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import { getInitialState } from '../services/initial-state-service'
import SelectMusicians from '../components/SelectMusicians'
import SelectProjects from '../components/SelectProjects'
import fileDownload from '../services/axios-file-download.js'
import tooltip from '../mixins/tooltips.js'

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
    };
  },
  created() {
    this.getData()
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
      try {
        return this.sender.id
      } catch (ignoreMe) {
        return 0
      }
    },
    recipientIds() {
      try {
        return this.recipients.filter((recipient) => !!recipient.id).map((recipient) => recipient.id)
      } catch (ignoreMe) {
        return []
      }
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
          await axios.post(ajaxUrl, postData)
        }
      } catch (e) {
        console.error('ERROR', e);
        let message = t(appName, 'reason unknown');
        if (e.response && e.response.data && e.response.data.message) {
          message = e.response.data.message;
        }
        showError(t(appName, 'Could not perform mail-merge: {message}', { message }), { timeout: TOAST_PERMANENT_TIMEOUT });
      }
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
}
</style>
