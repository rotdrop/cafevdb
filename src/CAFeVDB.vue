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
 - GNU Affero General Public License for more detail.s
 -
 - You should have received a copy of the GNU Affero General Public License
 - along with this program. If not, see <http://www.gnu.org/licenses/>.
 -
 -->
<template>
  <NcContent :app-name="appId">
    <NcAppNavigation>
      <template #list>
        <NcAppNavigationItem :to="{ name: 'home' }"
                             :name="t(appId, 'Home')"
                             exact
                             @click="showSidebar = false"
        >
          <template #icon>
            <PageTemplateIcon page-template="home" />
          </template>
        </NcAppNavigationItem>
        <NcAppNavigationItem v-if="projectMode"
                             :name="t(appId, 'Overview {currentProjectName}', { currentProjectName })"
                             @click="openProjectOverview"
        >
          <template #icon>
            <PageTemplateIcon page-template="project-overview" />
          </template>
        </NcAppNavigationItem>
        <NcAppNavigationItem v-for="item in authorizedNavigationItems"
                             v-show="globalState.financeMode || !(item.permissions & PERMISSION_FINANCE)"
                             :key="item.template"
                             v-tooltip="item.tooltip"
                             :to="{
                               name: 'legacy-page',
                               params: { template: item.template, ...item.templateParameters }
                             }"
                             :name="item.name"
                             exact
                             @click="showSidebar = false"
        >
          <template #icon>
            <PageTemplateIcon :page-template="item.template" />
          </template>
        </NcAppNavigationItem>
        <!-- <NcAppNavigationItem v-if="projectMode"
                             :to="{
                               name: 'legacy-page',
                               params: {
                                 template: 'project-participants',
                                 projectId: currentProjectId,
                                 projectName: currentProjectName,
                               },
                             }"
                             :name="t(appId, 'Participants')"
                             exact
                             @click="showSidebar = false"
        >
          <template #icon>
            <ProjectParticipantsIcon />
          </template>
        </NcAppNavigationItem>
        <NcAppNavigationItem v-if="projectMode"
                             :to="{
                               name: 'legacy-page',
                               params: {
                                 template: 'project-instrumentation-numbers',
                                 projectId: currentProjectId,
                                 projectName: currentProjectName,
                               },
                             }"
                             :name="t(appId, 'Instrumentation Numbers')"
                             exact
                             @click="showSidebar = false"
        >
          <template #icon>
            <InstrumentationNumbersIcon />
          </template>
        </NcAppNavigationItem>
        <NcAppNavigationItem v-if="projectMode"
                             :to="{
                               name: 'legacy-page',
                               params: {
                                 template: 'project-participant-fields',
                                 projectId: currentProjectId,
                                 projectName: currentProjectName,
                               },
                             }"
                             :name="t(appId, 'Extra Fields')"
                             exact
                             @click="showSidebar = false"
        >
          <template #icon>
            <ParticipantFieldsIcon />
          </template>
        </NcAppNavigationItem>
        <NcAppNavigationItem ref="projectView"
                             :to="{name: 'legacy-page', params: { template: 'projects' }}"
                             :name="t(appId, 'All Projects')"
                             icon="icon-home"
                             exact
                             @click="showSidebar = false"
        />
        <NcAppNavigationItem :to="{ name: 'legacy-page', params: { template: 'all-musicians' }}"
                             :name="t(appId, 'All Musicians')"
                             icon="icon-home"
                             exact
                             @click="showSidebar = false"
        /> -->
      </template>
      <template #footer>
        <NcAppNavigationSettings :exclude-click-outside-selectors="[
          '#appsettings_popup *',
          '.vs__dropdown-menu',
        ]"
        >
          <NcCheckboxRadioSwitch v-tooltip="hints['show-tool-tips']"
                                 :checked.sync="globalState.toolTipsEnabled"
          >
            {{ t(appId, 'Tool-Tips') }}
          </NcCheckboxRadioSwitch>
          <NcCheckboxRadioSwitch v-tooltip="hints['restore-history']"
                                 :checked.sync="globalState.restoreHistory"
          >
            {{ t(appId, 'Restore Last View') }}
          </NcCheckboxRadioSwitch>
          <NcCheckboxRadioSwitch v-tooltip="hints['filter-visibility']"
                                 :checked.sync="globalState.PHPMyEdit.initialFilterVisibility"
          >
            {{ t(appId, 'Filter-Controls') }}
          </NcCheckboxRadioSwitch>
          <NcCheckboxRadioSwitch v-tooltip="hints['direct-change']"
                                 :checked.sync="globalState.PHPMyEdit.directChange"
          >
            {{ t(appId, 'Quick Change-Dialog') }}
          </NcCheckboxRadioSwitch>
          <NcCheckboxRadioSwitch v-tooltip="hints['deslect-invisible-misc-recs']"
                                 :checked.sync="globalState.PHPMyEdit.deselectInvisibleMiscRecs"
          >
            {{ t(appId, 'Deselect Invisible') }}
          </NcCheckboxRadioSwitch>
          <SelectWithSubmitButton v-model="globalState.PHPMyEdit.pageRowsDefault"
                                  input-id="page-rows-select"
                                  :input-label="t(appId, '#Rows/Page in Tables')"
                                  :tooltip="hints['table-rows-per-page']"
                                  :required="true"
                                  :clearable="false"
                                  :options="pageRowsOptions"
                                  :multiple="false"
                                  :loading="false"
                                  :disabled="false"
                                  :submit-button="false"
          >
            <template #option="option">
              <NcEllipsisedOption :name="+option.label === -1 ? '∞' : '' + option.label" />
            </template>
            <template #selected-option="option">
              <NcEllipsisedOption :name="+option.label === -1 ? '∞' : '' + option.label" />
            </template>
          </SelectWithSubmitButton>
          <NcCheckboxRadioSwitch v-if="financeAllowed"
                                 v-tooltip="hints['finance-mode']"
                                 :checked.sync="globalState.financeMode"
          >
            {{ t(appId, 'Finance-Mode') }}
          </NcCheckboxRadioSwitch>
          <NcCheckboxRadioSwitch v-tooltip="hints['expert-mode']"
                                 :checked.sync="globalState.expertMode"
          >
            {{ t(appId, 'Expert-Mode') }}
          </NcCheckboxRadioSwitch>
          <NcCheckboxRadioSwitch v-tooltip="hints['show-disabled']"
                                 :checked.sync="globalState.PHPMyEdit.showDisabled"
          >
            {{ t(appId, 'Show Disabled Data-Sets') }}
          </NcCheckboxRadioSwitch>
          <SelectWithSubmitButton v-model="debugModes"
                                  input-id="debug-modes-select"
                                  :input-label="t(appId, 'Debug')"
                                  :tooltip="hints['debug-mode']"
                                  :required="false"
                                  :clearable="true"
                                  :options="debugOptions"
                                  :multiple="true"
                                  :loading="false"
                                  :disabled="false"
                                  :submit-button="false"
          />
          <NcActions :force-name="true" :inline="1" :class="{ loading: appSettingsLoading }">
            <NcActionLink v-tooltip="hints['further-settings']"
                          :class="{ loading: appSettingsLoading }"
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
      <RouterView v-show="!loading && !appError"
                  :loading.sync="loading"
                  @view-details="handleDetailsRequest"
      />
      <NcEmptyContent v-if="isRoot || appError" class="emp-content">
        {{ t(appId, '{orchestraName} Orchestra Portal', { orchestraName, }) }}
        <template #icon>
          <!-- eslint-disable-next-line vue/no-v-html -->
          <span class="app-icon" v-html="icon" />
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
    <div id="fullcalendar">
      <!-- used by legacy calendar stuff -->
    </div>
    <div id="dialog_holder" class="popup topleft hidden">
      <!-- used by legacy calendar, blog, legacy events -->
    </div>
    <div id="appsettings_popup" class="personal-settings app-admin-settings popup bottomleft hidden">
      <!-- used by app-settings popup opened by the left side-bar -->
    </div>
    <form class="focusstealer">
      <!-- defeat auto-focus attempts -->
      <input id="focusstealer" type="checkbox" class="focusstealer">
    </form>
    <ImageUploadTemplate :upload-max-file-size="uploadMaxFileSize" :upload-max-human-file-size="uploadMaxHumanFileSize" />
    <FileUploadTemplate :upload-max-file-size="uploadMaxFileSize" :upload-max-human-file-size="uploadMaxHumanFileSize" />
    <CloudFileSystemOperations :upload-max-file-size="uploadMaxFileSize" :upload-max-human-file-size="uploadMaxHumanFileSize" />
  </NcContent>
</template>
<script lang="ts">
// import { appName as appId } from './config.ts'
import appMixins from './mixins/app-mixins.ts'
import authorization from './mixins/authorization.ts'
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
  NcEllipsisedOption,
  NcEmptyContent,
} from '@nextcloud/vue'
import useAppDataStore from './stores/app-data.ts'
import useHistoryStore from './stores/history.ts'
// import ProjectInfoIcon from 'vue-material-design-icons/InformationOutline.vue'
// import ProjectPartici<pantsIcon from 'vue-material-design-icons/AccountMultiple.vue'
// import InstrumentationNumbersIcon from 'vue-material-design-icons/CircleSlice5.vue'
// import ParticipantFieldsIcon from 'vue-material-design-icons/TableAccount.vue'
import AppSettingsIcon from 'vue-material-design-icons/Cogs.vue'
import SelectWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/SelectWithSubmitButton.vue'
import ImageUploadTemplate from './components/oc-template/ImageUploadTemplate.vue'
import FileUploadTemplate from './components/oc-template/FileUploadTemplate.vue'
import CloudFileSystemOperations from './components/oc-template/CloudFileSystemOperations.vue'
import PageTemplateIcon from './components/PageTemplateIcon.vue'
import axios from '@nextcloud/axios'
import generateAppUrl from './toolkit/util/generate-url.js'
import { mapWritableState, mapState } from 'pinia'
import { authorized, PERMISSION_FINANCE } from './authorization.ts'
import { debugOptions } from './debug-modes.ts'
import { formatFileSize } from '@nextcloud/files'
import { emit as asyncEmit, subscribe as asyncSubscribe } from '@rotdrop/async-nextcloud-event-bus'
// import type { NextcloudEvents } from '@rotdrop/async-nextcloud-event-bus'
import { closeNavigation } from './services/navigation.js'
import * as BusEvents from './event-bus-events.ts'
import appIcon from '../img/cafevdb.svg?raw'
import { getInitialState } from './toolkit/services/InitialStateService.js'
import type { RawLocation } from 'vue-router'
import type { AxiosResponse } from 'axios'
// import type { PropType } from 'vue'

const initialState = getInitialState('CAFEVDB')

type NavigationItem = {
  template: string,
  name: string,
  tooltip: string,
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  templateParameters: any[],
  permissions: number,
}

type DebugOption = {
  value: number,
  label: string,
}

export default {
  name: 'CAFeVDB',
  components: {
    AppSettingsIcon,
    CloudFileSystemOperations,
    FileUploadTemplate,
    ImageUploadTemplate,
    // InstrumentationNumbersIcon,
    NcActionLink,
    NcActions,
    NcAppContent,
    NcAppNavigation,
    NcAppNavigationItem,
    NcAppNavigationSettings,
    NcCheckboxRadioSwitch,
    NcContent,
    NcEllipsisedOption,
    NcEmptyContent,
    // ParticipantFieldsIcon,
    PageTemplateIcon,
    // ProjectInfoIcon,
    // ProjectParticipantsIcon,
    SelectWithSubmitButton,
  },
  mixins: [...appMixins, authorization],
  props: {}, // make the vue language server happy, otherwise methods: will be ignored.
  setup() {
    const appData = useAppDataStore()
    const history = useHistoryStore()
    return {
      appData,
      setBusyFlag: appData.setBusyFlag,
      pushBusyState: appData.pushBusyState,
      popBusyState: appData.popBusyState,
      scheduleHistoryPush: history.scheduleHistoryReplace,
      cancelHistoryAction: history.cancelHistoryAction,
      finishHistoryAction: history.finishHistoryAction,
      scheduleHistoryReplace: history.scheduleHistoryReplace,
      routerHistory: history.routerHistory,
    }
  },
  data() {
    return {
      orchestraName: initialState?.orchestraName || this.t(this.appId, '[UNKNOWN]'),
      icon: appIcon,
      loading: true,
      isMounted: false,
      debugModes: [] as DebugOption[],
      settingsLocked: false,
      appSettingsLoading: false,
      pageTemplate: null as string|null,
      navigationItems: [] as NavigationItem[],
      hints: {
        'debug-mode': '',
        'deselect-invisible-misc-recs': '',
        'direct-change': '',
        'expert-mode': '',
        'finance-mode': '',
        'filter-visibility': '',
        'further-settings': '',
        'table-rows-per-page': '',
        'restore-history': '',
        'show-disabled': '',
        'show-tool-tips': '',
      },
      triggerNavigationUpdate: false,
      showSidebar: false,
      sidebarTitle: '',
    }
  },
  computed: {
    isRoot() {
      return this.$route.path === '/'
    },
    ...mapState(useAppDataStore, [
      'routerHistory',
      'projectMode',
    ]),
    ...mapWritableState(
      useAppDataStore, [
        'debugMode',
        'appError',
        'currentProjectId',
        'currentProjectName',
      ],
    ),
    financeAllowed() {
      return authorized(PERMISSION_FINANCE, this.globalState.userPermissions)
    },
    debugOptions(): DebugOption[] {
      const options: DebugOption[] = []
      for (const [value, label] of Object.entries(debugOptions)) {
        options.push({ value: +value, label })
      }
      return options
    },
    pageRowsOptions() {
      // const options = [
      //   {
      //     label: '∞',
      //     value: -1,
      //   },
      // ]
      // for (let i = 10; i <= 100; i += 10) {
      //   options.push({ label: '' + i, value: i })
      // }
      return [-1, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100]
    },
    personalSettingsUrl() {
      return nextcloudGenerateUrl('settings/user/' + this.appId)
    },
    uploadMaxFileSize() {
      return this.globalState?.uploadMaxFileSize || 0
    },
    uploadMaxHumanFileSize() {
      return formatFileSize(this.uploadMaxFileSize)
    },
    authorizedNavigationItems() {
      const items = this.navigationItems.filter(
        (item: NavigationItem) => (item.permissions === (item.permissions & this.globalState.userPermissions)),
      )
      this.info('FILTERED NAVIGATION ITEMS', items)
      return items
    },
  },
  watch: {
    'globalState.toolTipsEnabled'(value, oldValue) {
      this.updatePersonalSettings(BusEvents.SET_TOOLTIPS_MODE, value, oldValue)
    },
    'globalState.financeMode'(value, oldValue) {
      this.updatePersonalSettings(BusEvents.SET_FINANCE_MODE, value, oldValue)
    },
    'globalState.expertMode'(value, oldValue) {
      this.updatePersonalSettings(BusEvents.SET_EXPERT_MODE, value, oldValue)
    },
    'globalState.PHPMyEdit.showDisabled'(value, oldValue) {
      this.updatePersonalSettings(BusEvents.SET_SHOW_DISABLED, value, oldValue)
    },
    async 'globalState.debugModes'(newValue, oldValue) {
      this.info('DEBUG MODES CHANGED', newValue, oldValue, this.isMounted, this.settingsLocked)
      if (this.settingsLocked) {
        return
      }
      const newSelection: DebugOption[] = []
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
    debugModes(value, oldValue) {
      this.updatePersonalSettings(BusEvents.SET_DEBUG_MODES, value, oldValue)
    },
    'globalState.PHPMyEdit.pageRowsDefault'(value, oldValue) {
      this.updatePersonalSettings(BusEvents.SET_PAGE_ROWS, value, oldValue)
    },
    'globalState.PHPMyEdit.deselectInvisibleMiscRecs'(value, oldValue) {
      this.updatePersonalSettings(BusEvents.SET_DESELECT_INVISIBLE, value, oldValue)
    },
    'globalState.PHPMyEdit.directChange'(value, oldValue) {
      this.updatePersonalSettings(BusEvents.SET_DIRECT_CHANGE, value, oldValue)
    },
    'globalState.PHPMyEdit.initialFilterVisibility'(value, oldValue) {
      this.updatePersonalSettings(BusEvents.SET_INITIAL_FILTER_VISIBILITY, value, oldValue)
    },
    'globalState.restoreHistory'(value, oldValue) {
      this.updatePersonalSettings(BusEvents.SET_RESTORE_HISTORY, value, oldValue)
    },
    'globalState.userPermissions'(...args) {
      this.info('USER APP PERMISSIONS CHANGED', ...args)
      this.triggerNavigationUpdate = true
    },
    pageTemplate(value, oldValue) {
      this.info('CURRENT TEMPLATE CHANGED', value, oldValue)
      if (value === 'home') {
        this.currentProjectId = 0
      }
      this.triggerNavigationUpdate = true
    },
    currentProjectId(...args) {
      this.info('CURRENT PROJECT ID CHANGED', ...args)
      this.triggerNavigationUpdate = true
    },
    async triggerNavigationUpdate(value: boolean, oldValue: boolean) {
      this.info('TRIGGER NAVIGATION UPDATE CHANGED', value, oldValue)
      if (value) {
        this.triggerNavigationUpdate = false
        if (this.pageTemplate) {
          await this.updateNavigationItems()
        }
      }
    },
  },
  async created() {
    this.loading = false
    this.hints = await this.tooltips(Object.keys(this.hints))
    asyncSubscribe(BusEvents.SET_BUSY_FLAG, ({ value }) => this.setBusyFlag(value))
    asyncSubscribe(BusEvents.PUSH_BUSY_STATE, () => this.pushBusyState())
    asyncSubscribe(BusEvents.POP_BUSY_STATE, () => this.popBusyState())
    // this.$router.beforeEach((to, from, next) => {
    //   this.info('GLOBAL BEFORE EACH ROUTE CHANGE', to, from, window?.history?.state)
    //   next()
    // })
    this.$router.afterEach((to, from) => {
      this.info('GLOBAL AFTER EACH ROUTE CHANGE', to, from, window?.history?.state)
      this.pageTemplate = to.params?.template || 'home'
      this.finishHistoryAction()
    })
    // this.$router.onReady((...args) => {
    //   this.info('ROUTER ON READY HOOK', ...args, window?.history?.state)
    // })
    this.$router.onError((...args) => {
      this.info('ROUTER ON ERROR HOOK', ...args, window?.history?.state)
      this.cancelHistoryAction()
    })

    this.debug('INITIAL HISTORY', {
      currentRoute: { ...this.$router.currentRoute },
      windowHistoryState: { ...window.history?.state },
      history: { ...this.routerHistory },
    })
  },
  mounted() {
    // works only after mounting
    closeNavigation()
    this.isMounted = true
    this.info(this.appId, this.appIdProp)
  },
  methods: {
    closeSidebar() {
      this.showSidebar = false
    },
    handleDetailsRequest(data: { viewName: string, title: string, props: object }) {
      this.showSidebar = true
      this.sidebarTitle = data.title
    },
    getRouteHref(route: RawLocation) {
      const routeProps = this.$router.resolve(route)
      return routeProps?.href
    },
    openProjectOverview() {
      closeNavigation()
      asyncEmit(BusEvents.PROJECT_POPUP, {
        projectId: this.currentProjectId,
        projectName: this.currentProjectName,
      })
    },
    async openSettingsPopup(event: MouseEvent) {
      event.preventDefault()
      this.appSettingsLoading = true
      asyncEmit(BusEvents.APP_SETTINGS_POPUP, {
        done() {},
        fail() {},
        always: () => { this.appSettingsLoading = false },
      })
    },
    updatePersonalSettings(event: string, value: boolean, oldValue: boolean) {
      this.debug('UPDATE PERSONAL SETTING', this.updatePersonalSettings, {
        event,
        value,
        oldValue,
        isMounted: this.isMounted,
        settingsLocked: this.settingsLocked,
      })
      if (!this.isMounted || oldValue === undefined || this.settingsLocked) {
        return
      }
      this.settingsLocked = true
      asyncEmit(event, {
        value,
        callbacks: {
          always: async () => {
            await this.$nextTick()
            this.settingsLocked = false
          },
        },
      })
    },
    async updateNavigationItems() {
      if (!this.pageTemplate) {
        return
      }
      const url = generateAppUrl('vue-app/n/{pageTemplate}', { pageTemplate: this.pageTemplate })
      this.info('URL', this.pageTemplate, { pageTemplate: this.pageTemplate })
      try {
        const response: AxiosResponse<{ navigation: NavigationItem[] }> = await axios.post(
          url, {
            projectId: this.currentProjectId,
            projectName: this.currentProjectName,
          },
        )
        const navigationItems = response.data?.navigation
        if (!navigationItems) {
          // TODO: notify user etc.
        } else {
          // naturally legacy templates refere to file-system objects
          // and may contain path-separators. This is problematic as
          // some parts -- not sure if it is my mistake -- of the
          // request handling may or may not require double
          // url-encoding. So better do not inject special characters
          // into the url params at all. Here we are on the safe side:
          // at worst the template contains path separators and
          // otherwise only lowercase alphabetics. So just replace the
          // slashes by a colon.
          for (const item of navigationItems) {
            item.template = item.template.replace('/', ':')
          }
        }
        this.debug('NAVIGATION ITEMS TO INSTALL', navigationItems)
        // this.navigationItems.splice(0, this.navigationItems.length, ...navigationItems)
        this.navigationItems = navigationItems
      } catch (error) {
        // TODO: notify user etc.
        this.error('Unable to update navigation items', url, error)
      }
    },
  },
}
</script>
<style lang="scss" scoped>
#app-settings::v-deep {
  max-height: 60%;
  flex-shrink: 10;
  #app-settings__content {
    max-height: 100%;
  }
}
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
