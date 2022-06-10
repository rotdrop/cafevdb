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
  <div>
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
  </div>
</template>
<script>

import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import SelectMusicians from '../components/SelectMusicians'
import SelectProjects from '../components/SelectProjects'
import tooltip from '../mixins/tooltips.js'

export default {
  name: 'FilesTab',
  components: {
    AppSidebar,
    AppSidebarTab,
    SelectMusicians,
    SelectProjects,
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
      },
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
      // for (const [key, value] of Object.entries(this.hints)) {
      // this.hints[key] = await this.tooltip(key)
      // }
      this.hints = await this.tooltips(Object.keys(this.hints))
    },
    /**
     * Reset the current view to its default state
     */
    resetState() {
      this.sender = ''
      this.recipients = []
      this.project = ''
    },
  },
}
</script>
<style lang="scss" scoped>
.app-sidebar-hidden-root {
  width: 100% !important;
  height: calc(100vh - 223px) !important;
  min-width: 289px !important;
  top:unset !important;

  ::v-deep {
    .app-sidebar-tabs__content {
      section {
        padding:0;
      }
    }

    .app-sidebar-header {
      display: none !important;
    }
  }
}
</style>
