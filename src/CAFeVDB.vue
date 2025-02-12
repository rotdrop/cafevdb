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
                                 :checked.sync="toolTipsEnabled"
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
      <NcEmptyContent v-if="isRoot || appError" :class="{ 'error-page': appError }">
        <template #name>
          <h2>{{ t(appId, '{orchestraName} Orchestra Management App', { orchestraName, }) }}</h2>
        </template>
        <template #icon>
          <DynamicSvgIcon :size="64" :data="icon" :title="orchestraName + ' logo'" />
          <!-- eslint-disable-next-line vue/no-v-html -->
          <!-- <span class="app-icon" v-html="icon" /> -->
        </template>
        <template #description>
          <span v-if="!appError">
            {{ t(appId, 'Please click on the ☰-button in order to open the navigation menu.') }}
            {{ t(appId, 'Please click on your avatar or initials (top-right) for logout and configuration options.') }}
          </span>
          <ErrorPage v-else
                     :id="appPrefix('error')"
                     :error="appError"
          />
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
<script setup lang="ts">
import { appName as appId, appName, appPrefix } from './config.ts'
import globalState from './app/globalstate.js'
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
import ErrorPage from './components/ErrorPage.vue'
import { translate as t } from '@nextcloud/l10n'
import useAppDataStore from './stores/app-data.ts'
import useHistoryStore from './stores/history.ts'
import useErrorHandlerStore from './stores/error-handler.ts'
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
import { storeToRefs } from 'pinia'
import { authorized, PERMISSION_FINANCE } from './authorization.ts'
import allDebugOptions, { DEBUG_VUE } from './debug-modes.ts'
import { enableVueDevTools, disableVueDevTools } from './util/vue-devtools.ts'
import { formatFileSize } from '@nextcloud/files'
import {
  emit as asyncEmit,
  subscribe as asyncSubscribe,
} from './services/async-event-bus.ts'
import type { SetterEvents, SetterEventValue } from '@rotdrop/async-nextcloud-event-bus'
import { closeNavigation } from './services/navigation.js'
import * as BusEvents from './event-bus-events.ts'
import DynamicSvgIcon from './components/DynamicSvgIcon.vue'
import appIcon from '../img/cafevdb.svg?raw'
import { getInitialState } from './toolkit/services/InitialStateService.js'
import { useRoute, useRouter } from 'vue-router/composables'
import type { AxiosResponse } from 'axios'
import {
  ref,
  computed,
  watch,
  nextTick,
  onMounted,
  reactive,
  set as vueSet,
  del as vueDel,
} from 'vue'
import { asyncComputed } from '@vueuse/core'
import { tooltips } from './util/tooltips.ts'
import Console from './util/console.ts'
import { AppError } from './types/errors.ts'
import { options as tooltipOptions } from 'floating-vue'
import md5 from 'blueimp-md5'
import type { NavigationItem } from './types/ajax/navigation-items.d.ts'
import type { ConfigCheckResult } from './types/ajax/config-check.d.ts'

const COMPONENT_NAME = 'CAFeVDB'
const logger = new Console(COMPONENT_NAME)

const errorHandlerProvider = useErrorHandlerStore()

const appError = ref<null | AppError>(null)
const errorHandler = <E extends AppError>(error: E) => {
  logger.info('TOP LEVEL ERROR', error)
  appError.value = error
}
errorHandlerProvider.pushHandler(errorHandler)

const initialState = getInitialState('CAFEVDB')

type DebugOption = {
  value: number,
  label: string,
}

const appData = useAppDataStore()
const history = useHistoryStore()

const {
  currentProjectId,
  currentProjectName,
} = storeToRefs(appData)

const routerHistory = history.routerHistory

const orchestraName = ref(initialState?.orchestra || t(appId, '[UNKNOWN]'))
const icon = ref(appIcon)

const loading = ref(true)
const isMounted = ref(false)
const debugModes = ref<DebugOption[]>([])
const settingsLocked = ref(false)
const appSettingsLoading = ref(false)
const pageTemplate = ref<string|null>(null)
const navigationItems = ref<NavigationItem[]>([])

const tooltipKeys = [
  'debug-mode',
  'deselect-invisible-misc-recs',
  'direct-change',
  'expert-mode',
  'finance-mode',
  'filter-visibility',
  'further-settings',
  'table-rows-per-page',
  'restore-history',
  'show-disabled',
  'show-tool-tips',
]
const initialTooltips = Object.fromEntries(tooltipKeys.map(key => { return [key, ''] as [string, string] }))

const hints = asyncComputed(
  (/* onCancel */) => tooltips(tooltipKeys),
  initialTooltips,
  { lazy: true },
)

const triggerNavigationUpdate = ref<undefined | boolean>(undefined)
const showSidebar = ref(false)
const sidebarTitle = ref('')

const router = useRouter()
const route = useRoute()

const isRoot = computed(() => {
  return route.path === '/'
})

const projectMode = computed(() => appData.projectMode)
const debugOptions = computed(() => {
  const options: DebugOption[] = []
  for (const [value, label] of Object.entries(allDebugOptions)) {
    options.push({ value: +value, label })
  }
  return options
})
const toolTipsEnabled = ref(globalState.toolTipsEnabled)
watch(toolTipsEnabled, (value) => {
  if (value !== globalState.toolTipsEnabled) {
    asyncEmit(BusEvents.TOGGLE_TOOLTIPS, { enabled: value })
  }
})
const pageRowsOptions = computed(() => [-1, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100])
const personalSettingsUrl = computed(() => nextcloudGenerateUrl('settings/user/' + appId))
const uploadMaxFileSize = ref<number>(0)
const uploadMaxHumanFileSize = computed(() => formatFileSize(uploadMaxFileSize.value))
const userPermissions = ref<number>(0)
const financeAllowed = computed(() => authorized(PERMISSION_FINANCE, userPermissions.value))
const authorizedNavigationItems = computed(() => {
  const items = navigationItems.value.filter(
    (item: NavigationItem) => (item.permissions === (item.permissions & userPermissions.value)),
  )
  logger.info('FILTERED NAVIGATION ITEMS', { items, globalState })
  return items
})

// methods

// const closeSidebar = () => { showSidebar.value = false }

const handleDetailsRequest = (data: { viewName: string, title: string, props: object }) => {
  showSidebar.value = true
  sidebarTitle.value = data.title
}

const openProjectOverview = () => {
  closeNavigation()
  asyncEmit(BusEvents.PROJECT_POPUP, {
    projectId: currentProjectId.value,
    projectName: currentProjectName.value,
  })
}

const openSettingsPopup = (event: MouseEvent) => {
  event.preventDefault()
  appSettingsLoading.value = true
  return asyncEmit(BusEvents.APP_SETTINGS_POPUP, {
    done() {},
    fail() {},
    always: () => { appSettingsLoading.value = false },
  })
}

const updatePersonalSettings = (
  event: keyof SetterEvents,
  value: SetterEventValue<typeof event>,
  oldValue: SetterEventValue<typeof event>|undefined,
) => {
  logger.debug('UPDATE PERSONAL SETTING', {
    event,
    value,
    oldValue,
    isMounted: isMounted.value,
    settingsLocked: settingsLocked.value,
  })
  if (!isMounted.value || oldValue === undefined || settingsLocked.value) {
    return
  }
  settingsLocked.value = true
  asyncEmit(event, {
    value,
    callbacks: {
      always: async () => {
        await nextTick()
        settingsLocked.value = false
      },
    },
  })
}

asyncSubscribe(BusEvents.LEGACY_BACK_REQUEST, () => router.back())

const configCheck = async () => {
  const url = generateAppUrl('a/config-check')
  logger.info('URL', url)
  try {
    const response: AxiosResponse<ConfigCheckResult> = await axios.get(url)
    logger.info('CONFIG CHECK RESULT', response)
    if (!response.data.summary) {
      const target = {
        name: 'legacy-page',
        params: { template: 'maintenance:configcheck' },
      }
      history.scheduleHistoryPush(target.params)
      router.push(target)
    }
    return response.data.summary
  } catch (error) {
    logger.error('Unable to run the basic configuration checks', url, error)
    appError.value = new AppError(t(appName, 'Unable to run the config-check.'), { cause: error })
    return false
  }
}

const updateNavigationItems = async () => {
  if (!pageTemplate.value) {
    return
  }
  const url = generateAppUrl('n/{pageTemplate}', { pageTemplate: pageTemplate.value })
  logger.info('URL', url)
  try {
    const response: AxiosResponse<{ navigation: NavigationItem[] }> = await axios.post(
      url, {
        projectId: currentProjectId.value,
        projectName: currentProjectName.value,
      },
    )
    const newNavigationItems = response.data?.navigation
    if (!newNavigationItems) {
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
      for (const item of newNavigationItems) {
        item.template = item.template.replace('/', ':')
      }
    }
    logger.debug('NAVIGATION ITEMS TO INSTALL', newNavigationItems)
    navigationItems.value = newNavigationItems
  } catch (error) {
    logger.error('Unable to update navigation items', url, error)
    appError.value = new AppError(t(appName, 'Unable to update navigation items.'), { cause: error })
  }
}

const updateDebugModes = async (newValue: number, oldValue?: number) => {
  logger.info('DEBUG MODES CHANGED', newValue, oldValue, isMounted.value, settingsLocked.value)
  if (settingsLocked.value) {
    return
  }
  const newSelection: DebugOption[] = []
  for (const option of debugOptions.value) {
    const flag = +option.value
    if ((newValue & flag)) {
      newSelection.push(option)
    }
  }
  settingsLocked.value = true
  debugModes.value.splice(0, Infinity, ...newSelection)
  await nextTick()
  settingsLocked.value = false

  if (globalState.debugModes & DEBUG_VUE) {
    enableVueDevTools()
  } else {
    disableVueDevTools()
  }
}

// watchers
const reactifyGlobalState = function() {
  logger.debug('BEFORE REACTIFY GLOBAL STATE', globalState)
  for (const [key, value] of Object.entries(globalState)) {
    vueDel(globalState, key)
    vueSet(globalState, key, value)
  }
  for (const [key, value] of Object.entries(globalState.PHPMyEdit)) {
    vueDel(globalState.PHPMyEdit, key)
    vueSet(globalState.PHPMyEdit, key, value)
  }
  // reactive(globalState) this alone does not seem to work ...
  logger.debug('AFTER REACTIFY GLOBAL STATE', globalState)

  // due to the async initialization of the globalstate computed
  // properties cannot work, but watchers do. We can exploit this to
  // update refs through watchers which in effect is just the same.

  orchestraName.value = globalState.orchestra
  watch(() => globalState.orchestra, (value) => { orchestraName.value = value })

  uploadMaxFileSize.value = globalState.uploadMaxFileSize || 0
  watch(() => globalState.uploadMaxFileSize, (value) => { uploadMaxFileSize.value = value || 0 })

  userPermissions.value = globalState.userPermissions
  watch(() => globalState.userPermissions, (value) => { userPermissions.value = value })

  updateDebugModes(globalState.debugModes)

  // settings stuff

  watch(
    () => globalState.toolTipsEnabled,
    (value, oldValue) => {
      updatePersonalSettings(BusEvents.SET_TOOLTIPS_MODE, value, oldValue)
      tooltipOptions.themes.tooltip.disabled = !value
      toolTipsEnabled.value = value
    },
  )
  watch(
    () => globalState.financeMode,
    (value, oldValue) => updatePersonalSettings(BusEvents.SET_FINANCE_MODE, value, oldValue),
  )
  watch(
    () => globalState.expertMode,
    (value, oldValue) => updatePersonalSettings(BusEvents.SET_EXPERT_MODE, value, oldValue),
  )
  watch(() => globalState.debugModes, updateDebugModes)
  watch(
    () => globalState.restoreHistory,
    (value, oldValue) => updatePersonalSettings(BusEvents.SET_RESTORE_HISTORY, value, oldValue),
  )
  watch(
    () => globalState.userPermissions,
    (...args) => {
      logger.info('USER APP PERMISSIONS CHANGED', ...args)
      triggerNavigationUpdate.value = true
    },
  )
  watch(
    () => globalState.PHPMyEdit.showDisabled,
    (value, oldValue) => updatePersonalSettings(BusEvents.SET_SHOW_DISABLED, value, oldValue),
  )
  watch(
    () => globalState.PHPMyEdit.pageRowsDefault,
    (value, oldValue) => updatePersonalSettings(BusEvents.SET_PAGE_ROWS, value, oldValue),
  )
  watch(
    () => globalState.PHPMyEdit.deselectInvisibleMiscRecs,
    (value, oldValue) => updatePersonalSettings(BusEvents.SET_DESELECT_INVISIBLE, value, oldValue),
  )
  watch(
    () => globalState.PHPMyEdit.directChange,
    (value, oldValue) => updatePersonalSettings(BusEvents.SET_DIRECT_CHANGE, value, oldValue),
  )
  watch(
    () => globalState.PHPMyEdit.initialFilterVisibility,
    (value, oldValue) => updatePersonalSettings(BusEvents.SET_INITIAL_FILTER_VISIBILITY, value, oldValue),
  )
}

if (!(globalState.initialized && globalState.PHPMyEdit.initialized)) {
  globalState.initialized = globalState.initialized || false
  globalState.PHPMyEdit.initialized = globalState.PHPMyEdit.initialized || false
  reactive(globalState)
  logger.info('INSTALL WATCHER FOR GLOBAL STATE', Object.assign({}, globalState))
  const stop = watch(
    () => globalState.initialized && globalState.PHPMyEdit.initialized,
    () => {
      reactifyGlobalState()
      console.info('AFTER GLOBAL STATE REACTIFY IN WATCHER', globalState)
      stop()
    },
  )
} else {
  reactifyGlobalState()
}

watch(
  debugModes,
  (value, oldValue) => updatePersonalSettings(BusEvents.SET_DEBUG_MODES, value, oldValue),
)
watch(
  pageTemplate,
  (value, oldValue) => {
    logger.info('CURRENT TEMPLATE CHANGED', value, oldValue)
    if (value === 'home') {
      currentProjectId.value = 0
      // should also run the config checks ... all other templates
      // call into the legacy page loader which runs the config check
      // by itself.
      configCheck()
    }
    triggerNavigationUpdate.value = true
  })
watch(
  currentProjectId,
  (...args) => {
    logger.info('CURRENT PROJECT ID CHANGED', ...args)
    triggerNavigationUpdate.value = true
  })
watch(
  triggerNavigationUpdate,
  async (value, oldValue) => {
    logger.info('TRIGGER NAVIGATION UPDATE CHANGED', value, oldValue)
    if (value) {
      triggerNavigationUpdate.value = false
      if (pageTemplate.value) {
        await updateNavigationItems()
      }
    }
  })

// the following was created() in options api ...

// router.beforeEach((to, from, next) => {
//   logger.info('GLOBAL BEFORE EACH ROUTE CHANGE', to, from, window?.history?.state)
//   next()
// })
router.afterEach((to, from) => {
  logger.info('GLOBAL AFTER EACH ROUTE CHANGE', to, from, window?.history?.state)
  pageTemplate.value = to.params?.template || 'home'
  history.finishHistoryAction()
})
// router.onReady((...args) => {
//   logger.info('ROUTER ON READY HOOK', ...args, window?.history?.state)
// })
router.onError((...args) => {
  logger.info('ROUTER ON ERROR HOOK', ...args, window?.history?.state)
  history.cancelHistoryAction()
})

onMounted(() => {
  logger.debug('INITIAL HISTORY', {
    currentRoute: { ...router.currentRoute },
    windowHistoryState: { ...window.history?.state },
    history: { ...routerHistory },
  })

  // works only after mounting
  closeNavigation()
  isMounted.value = true
  if (triggerNavigationUpdate.value === undefined) {
    triggerNavigationUpdate.value = true
  }
  if (!pageTemplate.value) {
    pageTemplate.value = 'home'
  }

  loading.value = false
})
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
  &.error-page h2 ~ p {
    min-width: 80%;
    text-align: left;
  }
  h2 ~ p {
    text-align: center;
    width: 72ex;
  }
  .hint {
    color: var(--color-text-lighter);
  }
  .empty-content__icon {
    margin-top: 16px;
  }
}
</style>
