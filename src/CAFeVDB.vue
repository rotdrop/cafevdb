<!--
 - @copyright Copyright (c) 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
        <NcAppNavigationItem :to="{ name: '/' }"
                             :name="t(appId, 'Home')"
                             icon="icon-home"
                             exact
                             @click="showSidebar = false"
        />
        <NcAppNavigationItem v-if="projectMode"
                             :name="t(appId, 'Overview {currentProjectName}', { currentProjectName })"
                             @click="openProjectOverview"
        >
          <template #icon>
            <ProjectInfoIcon />
          </template>
        </NcAppNavigationItem>
        <NcAppNavigationItem v-if="projectMode"
                             :to="{ name: 'project-participants', params: {
                               projectId: currentProjectId,
                               projectName: currentProjectName,
                             }}"
                             :name="t(appId, 'Participants')"
                             exact
                             @click="showSidebar = false"
        >
          <template #icon>
            <ProjectParticipantsIcon />
          </template>
        </NcAppNavigationItem>
        <NcAppNavigationItem v-if="projectMode"
                             :to="{ name: 'project-instrumentation-numbers', params: {
                               projectId: currentProjectId,
                               projectName: currentProjectName,
                             }}"
                             :name="t(appId, 'Instrumentation Numbers')"
                             exact
                             @click="showSidebar = false"
        >
          <template #icon>
            <InstrumentationNumbersIcon />
          </template>
        </NcAppNavigationItem>
        <NcAppNavigationItem v-if="projectMode"
                             :to="{ name: 'project-participant-fields', params: {
                               projectId: currentProjectId,
                               projectName: currentProjectName,
                             }}"
                             :name="t(appId, 'Extra Fields')"
                             exact
                             @click="showSidebar = false"
        >
          <template #icon>
            <ParticipantFieldsIcon />
          </template>
        </NcAppNavigationItem>
        <NcAppNavigationItem :to="{ name: 'projects' }"
                             :name="t(appId, 'All Projects')"
                             icon="icon-home"
                             exact
                             @click="showSidebar = false"
        />
        <NcAppNavigationItem :to="{ name: 'all-musicians' }"
                             :name="t(appId, 'All Musicians')"
                             icon="icon-home"
                             exact
                             @click="showSidebar = false"
        />
      </template>
      <template #footer>
        <NcAppNavigationSettings>
          <NcCheckboxRadioSwitch :checked.sync="debugToggle">
            {{ t(appId, 'Fixme, add settings') }}
          </NcCheckboxRadioSwitch>
          <NcCheckboxRadioSwitch v-if="financeAllowed"
                                 :checked.sync="globalState.financeMode"
          >
            {{ t(appId, 'Finance Mode') }}
          </NcCheckboxRadioSwitch>
        </NcAppNavigationSettings>
      </template>
    </NcAppNavigation>
    <NcAppContent :class="{ 'icon-loading': loading }">
      <router-view v-show="!loading && !appError" :loading.sync="loading" @view-details="handleDetailsRequest" />
      <NcEmptyContent v-if="isRoot || appError" class="emp-content">
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
import mixins from './mixins/app-mixins.js'
import { emit, subscribe } from '@nextcloud/event-bus'
import {
  NcContent,
  NcAppContent,
  NcAppNavigation,
  NcAppNavigationItem,
  NcAppNavigationSettings,
  NcCheckboxRadioSwitch,
  NcEmptyContent,
} from '@nextcloud/vue'
import useAppDataStore from './stores/app-data.js'
import ProjectInfoIcon from 'vue-material-design-icons/InformationOutline.vue'
import ProjectParticipantsIcon from 'vue-material-design-icons/AccountMultiple.vue'
import InstrumentationNumbersIcon from 'vue-material-design-icons/CircleSlice5.vue'
import ParticipantFieldsIcon from 'vue-material-design-icons/TableAccount.vue'
import { mapWritableState, mapActions, mapState } from 'pinia'
import { authorized, PERMISSION_FINANCE } from './authorization.ts'

import Icon from '../img/cafevdb.svg'

import { getInitialState } from './toolkit/services/InitialStateService.js'

const initialState = getInitialState('CAFEVDB')

export default {
  name: 'CAFeVDB',
  components: {
    InstrumentationNumbersIcon,
    NcAppContent,
    NcAppNavigation,
    NcAppNavigationItem,
    NcAppNavigationSettings,
    NcCheckboxRadioSwitch,
    NcContent,
    NcEmptyContent,
    ParticipantFieldsIcon,
    ProjectInfoIcon,
    ProjectParticipantsIcon,
  },
  mixins,
  data() {
    return {
      orchestraName: initialState?.orchestraName || t(appId, '[UNKNOWN]'),
      icon: Icon,
      loading: true,
      debugToggle: false,
    }
  },
  computed: {
    isRoot() {
      return this.$route.path === '/'
    },
    ...mapState(useAppDataStore, ['busyState']),
    ...mapWritableState(
      useAppDataStore, [
        'debugMode',
        'appError',
        'currentProjectId',
        'currentProjectName',
        'projectMode',
      ],
    ),
    financeAllowed() {
      return authorized(PERMISSION_FINANCE, this.globalState.userPermissions)
    },
  },
  watch: {
    debugToggle(value) {
      this.debugMode = value ? 1 : 0
    },
  },
  created() {
    this.loading = false
    subscribe(this.appName + ':push-busy-state', () => this.pushBusyState())
    subscribe(this.appName + ':pop-busy-state', () => this.popBusyState())
  },
  mounted() {
    // works only after mounting
    emit('toggle-navigation', {
      open: false,
    })
  },
  methods: {
    ...mapActions(useAppDataStore, ['pushBusyState', 'popBusyState']),
    closeSidebar() {
      this.showSidebar = false
    },
    handleDetailsRequest(data) {
      this.showSidebar = true
      this.sidebarTitle = data.title
    },
    getRouteHref(route) {
      const routeProps = this.$router.resolve(route)
      return routeProps?.href
    },
    openProjectOverview() {
      this.open = false
      emit('toggle-navigation', {
        open: false,
      })
      emit(this.appName + ':project-popup', {
        projectId: this.currentProjectId,
        projectName: this.currentProjectName,
      })
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
