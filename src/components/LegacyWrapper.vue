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
      <NcButton :class="{ [appPrefix('top-nav-button')]: true, loading: busyState }"
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
        <NcActionCheckbox v-model="globalState.toolTipsEnabled" :model-value="globalState.toolTipsEnabled">
          {{ t(appName, 'Tooltips') }}
        </NcActionCheckbox>
        <NcActionLink :href="wikiManualUrl" :target="wikiManualUrlTarget">
          {{ t(appName, 'Manual (other tab or window)') }}
        </NcActionLink>
        <NcActionButton @click="onUserManualPopup">
          {{ t(appName, 'Manual (popup)') }}
        </NcActionButton>
      </NcActions>
    </div>
    <!-- eslint-disable vue/no-v-html  -->
    <div :id="appPrefix('general')" :class="{ 'page-container': true, loading, }">
      <!-- /* used to eliminate the pixel-size of the control bar -->
      <div :id="pagePrefix + 'header-box'" :class="[pagePrefix + 'header-box', legacyCssClass]">
        <div :id="pagePrefix + 'header'" :class="[pagePrefix + 'header', legacyCssClass]" v-html="legacyHeaderHtml" />
      </div>
      <div :id="pagePrefix + 'container'" :class="[pagePrefix + 'container', legacyCssClass]">
        <!-- used to have something with 100% height for scrollbars -->
        <div :id="pagePrefix + 'body'" :class="[pagePrefix + 'body', legacyCssClass]">
          <div :id="pagePrefix + 'body-inner'" :class="[pagePrefix + 'body-inner', legacyCssClass]" v-html="legacyBodyHtml" />
        </div>
      </div>
    </div>
  </div>
</template>
<script lang="ts">
import { nextTick } from 'vue'
import {
  NcActionButton,
  NcActionCheckbox,
  NcActionLink,
  NcActions,
  NcButton,
} from '@nextcloud/vue'
import HomeIcon from 'vue-material-design-icons/Home.vue'
import ReloadIcon from 'vue-material-design-icons/Reload.vue'
import InfoIcon from 'vue-material-design-icons/InformationVariant.vue'
import InfoOffIcon from 'vue-material-design-icons/InformationOffOutline.vue'
import HistoryBackIcon from 'vue-material-design-icons/ArrowULeftTop.vue'
import HistoryForwardIcon from 'vue-material-design-icons/ArrowURightTop.vue'
import mixins from '../mixins/app-mixins.js'
import axios from '@nextcloud/axios'
import generateAppUrl from '../toolkit/util/generate-url.js'
import * as CAFEVDB from '../app/cafevdb.js'
import { closeNavigation } from '../services/navigation.js'
import wikiPopup from '../app/wiki-popup.js'
import useAppDataStore from '../stores/app-data.js'
import { mapWritableState, mapActions, mapState } from 'pinia'
import { subscribe as asyncSubscribe, emit as asyncEmit } from '@rotdrop/async-nextcloud-event-bus'
import {
  LEGACY_PAGE_LOAD,
  LEGACY_PME_HISTORY_UPDATE,
  LEGACY_PAGE_CLEANUP,
} from '../event-bus-events.js'
import * as LegacyNotification from '../app/notification.js'
import objectHash from 'object-hash'

export default {
  name: 'LegacyWrapper',
  components: {
    HistoryBackIcon,
    HistoryForwardIcon,
    HomeIcon,
    InfoIcon,
    InfoOffIcon,
    NcActions,
    NcActionButton,
    NcActionCheckbox,
    NcActionLink,
    NcButton,
    ReloadIcon,
  },
  mixins,
  props: {
    template: {
      type: String,
      default: null,
    },
    templateParameters: {
      type: Object,
      default: () => {},
    },
    hash: {
      type: String,
      default: '',
    },
    noLegacyReload: {
      type: Boolean,
      default: false,
    },
    navButtonSize: {
      type: String,
      default: 'large',
    },
  },
  data() {
    return {
      legacyHeaderHtml: '',
      legacyBodyHtml: '',
      legacyCssPrefix: this.appName,
      legacyCssClass: '',
      loading: true,
      stortTitle: this.template,
      loadingPromise: Promise.resolve(true),
      pageLoadTrigger: false,
      previousHash: null,
    }
  },
  computed: {
    wikiManualSection() {
      return this.dokuWikiSection([
        this.appName,
        'documentation',
        'user-manual',
        this.template,
      ])
    },
    wikiManualUrl() {
      return this.dokuWikiUrl(this.wikiManualSection)
    },
    wikiManualUrlTarget() {
      return this.dokuWikiUrlTarget(this.wikiManualSection)
    },
    ...mapState(
      useAppDataStore, [
        'busyState',
        'currentHistoryState',
        'prevHistoryIndex',
        'nextHistoryIndex',
      ],
    ),
    ...mapWritableState(
      useAppDataStore, [
        'currentProjectId',
      ],
    ),
    pmePrefix() {
      return this.globalState.PHPMyEdit.pmePrefix
    },
    pmeForm() {
      return this.pmeSelector('form', 'form')
    },
    appPrefixGeneral() {
      return this.appPrefix('general')
    },
    pagePrefix() {
      return this.legacyCssPrefix + '-'
    },
  },
  watch: {
    'templateParameters.projectId'(value, ...rest) {
      this.info('PROJECT ID CHANGED', value, ...rest)
      this.currentProjectId = value
      this.info('TRIGGER PAGE LOAD')
      this.pageLoadTrigger = true
    },
    template(...args) {
      this.info('TEMPLATE CHANGE', ...args)
      this.info('TRIGGER PAGE LOAD')
      this.pageLoadTrigger = true
    },
    hash(value, oldValue) {
      this.info('POST DATA HASH CHANGE', value, oldValue, this.previousHash)
      if (value !== this.previousHash) {
        this.previousHash = value
        this.info('TRIGGER PAGE LOAD')
        this.pageLoadTrigger = true
      } else {
        this.info('NEW HASH EQUAL TO PREVIOUS AFTER LOAD HASH, DO NOT TRIGGER PAGE LOAD')
      }
    },
    async noLegacyReload(value, oldValue) {
      this.info('NO LEGACY RELOAD CHANGE', value, oldValue, this.pageLoadTrigger)
    },
    async pageLoadTrigger(...args) {
      this.info('PAGE LOAD TRIGGER CHANGE', ...args)
      if (this.pageLoadTrigger) {
        this.pageLoadTrigger = false
        if (!this.noLegacyReload) {
          await this.loadLegacy()
        } else {
          this.info('NO LOAD FLAG ACTIVE, SKIPPING PAGE LOAD')
          // keep current post data, this is just for updating the hash value in window.location
          this.scheduleHistoryReplace(this.currentHistoryState.post)
          // remove no-load from the display URL
          await this.synchronizeHistoryState(this.hash)
          this.info('SYNCHRONIZED BROWSER HISTORY STATE WITH COMPONENT STATE', window.location, this.hash, this.noLegacyReload)
        }
      }
    },
    'globalState.toolTipsEnabled'(value, oldValue) {
      this.info('TOOLTIPS MODE CHANGED', value, oldValue)
    },
  },
  async created() {
    this.info('WATCHED PROPS AT CREATION TIME', this.template, this.templateParameters, this.postDataHash)
    this.pageLoadSubscriber()
    this.info('TRIGGER PAGE LOAD')
    this.pageLoadTrigger = true
  },
  methods: {
    ...mapActions(
      useAppDataStore, [
        'pushBusyState',
        'popBusyState',
        'scheduleHistoryPush',
        'scheduleHistoryReplace',
      ],
    ),
    onUserManualPopup() {
      wikiPopup({
        wikiPage: this.wikiManualSection,
        popupTitle: t(this.appName, 'User Manual: {section}', { section: this.shortTitle }, 0, { escape: false }),
      })
    },
    async loadLegacy() {
      this.info('VUE APP LOAD PAGE LOADING', this.template, this.templateParameters, this.postDataHash)
      let promise
      do {
        await (promise = this.loadingPromise)
        this.info('AFTER AWAIT PROMISE IN LOOP', this.template, this.templateParameters, this.postDataHash)
      } while (promise !== this.loadingPromise)
      await (this.loadingPromise = this.doLoadLegacy())
      this.info('AFTER AWAIT PAGE LOAD', this.template, this.templateParameters, this.postDataHash)
    },
    async doLoadLegacy() {
      if (!this.template) {
        this.error('*** TEMPLATE MISSING, CANNOT LOAD PAGE ***')
        return
      }
      this.currentProjectId = this.templateParameters?.projectId || -1
      this.loading = true
      this.pushBusyState()
      closeNavigation()
      asyncEmit(LEGACY_PAGE_CLEANUP)
      this.info('HISTORY STATE AT ENTRY', this.currentHistoryState)
      const historyAppData = this.currentHistoryState.post
      const post = {
        template: this.template,
        ...this.templateParameters,
      }
      Object.assign(post, historyAppData, post)
      Object.assign(historyAppData, post)
      this.info('POST including history state', post, this.currentHistoryState)
      try {
        const response = await axios.post(generateAppUrl('page/remember/parts'), post)
        const data = response.data // todo: validate
        this.legacyBodyHtml = data.bodyHtml
        this.legacyHeaderHtml = data.headerHtml
        this.legacyCssPrefix = data.cssPrefix
        this.legacyCssClass = data.cssClass
        await nextTick()
        CAFEVDB.runReadyCallbacks()
        const titleProvider = document.getElementById(this.globalState.PHPMyEdit.pmePrefix + '-short-title')
        if (titleProvider) {
          this.shortTitle = titleProvider.textContent
        }
        this.previousHash = objectHash(post)
        if (this.hash !== this.previousHash) {
          this.scheduleHistoryReplace(post)
          await this.synchronizeHistoryState(this.previousHash)
        }
      } catch (e) {
        this.error('ERROR', generateAppUrl('page/remember/parts'), post, e)
        this.appError = true
      }
      this.popBusyState()
      this.loading = false
    },
    pmeSelector(token, element) {
      return (element || '') + '.' + this.pmePrefix + '-' + token
    },
    async reloadPage() {
      const pmeContainer = document.getElementById(this.globalState.PHPMyEdit.pmePrefix + '-table-container')
      if (pmeContainer) {
        const reloadButton = pmeContainer.querySelector(this.pmeForm + ' ' + this.pmeSelector('reload', 'input'))
        if (reloadButton) {
          this.debug('TRIGGER CLICK ON PME RELOAD BUTTON')
          LegacyNotification.hide()
          reloadButton.click()
          document.querySelector('body').classList.remove('dialog-titlebar-clicked') // need for "mobile" css
          return
        }
      }
      await this.loadLegacy()
    },
    navigateBack() {
      this.$router.back()
    },
    navigateForward() {
      this.$router.forward()
    },
    // make sure the URL reflects the given hash and remove the no-reload query
    synchronizeHistoryState(hash) {
      this.info('REPLACE ROUTE TO SYNC BROWSER HISTORY', hash)
      const params = {
        template: this.template,
        projectId: this.templateParameters?.projectId,
        projectName: this.templateParameters?.projectName,
      }
      const target: RouteRecord = {
        name: 'legacy-page',
        params,
        query: { hash },
      }
      return this.$router.replace(target)
    },
    pageLoadSubscriber() {
      asyncSubscribe(LEGACY_PAGE_LOAD, (eventData: LEGACY_PAGE_LOAD) => {
        this.info('LEGACY PAGE LOAD CALLED', eventData)
        const params = {
          template: eventData?.template || eventData.post.template,
          projectId: eventData?.projectId || eventData.post?.projectId,
          projectName: eventData?.projectName || eventData.post?.projectName,
        }
        const post = Object.assign({}, eventData.post, params)
        const target: RouteRecord = {
          name: 'legacy-page',
          params,
          query: {
            hash: objectHash(post),
          },
        }
        if (eventData.keepHistory) {
          this.scheduleHistoryReplace(post)
          return this.$router.replace(target)
        } else {
          this.scheduleHistoryPush(post)
          return this.$router.push(target)
        }
      })
      asyncSubscribe(LEGACY_PME_HISTORY_UPDATE, (eventData: LEGACY_PME_HISTORY_UPDATE) => {
        this.info('LEGACY PME HISTORY UPDATE', eventData)
        const post = eventData.post
        const params = {
          template: post.template,
          projectId: post?.projectId,
          projectName: post?.projectName,
        }
        const target: RouteRecord = {
          name: 'legacy-page',
          params,
          query: {
            hash: objectHash(post),
            'no-reload': 1,
          },
        }
        this.info('INSTALL NEW HTML')
        this.legacyBodyHtml = eventData.html
        if (eventData?.action === 'push') {
          this.scheduleHistoryPush(post)
          return this.$router.push(target)
        } else {
          this.scheduleHistoryReplace(post)
          return this.$router.replace(target)
        }
      })
    },
  },
}
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
##{$appName}-general {
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
Y
