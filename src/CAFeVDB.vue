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
        <NcAppNavigationSettings :exclude-click-outside-selectors="[
          '#appsettings_popup *',
          '.vs__dropdown-menu',
        ]"
        >
          <NcCheckboxRadioSwitch :checked.sync="globalState.toolTipsEnabled">
            {{ t(appId, 'Tool-Tips') }}
          </NcCheckboxRadioSwitch>
          <NcCheckboxRadioSwitch :checked.sync="globalState.PHPMyEdit.deselectInvisibleMiscRecs">
            {{ t(appId, 'Deselect Invisible') }}
          </NcCheckboxRadioSwitch>
          <SelectWithSubmitButton v-model="globalState.PHPMyEdit.pageRowsDefault"
                                  input-id="page-rows-select"
                                  :input-label="t(appId, '#Rows/Page in Tables')"
                                  :hint="'REPLACE ME TOOLTIPS'"
                                  :required="true"
                                  :clearable="false"
                                  :options="pageRowsOptions"
                                  :multiple="false"
                                  :loading="false"
                                  :disabled="false"
                                  :submit-button="false"
          />
          <NcCheckboxRadioSwitch v-if="financeAllowed"
                                 :checked.sync="globalState.financeMode"
          >
            {{ t(appId, 'Finance-Mode') }}
          </NcCheckboxRadioSwitch>
          <NcCheckboxRadioSwitch :checked.sync="globalState.expertMode">
            {{ t(appId, 'Expert-Mode') }}
          </NcCheckboxRadioSwitch>
          <NcCheckboxRadioSwitch :checked.sync="globalState.PHPMyEdit.showDisabled">
            {{ t(appId, 'Show Disabled Data-Sets') }}
          </NcCheckboxRadioSwitch>
          <SelectWithSubmitButton v-model="debugModes"
                                  input-id="debug-modes-select"
                                  :input-label="t(appId, 'Debug')"
                                  :hint="'REPLACE ME TOOLTIPS'"
                                  :required="false"
                                  :clearable="true"
                                  :options="debugOptions"
                                  :multiple="true"
                                  :loading="false"
                                  :disabled="false"
                                  :submit-button="false"
          />
          <NcActions :force-name="true" :inline="1" :class="{ loading: appSettingsLoading }">
            <NcActionLink :class="{ loading: appSettingsLoading }"
                          :name="t(appId, 'Further Settings')"
                          :href="personalSettingsUrl"
                          :target="md5(personalSettingsUrl)"
                          @click="openSettingsPopup"
            >
              <template #icon>
                <AppSettingsIcon />
              </template>
              {{ t(appId, 'Further Settings') }}
            </NcActionLink>
          </NcActions>
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
    <div id="appsettings_popup" class="personal-settings app-admin-settings popup bottomleft hidden" />
  </NcContent>
</template>

<script>
import { appName as appId } from './app/app-info.js'
import mixins from './mixins/app-mixins.js'
import { generateUrl as nextcloudGenerateUrl } from '@nextcloud/router'
import {
  NcActions,
  NcActionLink,
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
import AppSettingsIcon from 'vue-material-design-icons/Cogs.vue'
import SelectWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/SelectWithSubmitButton.vue'
import { mapWritableState, mapActions, mapState } from 'pinia'
import { authorized, PERMISSION_FINANCE } from './authorization.ts'
import { debugOptions } from './debug-modes.ts'
import { emit, subscribe } from '@nextcloud/event-bus'
import * as BusEvents from './event-bus.ts'

import Icon from '../img/cafevdb.svg'

import { getInitialState } from './toolkit/services/InitialStateService.js'

const initialState = getInitialState('CAFEVDB')

export default {
  name: 'CAFeVDB',
  components: {
    AppSettingsIcon,
    InstrumentationNumbersIcon,
    NcActions,
    NcActionLink,
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
    SelectWithSubmitButton,
  },
  mixins,
  data() {
    return {
      orchestraName: initialState?.orchestraName || t(appId, '[UNKNOWN]'),
      icon: Icon,
      loading: true,
      debugToggle: false,
      isMounted: false,
      debugModes: [],
      settingsLocked: false,
      appSettingsLoading: false,
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
    debugOptions() {
      const options = []
      for (const [value, label] of Object.entries(debugOptions)) {
        options.push({ value, label })
      }
      return options
    },
    pageRowsOptions() {
      const options = [
        {
          label: '∞',
          value: -1,
        },
      ]
      for (let i = 10; i <= 100; i += 10) {
        options.push({ label: '' + i, value: i })
      }
      return options
    },
    personalSettingsUrl() {
      return nextcloudGenerateUrl('settings/user/' + this.appName)
    },
  },
  watch: {
    debugToggle(value) {
      this.debugMode = value ? 1 : 0
    },
    'globalState.toolTipsEnabled'(value, oldValue) {
      this.info('TOOLTIPS MODE CHANGED', value, oldValue, this.isMounted)
      if (!this.isMounted || oldValue === undefined) {
        return
      }
      emit(BusEvents.SET_TOOLTIPS_MODE, { value })
    },
    'globalState.financeMode'(value, oldValue) {
      this.info('FINANCE MODE CHANGED', value, oldValue, this.isMounted)
      if (!this.isMounted || oldValue === undefined) {
        return
      }
      emit(BusEvents.SET_FINANCE_MODE, { value })
    },
    'globalState.expertMode'(value, oldValue) {
      this.info('EXPERT MODE CHANGED', value, oldValue, this.isMounted)
      if (!this.isMounted || oldValue === undefined) {
        return
      }
      emit(BusEvents.SET_EXPERT_MODE, { value })
    },
    'globalState.PHPMyEdit.showDisabled'(value, oldValue) {
      this.info('SHOW DISABLED MODE CHANGED', value, oldValue, this.isMounted)
      if (!this.isMounted || oldValue === undefined) {
        return
      }
      emit(BusEvents.SET_SHOW_DISABLED, { value })
    },
    async 'globalState.debugModes'(newValue, oldValue) {
      this.info('DEBUG MODES CHANGED', newValue, oldValue, this.isMounted, this.settingsLocked)
      if (this.settingsLocked) {
        return
      }
      const newSelection = []
      for (const option of this.debugOptions) {
        const flag = +option.value
        if ((newValue & flag)) {
          newSelection.push(option)
        }
      }
      this.settingsLocked = true
      this.debugModes.splice(0, Infinity, ...newSelection)
      await this.$nextTick()
      this.settingsLocked = false
    },
    async debugModes(value, oldValue) {
      this.info('DEBUG MODES SELECTION CHANGED', value, oldValue, this.isMounted, this.settingsLocked)
      if (!this.isMounted || oldValue === undefined || this.settingsLocked) {
        return
      }
      this.settingsLocked = true
      emit(BusEvents.SET_DEBUG_MODES, {
        value,
        callbacks: {
          always: async () => {
            await this.$nextTick()
            this.settingsLocked = false
          },
        },
      })
    },
    'globalState.PHPMyEdit.pageRowsDefault'(value, oldValue) {
      this.info('DEFAULT PAGE ROWS CHANGED', value, oldValue, this.isMounted, this.settingsLocked)
      if (!this.isMounted || oldValue === undefined || this.settingsLocked) {
        return
      }
      this.settingsLocked = true
      emit(BusEvents.SET_PAGE_ROWS, {
        value,
        callbacks: {
          always: async () => {
            await this.$nextTick()
            this.settingsLocked = false
          },
        },
      })
    },
    'globalState.PHPMyEdit.deselectInvisibleMiscRecs'(value, oldValue) {
      this.info('DESELECT INVISIBLE CHANGED', value, oldValue, this.isMounted, this.settingsLocked)
      if (!this.isMounted || oldValue === undefined || this.settingsLocked) {
        return
      }
      this.settingsLocked = true
      emit(BusEvents.SET_DESELECT_INVISIBLE, {
        value,
        callbacks: {
          always: async () => {
            await this.$nextTick()
            this.settingsLocked = false
          },
        },
      })
    },
  },
  created() {
    this.loading = false
    subscribe(BusEvents.PUSH_BUSY_STATE, () => this.pushBusyState())
    subscribe(BusEvents.POP_BUSY_STATE, () => this.popBusyState())
  },
  mounted() {
    // works only after mounting
    emit(BusEvents.TOGGLE_NAVIGATION, {
      open: false,
    })
    this.isMounted = true
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
      emit(BusEvents.TOGGLE_NAVIGATION, {
        open: false,
      })
      emit(BusEvents.PROJECT_POPUP, {
        projectId: this.currentProjectId,
        projectName: this.currentProjectName,
      })
    },
    async openSettingsPopup(event) {
      event.preventDefault()
      this.appSettingsLoading = true
      emit(BusEvents.APP_SETTINGS_POPUP, {
        done() {},
        fail() {},
        always: () => { this.appSettingsLoading = false },
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
