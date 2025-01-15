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
               @click="moveToAnchor"
    >
      <NcActionSeparator v-show="false" />
    </NcActions>
    <NcActions ref="actions"
               :class="{ positioned }"
               :force-menu="true"
               force-semantic-type="menu"
               :open.sync="open"
               :close-after-click="true"
               @closed="closeMenu"
    >
      <NcActionCaption v-if="showProjectName && projectName"
                       :name="projectName"
      />
      <NcActionSeparator v-if="showProjectName && projectName" />
      <NcActionButton v-if="enableOverviewItem"
                      :name="t(appName, 'Project Overview')"
                      @click="openProjectOverview"
      >
        <template #icon>
          <ProjectInfoIcon />
        </template>
      </NcActionButton>
      <NcActionSeparator v-if="enableOverviewItem" />
      <NcActionRouter :to="{ name: 'project-participants', params: { projectId, projectName } }"
                      :name="t(appId, 'Participants')"
                      exact
                      @click="closeMenu"
      >
        <template #icon>
          <ProjectParticipantsIcon />
        </template>
      </NcActionRouter>
      <NcActionLink :name="t(appId, 'Instrumentation Numbers')"
                    :href="getRouteHref({ name: 'project-instrumentation-numbers', params: { projectId, projectName } })"
                    @click="openInstrumentationNumbers"
      >
        <template #icon>
          <InstrumentationNumbersIcon />
        </template>
      </NcActionLink>
      <NcActionLink :name="t(appId, 'Participant Fields')"
                    :href="getRouteHref({ name: 'project-participant-fields', params: { projectId, projectName } })"
                    @click="openParticipantFields"
      >
        <template #icon>
          <ParticipantFieldsIcon />
        </template>
      </NcActionLink>
      <NcActionSeparator />
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
import ParticipantFieldsIcon from 'vue-material-design-icons/TableAccount.vue'
import { emit, subscribe } from '@nextcloud/event-bus'
import mixins from '../mixins/app-mixins.js'
import useAppDataStore from '../stores/app-data.js'
import { mapActions } from 'pinia'

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
    ParticipantFieldsIcon,
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
    open(state, oldState) {
      if (!state && this.positioned) {
        // this.info('WATCHER CLOSE MENU')
        // this.closeMenu()
      }
      this.info('OPEN CHANGED', state, oldState)
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
    ...mapActions(useAppDataStore, ['pushBusyState', 'popBusyState']),
    openProjectOverview() {
      this.open = false
      emit('toggle-navigation', {
        open: false,
      })
      emit(this.appName + ':project-popup', {
        projectId: this.projectId,
        projectName: this.projectName,
      })
    },
    openInstrumentationNumbers(event) {
      event.preventDefault()
      this.open = false
      emit('toggle-navigation', {
        open: false,
      })
      emit(this.appName + ':project-instrumentation-numbers-popup', {
        projectId: this.projectId,
        projectName: this.projectName,
      })
    },
    openParticipantFields(event) {
      event.preventDefault()
      this.open = false
      emit('toggle-navigation', {
        open: false,
      })
      emit(this.appName + ':project-participant-fields-popup', {
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
      this.info('-> closeMenu()')
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
      this.info('<- closeMenu()')
    },
    nextFrame() {
      return new Promise(resolve => requestAnimationFrame(() => {
        requestAnimationFrame(resolve)
      }))
    },
    async openMenu(x, y) {
      this.info('-> openMenu()')
      this.setPosition(x, y)
      this.open = true
      if (this.positioned) {
        await this.$nextTick()
        this.triggerButton?.$el.blur()
      }
      this.info('<- openMenu()')
    },
    toggleMenu(x, y) {
      if (this.open) {
        this.closeMenu()
      } else {
        this.openMenu(x, y)
      }
    },
    async moveToAnchor(event) {
      if (!this.open || !this.positioned) {
        return
      }
      this.info('-> moveToAnchor()')
      event?.preventDefault()
      await this.closeMenu()
      await this.$nextTick()
      this.openMenu()
      this.info('<- moveToAnchor()')
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
.app-navigation-caption {
  font-weight: bold;
  color: blue;
  font-style: italic;
  text-align: center;
  display: inline-block;
  margin: auto;
  width: 100%;
}
</style>
