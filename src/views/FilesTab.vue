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
  <AppSidebar class="app-sidebar--without-background app-sidebar-hidden-root" :title="appName" :header="false">
    <AppSidebarTab :id="appName + '-tab'"
                   icon="icon-rename"
                   :name="t(appName, appName)"
    >
      <!-- <SettingsSelectUsers v-model="sender"
                           :label="t(appName, 'Sender')"
                           :hint="hints['templates:cloud:integration:sender']"
                           :multiple="false"
      /> -->
    </AppSidebarTab>
  </AppSidebar>
</template>
<script>

import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import SettingsSelectUsers from '../components/SettingsSelectUsers'

export default {
  name: 'FilesTab',
  components: {
    AppSidebar,
    AppSidebarTab,
    SettingsSelectUsers,
  },
  data() {
    return {
      sender: '',
      recipients: '',
      hints: {
        'templates:cloud:integration:sender': '',
      },
    };
  },
  computed: {
    activeTab() {
      return this.$parent.activeTab
    },
  },
  methods: {
     /**
     * Update current fileInfo and fetch new data
     * @param {Object} fileInfo the current file FileInfo
     */
    async update(fileInfo) {
      this.fileInfo = fileInfo
      this.resetState()
    },
    /**
     * Reset the current view to its default state
     */
    resetState() {
      // FILL ME
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
