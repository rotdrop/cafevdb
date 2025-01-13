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
      <NcActionRouter :to="{ name: 'project-participants', params: { projectId, projectName } }"
                      :name="t(appId, 'Project Participants')"
                      exact
                      @click="closeMenu"
      >
        <template #icon>
          <ProjectParticipantsIcon />
        </template>
      </NcActionRouter>
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
  NcActionRouter,
  NcActionSeparator,
} from '@nextcloud/vue'
import globalState from '../app/globalstate.js'
// import Vue from 'vue'
// import /* vueInstance, */ { Vue, router } from '../vue-app.js'
import ProjectInfoIcon from 'vue-material-design-icons/InformationOutline.vue'
import ProjectParticipantsIcon from 'vue-material-design-icons/Group.vue'
import { emit, subscribe } from '@nextcloud/event-bus'
import mixins from '../mixins/app-mixins.js'

export default globalState.vue.Vue.extend({
  name: 'ProjectActionsMenu',
  components: {
    NcActionButton,
    NcActionCaption,
    NcActionRouter,
    NcActionSeparator,
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
    },
  },
  created() {
    this.$parent = globalState.vue.app
  },
  mounted() {
    this.referenceElement = this.$refs.actions.$refs.popover.$refs.popover.$refs.reference
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
      this.open = false
      emit(this.appName + ':project-popup', {
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
      // await this.$nextTick()
      if (this.open) {
        this.open = false
        // await this.$nextTick()
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
    openMenu(x, y) {
      this.setPosition(x, y)
      this.open = true
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
