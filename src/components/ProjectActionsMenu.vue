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
                      :name="t(appId, 'Project Overview')"
                      @click="openProjectOverview"
      >
        <template #icon>
          <ProjectInfoIcon />
        </template>
      </NcActionButton>
      <NcActionSeparator v-if="enableOverviewItem" />
      <NcActionRouter :to="toProjectRouteData('project-participants')"
                      :name="t(appId, 'Participants')"
                      exact
                      @click="closeMenu"
      >
        <template #icon>
          <ProjectParticipantsIcon />
        </template>
      </NcActionRouter>
      <NcActionLink :name="t(appId, 'Instrumentation Numbers')"
                    :href="getRouteHref(toProjectRouteData('project-instrumentation-numbers'))"
                    @click="openInstrumentationNumbers"
      >
        <template #icon>
          <InstrumentationNumbersIcon />
        </template>
      </NcActionLink>
      <NcActionLink :name="t(appId, 'Participant Fields')"
                    :href="getRouteHref(toProjectRouteData('project-participant-fields'))"
                    @click="openParticipantFields"
      >
        <template #icon>
          <ParticipantFieldsIcon />
        </template>
      </NcActionLink>
      <NcActionSeparator />
      <NcActionLink :name="t(appId, 'Project Files')"
                    :href="projectFolderLink"
                    :target="projectFolderLinkTarget"
                    @click="closeMenu"
      >
        <template #icon>
          <ProjectFolderIcon />
        </template>
      </NcActionLink>
      <NcActionLink :name="t(appId, 'Project Notes')"
                    :href="projectNotesLink"
                    @click="openProjectNotes"
      >
        <template #icon>
          <ProjectNotesIcon />
        </template>
      </NcActionLink>
      <NcActionLink :name="t(appId, 'Events')"
                    :href="projectEventsLink"
                    @click="openProjectEvents"
      >
        <template #icon>
          <ProjectEventsIcon />
        </template>
      </NcActionLink>
      <NcActionButton v-if="financeMode">
        Two
      </NcActionButton>
    </NcActions>
  </div>
</template>
<script lang="ts">
import {
  NcActions,
  NcActionButton,
  NcActionCaption,
  NcActionLink,
  NcActionRouter,
  NcActionSeparator,
} from '@nextcloud/vue'
import globalState from '../app/globalstate.js'
import { appName as appId } from '../config.ts'
import { translate as t } from '@nextcloud/l10n'
import ProjectInfoIcon from 'vue-material-design-icons/InformationOutline.vue'
import ProjectParticipantsIcon from 'vue-material-design-icons/AccountMultiple.vue'
import InstrumentationNumbersIcon from 'vue-material-design-icons/CircleSlice5.vue'
import ParticipantFieldsIcon from 'vue-material-design-icons/TableAccount.vue'
import ProjectFolderIcon from 'vue-material-design-icons/Folder.vue'
import ProjectNotesIcon from 'vue-material-design-icons/MessageBulleted.vue'
import ProjectEventsIcon from 'vue-material-design-icons/Calendar.vue'
import { emit as asyncEmit, subscribe as asyncSubscribe } from '@rotdrop/async-nextcloud-event-bus'
import { PROJECT_ACTIONS } from '../event-bus-events.ts'
import { closeNavigation } from '../services/navigation.js'
import mixins from '../mixins/app-mixins.ts'
import useAppDataStore from '../stores/app-data.ts'
import type { Project } from '../stores/app-data.ts'
import { generateUrl as nextcloudGenerateUrl } from '@nextcloud/router'
import wikiPopup from '../app/wiki-popup.js'
import md5 from 'blueimp-md5'
// import { set as vueSet } from 'vue'
import * as Authorization from '../authorization.ts'
import * as BusEvents from '../event-bus-events.ts'
import type { Location as RouterLocation } from 'vue-router'

type NcButtonType = {
  ref?: string,
  $el: HTMLElement,
}
type NcActionsType = {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  closeMenu(returnFocus?: boolean):Promise<any>,
  $refs: {
    popover: { $refs: { popover: { $refs: { reference: HTMLElement, } } } },
    triggerButton: NcButtonType,
  },
}

export default {
  name: 'ProjectActionsMenu',
  components: {
    InstrumentationNumbersIcon,
    NcActionButton,
    NcActionCaption,
    NcActionLink,
    NcActionRouter,
    NcActionSeparator,
    NcActions,
    ParticipantFieldsIcon,
    ProjectEventsIcon,
    ProjectFolderIcon,
    ProjectInfoIcon,
    ProjectNotesIcon,
    ProjectParticipantsIcon,
  },
  router: globalState.vue!.router,
  mixins,
  props: {
    projectId: {
      type: Number,
      required: true,
    },
    projectName: {
      default: null,
      type: String,
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
  setup() {
    const appData = useAppDataStore(globalState.vue!.store)

    return { appData }
  },
  data() {
    return {
      open: false,
      referenceElement: null as null|HTMLElement,
      triggerButton: null as null|NcButtonType,
      positioned: false,
      project: null as null|Project,
    }
  },
  computed: {
    showProjectName() {
      return this.forceProjectName || this.positioned
    },
    projectFolder() {
      return this.project?.folders?.projectsfolder || null
    },
    projectFolderLink() {
      return nextcloudGenerateUrl('/apps/files/?dir=' + this.projectFolder)
    },
    projectFolderLinkTarget() {
      return md5(this.projectFolderLink)
    },
    wikiPage() {
      return this.project?.wikiPage || ''
    },
    projectNotesLink() {
      return nextcloudGenerateUrl('/apps/dokuwiki?wikiPage=' + this.wikiPage)
    },
    projectEventsLink() {
      return nextcloudGenerateUrl('/apps/calendar')
    },
    financeMode() {
      return ((this.globalState?.userPermissions || 0) & Authorization.PERMISSION_FINANCE) && this.globalState?.financeMode
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
    async projectId(newValue/*, oldValue */) {
      this.syncProjectData(newValue)
    },
  },
  async created() {
    // @ts-expect-error $parent is readonly
    this.$parent = globalState.vue!.app
    this.syncProjectData(this.projectId)
    this.info('ACTIONS MENU OBJECT', this)
  },
  mounted() {
    const actions = (this!.$refs!.actions! as object as NcActionsType)
    const origCloseMenu = actions.closeMenu
    actions.closeMenu = (returnFocus) => origCloseMenu(this.positioned ? false : returnFocus)
    this.referenceElement = actions.$refs.popover.$refs.popover.$refs.reference
    this.triggerButton = actions.$refs.triggerButton
    asyncSubscribe(PROJECT_ACTIONS, (event) => {
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
    toProjectRouteData(template: string):RouterLocation {
      return {
        name: 'legacy-page',
        params: {
          template,
          projectId: '' + this.projectId,
          projectName: this.projectName,
        },
      }
    },
    async syncProjectData(projectId: number) {
      this.project = await this.appData.getProject(projectId) || null
      // vueSet(this.project, 'folders', this.project.folders)
    },
    openProjectOverview() {
      this.open = false
      closeNavigation()
      asyncEmit(BusEvents.PROJECT_POPUP, {
        projectId: this.projectId,
        projectName: this.projectName,
      })
    },
    openInstrumentationNumbers(event: MouseEvent) {
      event.preventDefault()
      this.open = false
      closeNavigation()
      asyncEmit(BusEvents.PROJECT_INSTRUMENTATION_NUMBERS_POPUP, {
        projectId: this.projectId,
        projectName: this.projectName,
      })
    },
    openParticipantFields(event: MouseEvent) {
      event.preventDefault()
      this.open = false
      closeNavigation()
      asyncEmit(BusEvents.PROJECT_PARTICIPANT_FIELDS_POPUP, {
        projectId: this.projectId,
        projectName: this.projectName,
      })
    },
    openProjectNotes(event: MouseEvent) {
      event.preventDefault()
      this.open = false
      closeNavigation()
      wikiPopup({
        wikiPage: this.project!.wikiPage,
        popupTitle: t(appId, 'Project Wiki for {projectName}', { projectName: this.projectName }),
      })
    },
    openProjectEvents(event: MouseEvent) {
      event.preventDefault()
      this.open = false
      closeNavigation()
      asyncEmit(BusEvents.PROJECT_EVENTS_POPUP, {
        projectId: this.projectId,
        projectName: this.projectName,
      })
    },
    setPosition(x?: number, y?: number) {
      if (x !== undefined && y !== undefined) {
        this.referenceElement!.style.position = 'fixed'
        this.referenceElement!.style.left = x + 'px'
        this.referenceElement!.style.top = y + 'px'

        this.positioned = true
      } else if (this.positioned) {
        this.referenceElement!.style.position = ''
        this.referenceElement!.style.left = ''
        this.referenceElement!.style.top = ''

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
    async openMenu(x?: number, y?: number) {
      this.info('-> openMenu()')
      this.setPosition(x, y)
      this.open = true
      if (this.positioned) {
        await this.$nextTick()
        this.triggerButton?.$el.blur()
      }
      this.info('<- openMenu()')
    },
    toggleMenu(x?: number, y?: number) {
      if (this.open) {
        this.closeMenu()
      } else {
        this.openMenu(x, y)
      }
    },
    async moveToAnchor(event?: MouseEvent) {
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
    getRouteHref(route: RouterLocation) {
      const routeProps = this.$router.resolve(route)
      return routeProps?.href || '#'
    },
  },
}
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
