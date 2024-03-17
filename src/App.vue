<!--
 - @copyright Copyright (c) 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
 -
 - @author Claus-Justus Heine <himself@claus-justus-heine.de>
 -
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
 -
 -->
<template>
  <NcContent :app-name="appId">
    <NcAppNavigation>
      <template #list>
        <NcAppNavigationItem :to="{ name: '/projects' }"
                             :name="t(appId, 'All Projects')"
                             icon="icon-home"
                             exact
                             @click="showSidebar = false"
        />
      </template>
      <template #footer>
        <NcAppNavigationSettings>
          <NcCheckboxRadioSwitch :checked.sync="debug">
            {{ t(appId, 'Fixme, add settings') }}
          </NcCheckboxRadioSwitch>
        </NcAppNavigationSettings>
      </template>
    </NcAppNavigation>
    <NcAppContent :class="{ 'icon-loading': loading }" @show-sidebar="showSidebar = true">
      <router-view v-show="!loading && !error" :loading.sync="loading" @view-details="handleDetailsRequest" />
      <NcEmptyContent v-if="isRoot || memberDataError" class="emp-content">
        {{ t(appId, '{orchestraName} Orchestra Portal', { orchestraName, }) }}
        <template #icon>
          <img :src="icon">
        </template>
        <template #description>
          {{ t(appId, 'Description') }}
        </template>
      </NcEmptyContent>
    </NcAppContent>
    <!--
    <NcAppSidebar v-show="showSidebar"
                  :name="sidebarTitle"
                  :loading.sync="loading"
                  @close="closeSidebar"
    >
      <NcAppSidebarTab v-if="sidebarView === 'InstrumentInsurances'"
                       id="details-side-bar"
                       icon="icon-share"
                       :name="t(appId, 'details')"
      >
        <InsuranceDetails v-bind="sidebarProps" />
      </NcAppSidebarTab>
      <NcAppSidebarTab v-if="sidebarView === 'Projects'"
                       id="details-side-bar"
                       icon="icon-share"
                       :name="t(appId, 'details')"
      >
        <ProjectDetails v-bind="sidebarProps" />
      </NcAppSidebarTab>
    </NcAppSidebar>
    -->
  </NcContent>
</template>

<script>
import { appName as appId } from './app/app-info.js'
import {
  NcContent,
  NcAppContent,
  NcAppNavigation,
  NcAppNavigationItem,
  NcAppNavigationSettings,
  NcCheckboxRadioSwitch,
  NcEmptyContent,
} from '@nextcloud/vue'

import Icon from '../img/cafevdb.svg'

import { getInitialState } from './toolkit/services/InitialStateService.js'

const initialState = getInitialState()

export default {
  name: 'App',
  components: {
    NcAppContent,
    NcAppNavigation,
    NcAppNavigationItem,
    NcAppNavigationSettings,
    NcCheckboxRadioSwitch,
    NcContent,
    NcEmptyContent,
  },
  // setup() {
  //   const memberData = useMemberDataStore()
  //   return { memberData }
  // },
  data() {
    return {
      orchestraName: initialState?.orchestraName || t(appId, '[UNKNOWN]'),
      icon: Icon,
      loading: true,
    }
  },
  computed: {
    isRoot() {
      return this.$route.path === '/'
    },
    // memberDataError() {
    // return this.memberData.initialized.error
    // },
    // ...mapWritableState(useAppDataStore, ['debug']),
    // ...mapWritableState(useMemberDataStore, ['memberData']),
  },
  watch: {
  },
  async created() {
    this.loading = false
  },
  methods: {
    closeSidebar() {
      this.showSidebar = false
    },
    handleDetailsRequest(data) {
      this.showSidebar = true
      this.sidebarTitle = data.title
    },
  },
}
</script>
<style lang="scss" scoped>
.app-navigation-entry.disabled::v-deep {
  opacity: 0.5;
  &, & * {
    cursor: default !important;
    pointer-events: none;
  }
}

.empty-content::v-deep {
  h2 ~ p {
    text-align: center;
  }
  .hint {
    color: var(--color-text-lighter);
  }
  .error-section {
    text-align: center;
    .error-info {
      font-weight: bold;
      font-style: italic;
      max-width: 66ex;
    }
    .hint {
      max-width: 66ex;
    }
  }
}
</style>
