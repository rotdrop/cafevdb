<!--
 - Orchestra member, musicion and project management application.
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
  <div class="container">
    <NcActions v-if="positioned"
               :force-menu="true"
               :manual-open="true"
               :close-after-click="true"
               @click="moveToAnchor"
    >
      <NcActionSeparator v-show="false" />
    </NcActions>
    <NcActions ref="actions"
               :class="{ positioned }"
               :force-menu="true"
               force-semantic-type="menu"
               :open.sync="open"
               @closed="closeMenu"
    >
      <NcActionCaption v-if="showProjectName && projectName"
                       :name="projectName"
      />
      <NcActionSeparator v-if="showProjectName && projectName" />
      <NcActionButton v-if="enableOverviewItem" @click="openProjectOverview">
        <template #icon>
          <ProjectInfoIcon />
        </template>
        {{ t(appName, 'Project Overview') }}
      </NcActionButton>
      <NcActionSeparator v-if="enableOverviewItem" />
      <NcActionRouter :to="{ name: 'project-participants', params: { projectId, projectName } }"
                      :name="t(appId, 'Participants')"
                      exact
                      @click="/* closeMenu */"
      >
        <template #icon>
          <ProjectParticipantsIcon />
        </template>
      </NcActionRouter>
      <!-- <NcActionRouter :to="{ name: 'project-instrumentation-numbers', params: { projectId, projectName } }"
                      :name="t(appId, 'Instrumentation Numbers')"
                      exact
                      @click="openInstrumentationNumbers"
      >
        <template #icon>
          <InstrumentationNumbersIcon />
        </template>
           </NcActionRouter> -->
      <!-- <NcActionText :name="t(appId, 'Instrumentation Numbers')">
        <template #icon>
          <InstrumentationNumbersIcon />
          {{ getRouteHref({ name: 'project-instrumentation-numbers', params: { projectId, projectName } }) }}
        </template>
           </NcActionText> -->
      <NcActionLink :name="t(appId, 'Instrumentation Numbers')"
                    :href="getRouteHref({ name: 'project-instrumentation-numbers', params: { projectId, projectName } })"
                    @click="openInstrumentationNumbers"
      >
        <template #icon>
          <InstrumentationNumbersIcon />
        </template>
      </NcActionLink>
      <NcActionButton>
        Two
      </NcActionButton>
    </NcActions>
  </div>
</template>
<script>
import {
  NcActions,
  NcActionButton,
  NcActionCaption,
  NcActionLink,
  NcActionRouter,
  NcActionText,
  NcActionSeparator,
} from '@nextcloud/vue'
import globalState from '../app/globalstate.js'
import ProjectInfoIcon from 'vue-material-design-icons/InformationOutline.vue'
import ProjectParticipantsIcon from 'vue-material-design-icons/AccountMultiple.vue'
import InstrumentationNumbersIcon from 'vue-material-design-icons/CircleSlice5.vue'
import { emit, subscribe } from '@nextcloud/event-bus'
import mixins from '../mixins/app-mixins.js'

// The "consumer" has to take care that globalState.vue.Vue is already
// defined.
export default globalState.vue.Vue.extend({
  name: 'ProjectActionsMenu',
  components: {
    InstrumentationNumbersIcon,
    NcActionButton,
    NcActionCaption,
    NcActionLink,
    NcActionRouter,
    NcActionSeparator,
    NcActionText,
    NcActions,
    ProjectInfoIcon,
    ProjectParticipantsIcon,
  },
  router: globalState.vue.router,
  mixins,
  props: {
    projectId: {
      type: Number,
      required: true,
    },
    projectName: {
      type: String,
      default: null,
    },
    forceProjectName: {
      type: Boolean,
      default: false,
    },
    enableOverviewItem: {
      type: Boolean,
      default: true,
    },
    testOpen: {
      type: Boolean,
      default: true,
    },
  },
  data() {
    return {
      open: false,
      referenceElement: null,
      triggerButton: null,
      positioned: false,
    }
  },
  computed: {
    showProjectName() {
      return this.forceProjectName || this.positioned
    },
  },
  watch: {
    open(state/*, oldState */) {
      if (!state && this.positioned) {
        // this.info('WATCHER CLOSE MENU')
        // this.closeMenu()
      }
    },
  },
  created() {
    this.$parent = globalState.vue.app
  },
  mounted() {
    const origCloseMenu = this.$refs.actions.closeMenu
    this.$refs.actions.closeMenu = (returnFocus) => origCloseMenu(this.positioned ? false : returnFocus)
    this.referenceElement = this.$refs.actions.$refs.popover.$refs.popover.$refs.reference
    this.triggerButton = this.$refs.actions.$refs.triggerButton
    subscribe(this.appName + ':project-actions', (event) => {
      const projectId = event?.projectId
      const newOpenState = event?.open
      if (!newOpenState
          && this.open
          && +projectId !== -this.projectId
          && (+projectId <= 0 || +projectId === +this.projectId)) {
        this.closeMenu()
      } else if (newOpenState && projectId === this.projectId) {
        this.openMenu(event?.x || undefined, event?.y || undefined)
      }
    })
  },
  methods: {
    openProjectOverview() {
      // this.open = false
      emit(this.appName + ':project-popup', {
        projectId: this.projectId,
        projectName: this.projectName,
      })
    },
    openInstrumentationNumbers(event) {
      event.preventDefault()
      this.open = false
      emit(this.appName + ':instrumentation-numbers-popup', {
        projectId: this.projectId,
        projectName: this.projectName,
      })
    },
    setPosition(x, y) {
      if (x !== undefined && y !== undefined) {
        this.referenceElement.style.position = 'fixed'
        this.referenceElement.style.left = x + 'px'
        this.referenceElement.style.top = y + 'px'

        this.positioned = true
      } else if (this.positioned) {
        this.referenceElement.style.position = ''
        this.referenceElement.style.left = ''
        this.referenceElement.style.top = ''

        this.positioned = false
      }
    },
    async closeMenu() {
      if (this.open) {
        this.open = false
        await this.$nextTick()
      }
      if (this.positioned) {
        // the open trigger was a context menu click, so there is not
        // point to return the focus to the menu button.
        this.triggerButton?.$el.blur()
      }
      for (let i = 0; i < 2; ++i) {
        await this.nextFrame()
        await this.$nextTick()
      }
      this.setPosition()
    },
    nextFrame() {
      return new Promise(resolve => requestAnimationFrame(() => {
        requestAnimationFrame(resolve)
      }))
    },
    async openMenu(x, y) {
      this.setPosition(x, y)
      this.open = true
      if (this.positioned) {
        await this.$nextTick()
        this.triggerButton?.$el.blur()
      }
    },
    toggleMenu(x, y) {
      if (this.open) {
        this.closeMenu()
      } else {
        this.openMenu(x, y)
      }
    },
    async moveToAnchor() {
      if (!this.open || !this.positioned) {
        return
      }
      this.openMenu()
    },
    getRouteHref(route) {
      const routeProps = this.$router.resolve(route)
      return routeProps?.href
    },
  },
})
</script>
<style lang="scss" scoped>
.container {
  display: flex;
}
.action-item.action-item--open.positioned {
  &, ::v-deep * {
    width: 0 !important;
    height: 0 !important;
    min-width: 0 !important;
    min-height: 0 !important;
    max-width: 0 !important;
    max-height: 0 !important;
    overflow: hidden;
  }
}
</style>
