<!--
 * Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 -->
<template>
  <div :id="appPrefix('legacy-wrapper')">
    <div :id="appPrefix('top-navigation')" class="flex-container flex-align-center">
      <NcButton :class="appPrefix('top-nav-button')"
                :disabled="busyState || !prevHistoryIndex"
                @click="navigateBack"
      >
        <template #icon>
          <HistoryBackIcon />
        </template>
      </NcButton>
      <div class="spacer" />
      <NcButton :class="{ [appPrefix('top-nav-button')]: true, loading: busyState, }"
                :data-busy-flag="appData.busyFlag ? 'true' : 'false'"
                :data-busy-count="'' + appData.busyCount"
                @click="reloadPage"
      >
        <template #icon>
          <ReloadIcon />
        </template>
      </NcButton>
      <div class="spacer" />
      <NcButton :class="appPrefix('top-nav-button')"
                :disabled="busyState || !nextHistoryIndex"
                @click="navigateForward"
      >
        <template #icon>
          <HistoryForwardIcon />
        </template>
      </NcButton>
      <div class="spacer" />
      <NcButton>
        <template #icon>
          <HomeIcon />
        </template>
      </NcButton>
      <div class="buttonseparator" />
      <!-- <NcButton>
           <template #icon>
           <InfoIcon />
           </template>
           </NcButton> -->
      <NcActions>
        <template v-if="globalState.toolTipsEnabled" #icon>
          <InfoIcon />
        </template>
        <template v-else #icon>
          <InfoOffIcon />
        </template>
        <NcActionCheckbox v-model="toolTipsEnabled" :model-value="toolTipsEnabled">
          {{ t(appId, 'Tooltips') }}
        </NcActionCheckbox>
        <NcActionLink :href="wikiManualUrl" :target="wikiManualUrlTarget">
          {{ t(appId, 'Manual (other tab or window)') }}
        </NcActionLink>
        <NcActionButton @click="onUserManualPopup">
          {{ t(appId, 'Manual (popup)') }}
        </NcActionButton>
      </NcActions>
    </div>
    <!-- eslint-disable vue/no-v-html  -->
    <div v-if="!appError"
         :id="appPrefix('general')"
         :class="{ [appPrefix('general')]: true, loading, }"
    >
      <!-- /* used to eliminate the pixel-size of the control bar -->
      <div :id="pagePrefix + 'header-box'" :class="[pagePrefix + 'header-box', legacyCssClass]">
        <div :id="pagePrefix + 'header'" :class="[pagePrefix + 'header', legacyCssClass]" v-html="legacyHeaderHtml" />
      </div>
      <div :id="pagePrefix + 'container'" :class="[pagePrefix + 'container', legacyCssClass]">
        <!-- used to have something with 100% height for scrollbars -->
        <div :id="pagePrefix + 'body'" :class="[pagePrefix + 'body', legacyCssClass]">
          <div :id="pagePrefix + 'body-inner'"
               ref="legacyHtmlContainer"
               :class="[pagePrefix + 'body-inner', legacyCssClass]"
               v-html="legacyBodyHtml"
          />
        </div>
      </div>
    </div>
    <div v-else class="flex-container flex-justify-center">
      <ErrorPage :id="appPrefix('error')"
                 :class="{ [appPrefix('general')]: true, loading, }"
                 :error="appError"
      />
    </div>
    <div v-if="legacyAjaxError" class="flex-container flex-justify-center">
      <NcModal :show="showLegacyAjaxError"
               size="large"
               :has-next="false"
               :has-previous="false"
               :close-on-click-outside="false"
               label-id="legacy-ajax-error-heading"
               @update:show="handleLegacyAjaxErrorClose"
      >
        <template #default>
          <h2 id="legacy-ajax-error-heading">
            {{ t(appName, 'Sorry, an Error Occurred') }}
          </h2>
          <ErrorPage :id="appPrefix('legacy-ajax-error')"
                     :error="legacyAjaxError"
          />
        </template>
        <!-- <template #actions>
          <NcActionButton name="ONE" />
          <NcActionButton name="TWO" />
          <NcActionButton name="THREE" />
        </template> -->
      </NcModal>
    </div>
  </div>
</template>
<script setup lang="ts">
import { appName, appPrefix } from '../config.ts'
import globalState from '../app/globalstate.js'
import {
  nextTick,
  ref,
  computed,
  watch,
  onBeforeMount,
  onUnmounted,
  onErrorCaptured,
} from 'vue'
import {
  NcActionButton,
  NcActionCheckbox,
  NcActionLink,
  NcActions,
  NcButton,
} from '@nextcloud/vue'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import HomeIcon from 'vue-material-design-icons/Home.vue'
import ReloadIcon from 'vue-material-design-icons/Reload.vue'
import InfoIcon from 'vue-material-design-icons/InformationVariant.vue'
import InfoOffIcon from 'vue-material-design-icons/InformationOffOutline.vue'
import HistoryBackIcon from 'vue-material-design-icons/ArrowULeftTop.vue'
import HistoryForwardIcon from 'vue-material-design-icons/ArrowURightTop.vue'
import ErrorPage from './ErrorPage.vue'
import axios from '@nextcloud/axios'
import generateAppUrl from '../toolkit/util/generate-url.js'
import { closeNavigation } from '../services/navigation.js'
import useAppDataStore from '../stores/app-data.ts'
import useHistoryStore from '../stores/history.ts'
import useErrorHandlerStore from '../stores/error-handler.ts'
import {
  subscribe as asyncSubscribe,
  unsubscribe as asyncUnSubscribe,
  emit as asyncEmit,
} from '../services/async-event-bus.ts'
import {
  LEGACY_AJAX_ERROR,
  LEGACY_PAGE_CLEANUP,
  LEGACY_PAGE_FINALIZE,
  LEGACY_PAGE_LOAD,
  LEGACY_PME_UPDATE,
  LEGACY_POST_HASH,
  TOGGLE_TOOLTIPS,
  WIKI_POPUP,
} from '../event-bus-events.ts'
import * as LegacyNotification from '../app/notification.js'
import objectHash, { HASH_KEY } from '../util/object-hash.ts'
import type { AxiosResponse } from 'axios'
import type { LoadPartsData } from '../types/ajax/page-load-response.ts'
import { loadTranslations, translate as t } from '@nextcloud/l10n'
import { useRouter, useRoute } from 'vue-router/composables'
import { dokuWikiSection, dokuWikiUrl, dokuWikiUrlTarget } from '../util/doku-wiki.ts'
import { AppError } from '../types/errors.ts'
import Console from '../util/console.ts'
import { JQueryAjaxError } from '../types/ajax/jqxhr-error.ts'
import type { TemplatePostData } from '@rotdrop/async-nextcloud-event-bus'

const COMPONENT_NAME = 'LegacyWrapper'
const logger = new Console(COMPONENT_NAME)

const appError = ref<null | AppError>(null) // any cannot be avoided here
const errorHandler = <E extends AppError>(error: E) => {
  appError.value = error
  logger.error('LEGACY WRAPPER ERROR')
}
const errorHandlerProvider = useErrorHandlerStore()

errorHandlerProvider.pushHandler(errorHandler)

onErrorCaptured((...args) => { logger.error('Vue error captured', ...args) })

const router = useRouter()
const route = useRoute()

const props = withDefaults(defineProps<{
  template: string,
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  templateParameters?: Record<string, any>,
  hash?: string,
  noLegacyReload?: boolean,
  navButtonSize?: string,
}>(), {
  templateParameters: () => { return {} },
  hash: '',
  noLegacyReload: false,
  navButtonSize: 'large',
})

logger.info('PROPS AT START', { ...props })

// handle tooltips

const toolTipsEnabled = ref(globalState.toolTipsEnabled)
watch(toolTipsEnabled, (value) => {
  if (value !== globalState.toolTipsEnabled) {
    asyncEmit(TOGGLE_TOOLTIPS, { enabled: value })
  }
})
watch(() => globalState.toolTipsEnabled, () => {
  toolTipsEnabled.value = globalState.toolTipsEnabled
})

// *** app-data store, formerly accessed through "map..."
const appData = useAppDataStore()
const browserHistory = useHistoryStore()

// *** former data properties

const legacyHeaderHtml = ref('')
const legacyBodyHtml = ref('')
const legacyCssPrefix = ref<string>(appName)
const legacyCssClass = ref('')
const loading = ref(true)
const shortTitle = ref(props.template)
// eslint-disable-next-line @typescript-eslint/no-explicit-any
let loadingPromise = Promise.resolve(true) as Promise<any> // reactivity not needed
const pageLoadTrigger = ref(false)
let previousHash = null as null|string // reactivity not needed
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const legacyHtmlLoaded = ref(false)

// *** former computed properties

// Pre-vue3 useTemplateRef()
const legacyHtmlContainer = ref(null)
const wikiManualSection = computed(() => dokuWikiSection([
  appName,
  'documentation',
  'user-manual',
  props.template,
]))
const wikiManualUrl = computed(() => dokuWikiUrl(wikiManualSection.value))
const wikiManualUrlTarget = computed(() => dokuWikiUrlTarget(wikiManualSection.value))
const busyState = computed(() => appData.busyState)
// let currentProjectId = appData.currentProjectId
const currentHistoryState = computed(() => browserHistory.currentHistoryState)
const prevHistoryIndex = computed(() => browserHistory.prevHistoryIndex)
const nextHistoryIndex = computed(() => browserHistory.nextHistoryIndex)
const pmePrefix = computed(() => globalState.PHPMyEdit.pmePrefix)
const pmeSelector = (token: string, element: string) =>
  (element || '') + '.' + pmePrefix.value + '-' + token
const pmeForm = computed(() => pmeSelector('form', 'form'))
// const appPrefixGeneral = computed(() => appPrefix('general'))
const pagePrefix = computed(() => legacyCssPrefix.value + '-')

// *** seems like methods should naturally defined before defining
// watchers ...

const pushBusyState = appData.pushBusyState
const popBusyState = appData.popBusyState

const scheduleHistoryAction = browserHistory.scheduleHistoryAction
const scheduleHistoryPush = browserHistory.scheduleHistoryPush
const scheduleHistoryReplace = browserHistory.scheduleHistoryReplace

const navigateBack = () => router.back()
const navigateForward = () => router.forward()
const reloadPage = async () => {
  const pmeContainer = document.getElementById(globalState.PHPMyEdit.pmePrefix + '-table-container')
  if (pmeContainer) {
    const reloadButton = pmeContainer.querySelector(pmeForm.value + ' ' + pmeSelector('reload', 'input')) as HTMLElement
    if (reloadButton) {
      logger.debug('TRIGGER CLICK ON PME RELOAD BUTTON', reloadButton, window?.history?.state)
      LegacyNotification.hide()
      reloadButton.click()
      document.querySelector('body')!.classList.remove('dialog-titlebar-clicked') // need for "mobile" css
      return
    }
  }
  await loadLegacy()
}

const onUserManualPopup = () => {
  return asyncEmit(WIKI_POPUP, {
    wikiPage: wikiManualSection.value,
    popupTitle: t(appName, 'User Manual: {section}', { section: shortTitle.value }, 0, { escape: false }),
  })
}

// make sure the URL reflects the given hash and remove the no-reload query parameter
const synchronizeHistoryState = (hash: string) => {
  const target = {
    name: 'legacy-page',
    params: { ...route.params },
    query: { hash },
  }
  logger.info('REPLACE ROUTE TO SYNC BROWSER HISTORY', { target: { ...target } })
  return router.replace(target)
}

const updateLegacyRoute = (post: TemplatePostData, action: 'push'|'replace' = 'replace', htmlBody?: string) => {
  appError.value = null
  const params = {
    template: post.template,
  }
  const projectId = post?.projectId
  const projectName = post?.projectName
  projectId && Object.assign(params, { projectId })
  projectName && Object.assign(params, { projectName })
  const target = {
    name: 'legacy-page',
    params,
    query: {
      hash: '',
      'no-reload': '1',
    },
  }
  if (htmlBody) {
    logger.info('INSTALL NEW HTML')
    legacyBodyHtml.value = htmlBody
  }
  target.query.hash = scheduleHistoryAction(action, post)
  return router[action](target)
}

const nextFrame = () => {
  return new Promise(resolve => requestAnimationFrame(() => {
    requestAnimationFrame(resolve)
  }))
}

const doLoadLegacy = async () => {
  if (!props.template) {
    logger.error('*** TEMPLATE MISSING, CANNOT LOAD PAGE ***')
    return
  }
  appData.currentProjectId = props.templateParameters?.projectId || 0
  loading.value = true
  pushBusyState()
  closeNavigation()
  asyncEmit(LEGACY_PAGE_CLEANUP)
  logger.info('HISTORY STATE AT ENTRY', currentHistoryState.value)
  const historyAppData = currentHistoryState.value.post
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const post: Record<string|number, any> = {
    template: props.template,
    ...props.templateParameters,
  }
  Object.assign(post, historyAppData, post)
  Object.assign(historyAppData, post)
  logger.info('POST including history state', post, { ...currentHistoryState.value, post: historyAppData })
  const currentHash = objectHash(post)
  if (props.hash !== currentHash) {
    previousHash = currentHash
    scheduleHistoryReplace(post, currentHash)
    synchronizeHistoryState(currentHash)
  }
  post[HASH_KEY] = currentHash
  try {
    const response: AxiosResponse<LoadPartsData> = await axios.post(generateAppUrl('page/remember/parts'), post)
    const data = response.data // todo: validate
    legacyBodyHtml.value = data.bodyHtml
    legacyHeaderHtml.value = data.headerHtml
    legacyCssPrefix.value = data.cssPrefix
    legacyCssClass.value = data.cssClass
    await nextTick()
    await nextFrame()
    logger.info('RUN READY CALLBACKS', legacyHtmlContainer.value)
    await asyncEmit(LEGACY_PAGE_FINALIZE)
    logger.info('AFTER RUN READY CALLBACKS')
    const titleProvider = document.getElementById(globalState.PHPMyEdit.pmePrefix + '-short-title')
    if (titleProvider) {
      shortTitle.value = titleProvider.textContent || ''
    }
    const responseTemplate = data.template?.replace(/%2F|\//, ':') || props.template
    if (responseTemplate !== props.template) {
      logger.info('TEMPLATE HAS CHANGED, SYNC HISTORY', responseTemplate, props.template)
      const post: TemplatePostData = {
        template: responseTemplate,
        ...data.defaultTemplateParameters,
      }
      updateLegacyRoute(post)
    }
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
  } catch (e: any) {
    logger.error('ERROR', generateAppUrl('page/remember/parts'), post, e)
    await loadTranslations('logreader', () => logger.info('LOGREADER L10N'))
    appError.value = new AppError({ component: COMPONENT_NAME }, t(appName, 'Error loading view "{template}".', { template: props.template }), { cause: e })
  }
  popBusyState()
  loading.value = false
}

const loadLegacy = async () => {
  logger.info('VUE APP LOAD PAGE LOADING', props.template, props.templateParameters, props.hash)
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  let promise: Promise<any>
  do {
    await (promise = loadingPromise)
    logger.info('AFTER AWAIT PROMISE IN LOOP', props.template, props.templateParameters, props.hash)
  } while (promise !== loadingPromise)
  await (loadingPromise = doLoadLegacy())
  logger.info('AFTER AWAIT PAGE LOAD', props.template, props.templateParameters, props.hash)
}

// *** watchers
watch(legacyHtmlLoaded, (value, oldValue) => {
  logger.info('Legacy HTML Loaded Watcher', value, oldValue)
  if (value) {
    legacyHtmlLoaded.value = false
  }
})
watch(
  () => props.templateParameters?.projectId,
  (value, oldValue) => {
    if (!value && !oldValue) {
      // protect against change between null, undefined and possibly 0
      return
    }
    logger.info('PROJECT ID CHANGED', value, oldValue)
    appData.currentProjectId = value
    logger.info('TRIGGER PAGE LOAD')
    pageLoadTrigger.value = true
  },
)
watch(
  () => props.template,
  (...args) => {
    logger.info('TEMPLATE CHANGE', ...args)
    logger.info('TRIGGER PAGE LOAD')
    pageLoadTrigger.value = true
  },
)
watch(
  () => props.hash,
  (value, oldValue) => {
    logger.info('POST DATA HASH CHANGE', value, oldValue, props.hash)
    if (value !== previousHash) {
      previousHash = value
      logger.info('TRIGGER PAGE LOAD')
      pageLoadTrigger.value = true
    } else {
      logger.info('NEW HASH EQUAL TO PREVIOUS AFTER LOAD HASH, DO NOT TRIGGER PAGE LOAD')
    }
  },
)
watch(
  () => props.noLegacyReload,
  (value, oldValue) => {
    logger.info('NO LEGACY RELOAD CHANGE', value, oldValue, pageLoadTrigger.value)
  },
)
watch(
  pageLoadTrigger,
  async (...args) => {
    logger.info('PAGE LOAD TRIGGER CHANGE', ...args)
    if (pageLoadTrigger.value) {
      pageLoadTrigger.value = false
      appError.value = null
      if (!props.noLegacyReload) {
        await loadLegacy()
      } else {
        logger.info('NO LOAD FLAG ACTIVE, SKIPPING PAGE LOAD')
        // keep current post data, this is just for updating the hash value in window.location
        const hash = scheduleHistoryReplace(currentHistoryState.value.post)
        // remove no-load from the display URL
        await synchronizeHistoryState(hash)
        logger.info('SYNCHRONIZED BROWSER HISTORY STATE WITH COMPONENT STATE', window.location, props.hash, props.noLegacyReload)
      }
    }
  },
)

// event subscriptions may come last ..
const legacyPageLoadHandler = asyncSubscribe(
  LEGACY_PAGE_LOAD,
  async (eventData) => {
    logger.info('LEGACY PAGE LOAD CALLED', eventData)
    appError.value = null
    const params = {
      template: (eventData?.template || eventData.post.template).replace(/%2F|\//, ':'),
    }
    const projectId = eventData?.projectId || eventData.post?.projectId
    const projectName = eventData?.projectName || eventData.post?.projectName
    projectId && Object.assign(params, { projectId })
    projectName && Object.assign(params, { projectName })
    const post = Object.assign({}, eventData.post, params)
    const target = {
      name: 'legacy-page',
      params,
      query: {
        hash: '',
      },
    }
    if (eventData.keepHistory) {
      target.query.hash = scheduleHistoryReplace(post)
      // force the router to navigate by altering the hash
      if (route.query.hash === target.query.hash) {
        target.query.hash = '-'
      }
      try {
        return await router.replace(target)
      } catch (e) {
        console.info('ROUTER ERROR', { e })
        return router.go(0)
      }
    } else {
      target.query.hash = scheduleHistoryPush(post)
      // force the router to navigate by altering the hash
      if (route.query.hash === target.query.hash) {
        target.query.hash = '-'
      }
      return router.push(target)
    }
  },
)
const legacyPmeHistoryUpdateHandler = asyncSubscribe(
  LEGACY_PME_UPDATE,
  (eventData) => {
    logger.info('LEGACY PME HISTORY UPDATE', eventData)
    return updateLegacyRoute(eventData.post, eventData.action, eventData.htmlBody)
  },
)
const legacyPostHashHandler = asyncSubscribe(LEGACY_POST_HASH, (event) => {
  return { [HASH_KEY]: objectHash(event.post) }
})

const legacyAjaxError = ref<JQueryAjaxError | undefined>()
const showLegacyAjaxError = ref(false)
let legacyAjaxErrorResolve: (_value: boolean) => void
const legacyAjaxErrorHandler = asyncSubscribe(
  LEGACY_AJAX_ERROR,
  async (eventData) => {
    logger.error('LEGACY_AJAX_ERROR', { eventData })
    legacyAjaxError.value = new JQueryAjaxError(
      eventData.message || t(appName, 'An error occured during communication with the server.'),
      eventData.xhr,
      eventData.html,
    )
    showLegacyAjaxError.value = true
    const { promise: closePromise, resolve } = Promise.withResolvers<boolean>()
    legacyAjaxErrorResolve = resolve
    await closePromise
    legacyAjaxError.value = undefined
  },
)
const handleLegacyAjaxErrorClose = () => {
  showLegacyAjaxError.value = false
  legacyAjaxErrorResolve(false)
}

// Initialization work different with composition API, so fore a page load at start
pageLoadTrigger.value = true

errorHandlerProvider.popHandler()

onBeforeMount(() => errorHandlerProvider.pushHandler(errorHandler))
onUnmounted(() => {
  errorHandlerProvider.popHandler()
  asyncUnSubscribe(LEGACY_AJAX_ERROR, legacyAjaxErrorHandler)
  asyncUnSubscribe(LEGACY_PAGE_LOAD, legacyPageLoadHandler)
  asyncUnSubscribe(LEGACY_PME_UPDATE, legacyPmeHistoryUpdateHandler)
  asyncUnSubscribe(LEGACY_POST_HASH, legacyPostHashHandler)
})

</script>
<style lang="scss" scoped>
##{$appName}-legacy-wrapper {
  position: relative;
  height: 100%;
  min-height: 100%;
  max-height: 100%;
  --#{$appName}-top-padding: 44px;
}
##{$appName}-top-navigation {
  position: absolute;
  right: 0;
  top: 0;
  z-index: 100;
  height: var(--#{$appName}-top-padding);
  padding-right: calc( 0.5 * (var(--cafevdb-top-padding) - var(--default-clickable-area)));
  .buttonseparator {
    background: #000;
    height: 30px;
    width: 2px;
    opacity: 0.5;
    margin: 0 3px;
  }
  .spacer {
    width: 2px;
  }
}
.#{$appName}-general {
  position: relative;
  padding-top: var(--#{$appName}-top-padding);
  height: 100%;
  min-height: 100%;
  max-height: 100%;
  overflow: hidden;
  &.loading {
    width:100%;
    * {
      display:none;
    }
  }
}
##{$appName}-error {
  max-width: 90%;
}
#legacy-ajax-error-heading {
  margin-left: 6px;
}
.flex-container {
  display: flex;
  &.flex- {
    &align- {
      &center {
        align-items: center;
      }
      &baseline {
        align-items: baseline;
      }
    }
    &justify- {
      &center {
        justify-content: center;
      }
      &start {
        justify-content: flex-start;
      }
      &left {
        justify-content: left;
      }
    }
  }
}
</style>
