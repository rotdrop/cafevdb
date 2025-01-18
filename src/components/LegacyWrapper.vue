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
      <NcButton :class="appPrefix('top-nav-button')" :disabled="busyState">
        <template #icon>
          <HistoryBackIcon />
        </template>
      </NcButton>
      <div class="spacer" />
      <NcButton :class="{ [appPrefix('top-nav-button')]: true, loading: busyState }">
        <template #icon>
          <ReloadIcon />
        </template>
      </NcButton>
      <div class="spacer" />
      <NcButton :class="appPrefix('top-nav-button')" :disabled="busyState">
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
    <div :id="appPrefix('general')"
         :class="{ 'page-container': true, loading, }"
         v-html="html"
    />
  </div>
</template>
<script>
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
import { getInitialState } from '../toolkit/services/InitialStateService.js'
import { emit, subscribe } from '@nextcloud/event-bus'
import wikiPopup from '../app/wiki-popup.js'
import useAppDataStore from '../stores/app-data.js'
import { mapWritableState, mapActions, mapState } from 'pinia'
import * as BusEvents from '../event-bus.ts'

const initialState = getInitialState('CAFEVDB')

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
  beforeRouteEnter(to, from, next) {
    next(self => {
      self.info('BEFORE ROUTE ENTER')
      self.onRouteChange(to, from, next)
    })
  },
  beforeRouteUpdate(to, from, next) {
    this.info('BEFORE ROUTE UPDATE')
    this.onRouteChange(to, from, next)
  },
  props: {
    template: {
      type: String,
      required: true,
    },
    templateParameters: {
      type: Object,
      default: () => {},
    },
    navButtonSize: {
      type: String,
      default: 'large',
    },
  },
  data() {
    return {
      html: '',
      loading: true,
      tooltips: initialState?.toolTipsEnabled,
      stortTitle: this.template,
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
    ...mapState(useAppDataStore, ['busyState']),
    ...mapWritableState(useAppDataStore, ['currentProjectId']),
  },
  watch: {
    currentProjectId(/* newValue, oldValue */) {
      this.loadLegacy()
    },
    template() {
      this.loadLegacy()
    },
    'globalState.toolTipsEnabled'(value, oldValue) {
      this.info('TOOLTIPS MODE CHANGED', value, oldValue)
    },
  },
  async created() {
    subscribe(BusEvents.TOGGLE_TOOLTIPS, (event) => {
      this.info('TOOLTIPS CHANGE', event)
      this.tooltips = event.enabled
    })
    this.info('TOOLTIPS STATE', this.tooltips, initialState.toolTipsEnabled)
    this.loadLegacy()
  },
  methods: {
    ...mapActions(useAppDataStore, ['pushBusyState', 'popBusyState']),
    onUserManualPopup() {
      wikiPopup({
        wikiPage: this.wikiManualSection,
        popupTitle: t(this.appName, 'User Manual: {section}', { section: this.shortTitle }, 0, { escape: false }),
      })
    },
    async loadLegacy() {
      emit(BusEvents.TOGGLE_NAVIGATION, {
        open: false,
      })
      this.currentProjectId = this.templateParameters?.projectId || -1
      this.loading = true
      this.pushBusyState()
      try {
        const post = {
          template: this.template,
          ...this.templateParameters,
        }
        const response = await axios.post(generateAppUrl('page/remember/blank'), post)
        const newContent = document.createElement('div')
        newContent.innerHTML = response.data
        const newAppContent = newContent.querySelector('#' + this.appGeneralId)
        this.html = newAppContent.innerHTML
        await nextTick()
        CAFEVDB.runReadyCallbacks()
        const titleProvider = document.getElementById(this.globalState.PHPMyEdit.pmePrefix + '-short-title')
        if (titleProvider) {
          this.shortTitle = titleProvider.textContent
        }
      } catch (e) {
        this.error('ERROR', generateAppUrl('page/remember/blank'), post, e)
        this.appError = true
      }
      this.popBusyState()
      this.loading = false
    },
    onRouteChange(/* to, from, next */) {
      emit(BusEvents.TOGGLE_NAVIGATION, {
        open: false,
      })
      this.currentProjectId = this.templateParameters?.projectId || -1
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
