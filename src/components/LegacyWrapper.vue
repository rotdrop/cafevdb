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
      <NcButton :class="appPrefix('top-nav-button')">
        <template #icon>
          <HistoryBackIcon />
        </template>
      </NcButton>
      <div class="spacer" />
      <NcButton>
        <template #icon>
          <ReloadIcon />
        </template>
      </NcButton>
      <div class="spacer" />
      <NcButton>
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
        <template v-if="tooltips" #icon>
          <InfoIcon />
        </template>
        <template v-else #icon>
          <InfoOffIcon />
        </template>
        <NcActionCheckbox v-model="tooltips" :model-value="tooltips" @change="onTooltipsChange">
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
import appInfo from '../mixins/app-info.js'
import globalState from '../mixins/global-state.js'
import consoleOutput from '../mixins/console.js'
import axios from '@nextcloud/axios'
import generateAppUrl from '../toolkit/util/generate-url.js'
import * as CAFEVDB from '../app/cafevdb.js'
import { generateUrl as nextcloudGenerateUrl } from '@nextcloud/router'
import { getInitialState } from '../toolkit/services/InitialStateService.js'
import md5 from 'blueimp-md5'
import { emit, subscribe } from '@nextcloud/event-bus'
import { setPersonalUrl } from '../app/settings-urls.js'
import wikiPopup from '../app/wiki-popup.js'

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
  mixins: [
    appInfo,
    consoleOutput,
    globalState,
  ],
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
      return [
        initialState.wikiNamespace,
        this.appName,
        'documentation',
        'user-manual',
        this.template,
      ].join(':')
    },
    wikiManualUrl() {
      return nextcloudGenerateUrl('/apps/dokuwiki/page/index?wikiPage=' + this.wikiManualSection)
    },
    wikiManualUrlTarget() {
      return md5(this.wikiManualSection)
    },
  },
  async created() {
    subscribe(this.appName + ':toggle-tooltips', (event) => {
      this.tooltips = event.enabled
    })
    try {
      this.loading = true
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
      this.loading = false
    } catch (e) {
      this.error('ERROR', e)
    }
  },
  methods: {
    md5(input) {
      return md5(input)
    },
    onUserManualPopup() {
      wikiPopup({
        wikiPage: this.wikiManualSection,
        popupTitle: t(this.appName, 'User Manual: {section}', { section: this.shortTitle }, 0, { escape: false }),
      })
    },
    async onTooltipsChange() {
      emit(this.appName + ':toggle-tooltips', {
        enabled: this.tooltips,
      })
      try {
        const response = await axios.post(setPersonalUrl('tooltips'), { value: this.tooltips })
        this.info('set tooltips response', response)
      } catch (e) {
        this.error('set tooltips ERROR', e)
      }
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
