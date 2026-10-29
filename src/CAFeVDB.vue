<!--
 - @copyright Copyright (c) 2024, 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  <NcContent :appName="appId">
    <NcAppNavigation :ariaLabel="t(appId, '{appId} Navigation', { appId })">
      <template #list>
        <NcAppNavigationItem :to="{ name: 'home' }"
                             :name="t(appId, 'Home')"
                             @click="showSidebar = false"
        >
          <template #icon>
            <IconPageTemplate pageTemplate="home" />
          </template>
        </NcAppNavigationItem>
        <NcAppNavigationItem v-if="projectMode"
                             v-tooltip.right="projectOverviewTooltip"
                             :name="t(appId, 'Overview {currentProjectName}', { currentProjectName })"
                             @click="openProjectOverview"
        >
          <template #icon>
            <IconPageTemplate pageTemplate="project-overview" />
          </template>
        </NcAppNavigationItem>
        <NcAppNavigationItem v-for="item in authorizedNavigationItems"
                             v-show="globalState.financeMode || !(item.permissions & PERMISSION_FINANCE)"
                             :key="item.template"
                             v-tooltip.right="item.tooltip"
                             :to="{
                               name: 'legacy-page',
                               params: { template: item.template, ...item.templateParameters },
                               query: { ...item.templateParameters },
                             }"
                             :class="{ 'finance-item': (item.permissions & PERMISSION_FINANCE) }"
                             :name="item.name"
                             @click="showSidebar = false"
        >
          <template #icon>
            <IconPageTemplate :pageTemplate="item.template" />
          </template>
        </NcAppNavigationItem>
      </template>
      <template #footer>
        <NcAppNavigationSettings :excludeClickOutsideSelectors="[
          '.vs__dropdown-menu',
          '.v-popper--theme-dropdown',
        ]"
        >
          <NcCheckboxRadioSwitch v-model="toolTipsEnabled"
                                 v-tooltip="hints['show-tool-tips']"
          >
            {{ t(appId, 'Tool-Tips') }}
          </NcCheckboxRadioSwitch>
          <NcActions :menuName="t(appName, 'Web Browser History')"
                     class="web-browser-history-menu"
          >
            <NcActionCheckbox v-model="globalState.restoreHistory"
                              v-tooltip="hints['restore-history']"
                              :closeAfterClick="true"
            >
              {{ t(appId, 'Restore Last View') }}
            </NcActionCheckbox>
            <NcActionButton :disabled="historyHasBeenSaved"
                            :closeAfterClick="true"
                            @click="history.saveHistoryData"
            >
              <template #icon>
                <IconHistorySaved v-if="historyHasBeenSaved" />
                <IconHistorySave v-else />
              </template>
              {{ t(appName, 'Save Web Browser History') }}
            </NcActionButton>
            <NcActionButton :disabled="history.savedHistoryStates.length === 0"
                            :closeAfterClick="true"
                            @click="showBrowserHistoryModal = true"
            >
              <template #icon>
                <IconHistoryManage />
              </template>
              {{ t(appName, 'Manage Saved History') }}
            </NcActionButton>
          </NcActions>
          <NcCheckboxRadioSwitch v-model="globalState.PHPMyEdit.initialFilterVisibility"
                                 v-tooltip="hints['filter-visibility']"
          >
            {{ t(appId, 'Filter-Controls') }}
          </NcCheckboxRadioSwitch>
          <NcCheckboxRadioSwitch v-model="globalState.PHPMyEdit.directChange"
                                 v-tooltip="hints['direct-change']"
          >
            {{ t(appId, 'Quick Change-Dialog') }}
          </NcCheckboxRadioSwitch>
          <NcCheckboxRadioSwitch v-model="globalState.PHPMyEdit.deselectInvisibleMiscRecs"
                                 v-tooltip="hints['deselect-invisible-misc-recs']"
          >
            {{ t(appId, 'Deselect Invisible') }}
          </NcCheckboxRadioSwitch>
          <SelectWithSubmitButton v-model="globalState.PHPMyEdit.pageRowsDefault"
                                  inputId="page-rows-select"
                                  :inputLabel="t(appId, '#Rows/Page in Tables')"
                                  :tooltip="hints['table-rows-per-page']"
                                  :required="true"
                                  :clearable="false"
                                  :options="pageRowsOptions"
                                  :multiple="false"
                                  :loading="false"
                                  :disabled="false"
                                  :submitButton="false"
          >
            <template #option="option">
              <NcEllipsisedOption :name="makePageRowsLabel(option)" />
            </template>
            <template #selected-option="option">
              <NcEllipsisedOption :name="makePageRowsLabel(option)" />
            </template>
          </SelectWithSubmitButton>
          <NcCheckboxRadioSwitch v-if="financeAllowed"
                                 v-model="globalState.financeMode"
                                 v-tooltip="hints['finance-mode']"
          >
            {{ t(appId, 'Finance-Mode') }}
          </NcCheckboxRadioSwitch>
          <NcCheckboxRadioSwitch v-model="globalState.expertMode"
                                 v-tooltip="hints['expert-mode']"
          >
            {{ t(appId, 'Expert-Mode') }}
          </NcCheckboxRadioSwitch>
          <NcCheckboxRadioSwitch v-if="globalState.expertMode"
                                 v-model="globalState.PHPMyEdit.showDisabled"
                                 v-tooltip="hints['show-disabled']"
          >
            {{ t(appId, 'Show Disabled Data-Sets') }}
          </NcCheckboxRadioSwitch>
          <SelectWithSubmitButton v-if="globalState.expertMode"
                                  v-model="debugModes"
                                  inputId="debug-modes-select"
                                  :inputLabel="t(appId, 'Debug')"
                                  :tooltip="hints['debug-mode']"
                                  :required="false"
                                  :clearable="true"
                                  :options="debugOptions"
                                  :multiple="true"
                                  :loading="false"
                                  :disabled="false"
                                  :submitButton="false"
          />
          <TextFieldWithSubmitButton v-if="globalState.expertMode && !!(globalState.debugMode & DEBUG_QUERY)"
                                     :modelValue="globalState.debugQuerySqlFilter"
                                     :label="t(appId, 'SQL Filter')"
                                     :placeholder="t(appId, 'SQL filter regexp')"
                                     :hint="t(appId, 'A regular expression which selects matching SQL queries for logging.')"
                                     @submit="(filter) => { globalState.debugQuerySqlFilter = filter; } "
          />
          <NcActions :forceName="true" :inline="1">
            <NcActionLink v-tooltip="hints['further-settings']"
                          :name="t(appId, 'Further Settings')"
                          :href="personalSettingsUrl"
                          :target="md5(personalSettingsUrl)"
            >
              <template #icon>
                <IconAppSettings />
              </template>
              {{ t(appId, 'Further Settings') }}
            </NcActionLink>
          </NcActions>
        </NcAppNavigationSettings>
      </template>
    </NcAppNavigation>
    <NcAppContent :class="{ 'icon-loading': loading }">
      <RouterView v-show="!loading"
                  v-model:loading="loading"
                  @viewDetails="handleDetailsRequest"
      />
      <div v-if="!!appError" class="flex-container flex-justify-center">
        <ErrorPageModal :show="!!appError"
                        :error="appError"
                        @update:show="appError = null"
        />
      </div>
      <div v-if="showBrowserHistoryModal" class="flex-container flex-justify-center">
        <BrowserHistoryModal :show="showBrowserHistoryModal"
                             @update:show="showBrowserHistoryModal = false"
        />
      </div>
    </NcAppContent>
    <NcAppSidebar v-show="showSidebar"
                  v-model:loading="loading"
                  name="Hello World"
                  @close="showSidebar = false"
    >
      <NcAppSidebarTab id="I-am-a-tab" name="I am a Tab!" />
    </NcAppSidebar>
    <div id="fullcalendar">
      <!-- used by legacy calendar stuff -->
    </div>
    <div id="dialog_holder" class="popup topleft hidden">
      <!-- used by legacy calendar, blog, legacy events -->
    </div>
    <form class="focusstealer">
      <!-- defeat auto-focus attempts -->
      <input id="focusstealer" type="checkbox" class="focusstealer">
    </form>
    <ImageUploadTemplate :uploadMaxFileSize="uploadMaxFileSize" :uploadMaxHumanFileSize="uploadMaxHumanFileSize" />
    <FileUploadTemplate :uploadMaxFileSize="uploadMaxFileSize" :uploadMaxHumanFileSize="uploadMaxHumanFileSize" />
    <CloudFileSystemOperations />
    <ProgressWrapperTemplate />
    <MusicianAddressViewTemplate />
  </NcContent>
</template>

<script setup lang="ts">
import type { AxiosResponse } from 'axios'
import type {
  ConfigCheckResponse,
  SidebarNavigationItem as NavigationItem,
} from '../build/ts-types/php-modules/Controller/DTO.ts'

import axios from '@nextcloud/axios'
import { formatFileSize } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl as nextcloudGenerateUrl } from '@nextcloud/router'
import {
  NcActionButton,
  NcActionCheckbox,
  NcActionLink,
  NcActions,
  NcAppContent,
  NcAppNavigation,
  NcAppNavigationItem,
  NcAppNavigationSettings,
  NcAppSidebar,
  NcAppSidebarTab,
  NcCheckboxRadioSwitch,
  NcContent,
  NcEllipsisedOption,
} from '@nextcloud/vue'
import {
  emit as asyncEmit,
  subscribe as asyncSubscribe,
} from '@rotdrop/async-nextcloud-event-bus'
import md5 from 'blueimp-md5'
import { options as tooltipOptions } from 'floating-vue'
import { storeToRefs } from 'pinia'
import {
  computed,
  nextTick,
  onMounted,
  ref,
  watch,
} from 'vue'
import {
  isNavigationFailure,
  useRoute,
  useRouter,
} from 'vue-router'
import SelectWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/SelectWithSubmitButton.vue'
import TextFieldWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/TextFieldWithSubmitButton.vue'
import IconAppSettings from 'vue-material-design-icons/Cogs.vue'
import IconHistorySave from 'vue-material-design-icons/ContentSave.vue'
import IconHistorySaved from 'vue-material-design-icons/ContentSaveCheck.vue'
import IconHistoryManage from 'vue-material-design-icons/History.vue'
import BrowserHistoryModal from './components/BrowserHistoryModal.vue'
import ErrorPageModal from './components/ErrorPageModal.vue'
import CloudFileSystemOperations from './components/oc-template/CloudFileSystemOperations.vue'
import FileUploadTemplate from './components/oc-template/FileUploadTemplate.vue'
import ImageUploadTemplate from './components/oc-template/ImageUploadTemplate.vue'
import MusicianAddressViewTemplate from './components/oc-template/MusicianAddressViewTemplate.vue'
import ProgressWrapperTemplate from './components/oc-template/ProgressWrapperTemplate.vue'
import IconPageTemplate from './components/PageTemplateIcon.vue'
import { END_POINT as configCheckEndPoint } from '../build/ts-types/php-modules/Controller/ConfigCheckController.ts'
import { END_POINT_NAVIGATION } from '../build/ts-types/php-modules/Controller/VueAppController.ts'
import { authorized, PERMISSION_FINANCE } from './authorization.ts'
import { appName as appId, appName, appPrefix } from './config.ts'
import allDebugOptions, { DEBUG_QUERY } from './debug-modes.ts'
import * as BusEvents from './event-bus-events.ts'
import { globalState, synchronizeGlobalState } from './services/legacy-global-state.ts'
import { closeNavigation } from './services/navigation.ts'
import type { PersonalSettingKey } from './services/personal-settings.ts'
import { setPersonalSetting } from './services/personal-settings.ts'
import useAppDataStore from './stores/app-data.ts'
import useErrorHandlerStore from './stores/error-handler.ts'
import useHistoryStore, { HistoryActionPush } from './stores/history.ts'
import useTooltipsStore from './stores/tooltips.ts'
import { showInfo } from './toolkit/util/toasts.ts'
// import ProjectInfoIcon from 'vue-material-design-icons/InformationOutline.vue'
// import ProjectPartici<pantsIcon from 'vue-material-design-icons/AccountMultiple.vue'
// import InstrumentationNumbersIcon from 'vue-material-design-icons/CircleSlice5.vue'
// import ParticipantFieldsIcon from 'vue-material-design-icons/TableAccount.vue'
import { AppError } from './toolkit/types/errors.ts'
import generateAppUrl from './toolkit/util/generate-url.ts'
import getInitialState from './toolkit/util/initial-state.ts'
import Console from './util/console.ts'

type DebugOption = {
  value: number
  label: string
}

const COMPONENT_NAME = 'CAFeVDB'
const logger = new Console(COMPONENT_NAME)

const errorHandlerProvider = useErrorHandlerStore()

const appError = ref<null | AppError>(null)
const errorHandler = <E extends AppError>(error: E) => {
  logger.debug('TOP LEVEL ERROR', error)
  if (!error.cause) {
    appError.value = new AppError({ component: COMPONENT_NAME }, t(appName, 'Top-Level Error'), { cause: error })
  } else {
    appError.value = error
  }
}
errorHandlerProvider.pushHandler(errorHandler)

const initialState = getInitialState({ section: 'CAFEVDB' })

const appData = useAppDataStore()
const history = useHistoryStore()

const {
  ready: historyReady,
} = storeToRefs(history)

const {
  currentProjectId,
  currentProjectName,
} = storeToRefs(appData)

// TRANSLATORS: unknown orchestra name placeholder
const orchestraName = ref(initialState?.orchestra || t(appId, '[UNKNOWN]'))

const loading = ref(true)
const isMounted = ref(false)
const debugModes = ref<DebugOption[]>([])
const settingsLocked = ref(false)
const pageTemplate = ref<string|null>(null)
const navigationItems = ref<NavigationItem[]>([])

const projectOverviewTooltip = ref(t(appName, `Display basic information about the current project like the registration deadline,
instrumentation, web-pages (inclusive editing them!), also provides configuration for the public share for music sheet download
for the participants. The view also provides a context menu in order to access the most important project views without navigating back to the main view. Just try it out!`))

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

const tooltipsProvider = useTooltipsStore()
tooltipsProvider.provideTooltips(tooltipKeys)
const hints = tooltipsProvider.tooltipsData

const triggerNavigationUpdate = ref<undefined | boolean>(undefined)
const showSidebar = ref(false)
const sidebarTitle = ref('')

const router = useRouter()
const route = useRoute()

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
  logger.debug('TOOLTIPS WATCHER', { value })
  tooltipOptions.themes.tooltip.disabled = !value
  if (value !== globalState.toolTipsEnabled) {
    logger.debug('EMIT TOGGLE TOOLTIPS', {
      value,
      globalState,
      gsTTEnable: globalState.toolTipsEnabled,
    })
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
  logger.debug('FILTERED NAVIGATION ITEMS', {
    items,
    navigationItems,
    userPermissions: userPermissions.value,
    globalState,
  })
  return items
})

// methods

// const closeSidebar = () => { showSidebar.value = false }

const makePageRowsLabel = (option: { label?: number }) => +option.label! === -1 ? '∞' : '' + option.label!

const handleDetailsRequest = (data: { viewName: string, title: string, props: object }) => {
  showSidebar.value = true
  sidebarTitle.value = data.title
}

const openProjectOverview = () => {
  closeNavigation()
  asyncEmit(BusEvents.LEGACY_RECORD_POPUP, {
    entityId: currentProjectId.value,
    projectId: currentProjectId.value,
    projectName: currentProjectName.value,
    template: 'projects',
  })
}

const legacyReloadKeys: PersonalSettingKey[] = ['financeMode', 'expertMode', 'showDisabled']

const updatePersonalSettings = async <T>(
  key: PersonalSettingKey,
  value: T,
  oldValue: T|undefined,
  revert: (oldValue: T) => void,
  payload: unknown = value,
) => {
  logger.debug('UPDATE PERSONAL SETTING', {
    key,
    value,
    oldValue,
    isMounted: isMounted.value,
    settingsLocked: settingsLocked.value,
  })
  if (!isMounted.value || oldValue === undefined || settingsLocked.value) {
    return
  }
  settingsLocked.value = true
  try {
    const response = await setPersonalSetting(key, payload)
    const messages = response.data?.messages
    if (messages?.length) {
      showInfo(messages.join(' '))
    }
    if (key === 'debugMode') {
      globalState.debugMode = response.data.value as number
    }
    if (key === 'expertMode' || key === 'financeMode') {
      document.body.classList.toggle(appPrefix(key === 'expertMode' ? 'expert-mode' : 'finance-mode'), !!value)
    }
    if (legacyReloadKeys.includes(key)) {
      asyncEmit(BusEvents.LEGACY_PAGE_RELOAD)
    }
  } catch (error) {
    logger.error('Unable to set personal setting', key, error)
    revert(oldValue)
    appError.value = new AppError(
      { component: COMPONENT_NAME },
      t(appName, 'Unable to save personal setting "{key}".', { key }),
      { cause: error },
    )
  }
  await nextTick()
  settingsLocked.value = false
}

asyncSubscribe(BusEvents.HISTORY_GO_REQUEST, (event) => {
  logger.info('RECEIVED HISTORY GO REQUEST', { event })
  history.clearHistoryAction()
  router.go(event.level)
})

const configCheck = async () => {
  const url = generateAppUrl(configCheckEndPoint)
  try {
    const response: AxiosResponse<ConfigCheckResponse> = await axios.get(url)
    logger.debug('CONFIG CHECK RESULT', response)
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
    appError.value = new AppError({ component: COMPONENT_NAME }, t(appName, 'Unable to run the config-check.'), { cause: error })
    return false
  }
}

const updateNavigationItems = async () => {
  if (!pageTemplate.value) {
    return
  }
  const url = generateAppUrl(`${END_POINT_NAVIGATION}/{pageTemplate}`, { pageTemplate: pageTemplate.value })
  logger.debug('URL', url)
  try {
    const response: AxiosResponse<{ navigation: NavigationItem[] }> = await axios.post(
      url,
      {
        projectId: currentProjectId.value,
        projectName: route.params.projectName || currentProjectName.value,
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
    appError.value = new AppError({ component: COMPONENT_NAME }, t(appName, 'Unable to update navigation items.'), { cause: error })
  }
}

const updateDebugModes = async (newValue: number, oldValue?: number) => {
  logger.debug('DEBUG MODES CHANGED', newValue, oldValue, isMounted.value, settingsLocked.value)
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
}

const historyHasBeenSaved = computed(() => history.modificationTime === history.saveTime)
const showBrowserHistoryModal = ref(false)

const redirectToLastUrlPath = ref(false)

// watchers
logger.debug('CALL SYNC GLOBAL STATE')
synchronizeGlobalState().then((globalState) => {

  logger.debug('AFTER GLOBAL STATE SYNC')

  // due to the async initialization of the globalstate computed
  // properties cannot work, but watchers do. We can exploit this to
  // update refs through watchers which in effect is just the same.

  orchestraName.value = globalState.orchestra
  watch(() => globalState.orchestra, (value) => {
    orchestraName.value = value
  })

  uploadMaxFileSize.value = globalState.uploadMaxFileSize || 0
  watch(() => globalState.uploadMaxFileSize, (value) => {
    uploadMaxFileSize.value = value || 0
  })

  userPermissions.value = globalState.userPermissions
  watch(() => globalState.userPermissions, (value) => {
    userPermissions.value = value
  })

  updateDebugModes(globalState.debugMode)

  // settings stuff

  toolTipsEnabled.value = globalState.toolTipsEnabled
  tooltipOptions.themes.tooltip.disabled = !toolTipsEnabled.value
  watch(
    () => globalState.toolTipsEnabled,
    (value, oldValue) => {
      logger.debug('GLOBAL STATE TOOLTIPS ENABLED WATCHER', {
        value,
        oldValue,
      })
      tooltipOptions.themes.tooltip.disabled = !value
      toolTipsEnabled.value = value
      updatePersonalSettings('toolTipsEnabled', value, oldValue, (old) => { globalState.toolTipsEnabled = old })
    },
  )
  watch(
    () => globalState.financeMode,
    (value, oldValue) => updatePersonalSettings('financeMode', value, oldValue, (old) => { globalState.financeMode = old }),
  )
  watch(
    () => globalState.expertMode,
    (value, oldValue) => updatePersonalSettings('expertMode', value, oldValue, (old) => { globalState.expertMode = old }),
  )
  watch(() => globalState.debugMode, updateDebugModes)
  watch(
    () => globalState.debugQuerySqlFilter,
    (value, oldValue) => updatePersonalSettings('debugQuerySqlFilter', value, oldValue, (old) => { globalState.debugQuerySqlFilter = old }),
  )
  watch(
    () => globalState.restoreHistory,
    (value, oldValue) => updatePersonalSettings('restoreHistory', value, oldValue, (old) => { globalState.restoreHistory = old }),
  )
  watch(
    () => globalState.userPermissions,
    (...args) => {
      logger.debug('USER APP PERMISSIONS CHANGED', ...args)
      triggerNavigationUpdate.value = true
    },
  )
  watch(
    () => globalState.PHPMyEdit.showDisabled,
    (value, oldValue) => updatePersonalSettings('showDisabled', value, oldValue, (old) => { globalState.PHPMyEdit.showDisabled = old }),
  )
  watch(
    () => globalState.PHPMyEdit.pageRowsDefault,
    (value, oldValue) => updatePersonalSettings('pageRowsDefault', value, oldValue, (old) => { globalState.PHPMyEdit.pageRowsDefault = old }),
  )
  watch(
    () => globalState.PHPMyEdit.deselectInvisibleMiscRecs,
    (value, oldValue) => updatePersonalSettings('deselectInvisibleMiscRecs', value, oldValue, (old) => { globalState.PHPMyEdit.deselectInvisibleMiscRecs = old }),
  )
  watch(
    () => globalState.PHPMyEdit.directChange,
    (value, oldValue) => updatePersonalSettings('directChange', value, oldValue, (old) => { globalState.PHPMyEdit.directChange = old }),
  )
  watch(
    () => globalState.PHPMyEdit.initialFilterVisibility,
    (value, oldValue) => updatePersonalSettings('initialFilterVisibility', value, oldValue, (old) => { globalState.PHPMyEdit.initialFilterVisibility = old }),
  )
})

const stopRedirectWatcher = watch(
  () => globalState.initialized && globalState.PHPMyEdit.initialized && redirectToLastUrlPath.value,
  () => {
    stopRedirectWatcher()
    // globalState is now initialized
    if (globalState.restoreHistory && redirectToLastUrlPath.value) {
      history.scheduleHistoryReplace(history.lastUrlData!, history.lastUrlHash)
      router.replace(history.lastUrlPath!)
    }
  },
)

watch(
  debugModes,
  (value, oldValue) => updatePersonalSettings(
    'debugMode',
    value,
    oldValue,
    (old) => { debugModes.value.splice(0, Infinity, ...old) },
    value.map(({ value }) => ({ value })),
  ),
)
watch(
  pageTemplate,
  async (value, oldValue) => {
    logger.debug('CURRENT TEMPLATE CHANGED', value, oldValue)
    if (value === 'home') {
      await appData.setCurrentProject(0)
      // should also run the config checks ... all other templates
      // call into the legacy page loader which runs the config check
      // by itself.
      configCheck()
    }
    triggerNavigationUpdate.value = true
  },
)
watch(
  currentProjectId,
  (...args) => {
    logger.debug('CURRENT PROJECT ID CHANGED', ...args)
    triggerNavigationUpdate.value = true
  },
)
watch(
  triggerNavigationUpdate,
  async (value, oldValue) => {
    logger.debug('TRIGGER NAVIGATION UPDATE CHANGED', value, oldValue)
    if (value) {
      triggerNavigationUpdate.value = false
      if (pageTemplate.value) {
        await updateNavigationItems()
      }
    }
  },
)

router.beforeEach((to, from, _next = () => {}, transition) => {
  logger.debug('APP BEFORE EACH ROUTE CHANGE', {
    to,
    from,
    transition,
    windowHistory: window?.history?.state,
    pendingHistoryAction: history.pendingHistoryAction,
    historyReady: historyReady.value,
  })
  if (!historyReady.value) {
    return
  }
  if (!history.pendingHistoryAction) {
    history.scheduleHistoryAction(transition ?? HistoryActionPush, to.params)
  }
})

router.afterEach((to, _from, failure, _transition) => {
  logger.debug('APP AFTER EACH ROUTE CHANGE', {
    to: { ...to },
    from: { ..._from },
    transition: _transition,
    windowHistory: { ...window?.history?.state },
    historyReady: historyReady.value,
  })
  if (!historyReady.value || isNavigationFailure(failure)) {
    return
  }
  pageTemplate.value = (to.params?.template as undefined | string) || 'home'
  // @todo: parse the query parameters, e.g.
  //
  // ?template=blah&foo=bar
  //
  // This should result in setting the legacy template to blah and
  // passing { foo: bar } as tempalte parameters, potentially
  // extending given default template parameters (if present).
})

watch(historyReady, () => {
  logger.debug('ROUTER ON READY HOOK', {
    urlPath: history.lastUrlPath,
    route,
    windowHistoryState: window?.history?.state,
  })
  if (history.lastUrlPath && route.name === 'home') {
    redirectToLastUrlPath.value = true
  } else {
    stopRedirectWatcher()
  }
})

onMounted(() => {
  logger.debug('ON MOUNTED INITIAL HISTORY', {
    currentRoute: { ...router.currentRoute },
    windowHistoryState: { ...window.history?.state },
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
#app-settings {
  max-height: 60%;
  flex-shrink: 10;
  :deep(#app-settings__content) {
    max-height: 100%;
    .web-browser-history-menu {
      button {
        padding-left: 0px;
        .button-vue__text {
          font-weight: normal;
        }
        background-color: inherit;
        &:hover {
          background-color: var(--color-background-hover);
        }
      }
    }
  }
}
.app-navigation-entry-wrapper.finance-item :deep(.app-navigation-entry:not(:hover)) {
  background-color: lightyellow;
}
.app-navigation-entry.disabled :deep() {
  opacity: 0.5;
  &, & * {
    cursor: default !important;
    pointer-events: none;
  }
}
.empty-content :deep() {
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
