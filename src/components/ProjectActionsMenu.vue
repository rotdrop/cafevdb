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
      <NcActionCaption v-if="showProjectName"
                       :name="projectName"
      />
      <NcActionSeparator v-if="showProjectName" />
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
<script setup lang="ts">
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
import { emit as asyncEmit, subscribe as asyncSubscribe } from '../services/async-event-bus.ts'
import { PROJECT_ACTIONS } from '../event-bus-events.ts'
import { closeNavigation } from '../services/navigation.js'
import useAppDataStore from '../stores/app-data.ts'
import type { Project } from '../stores/app-data.ts'
import { generateUrl as nextcloudGenerateUrl } from '@nextcloud/router'
import md5 from 'blueimp-md5'
import {
  computed,
  ref,
  watch,
  nextTick,
  onBeforeMount,
  onMounted,
} from 'vue'
import { useRouter } from 'vue-router/composables'
import * as Authorization from '../authorization.ts'
import * as BusEvents from '../event-bus-events.ts'
import type { Location as RouterLocation } from 'vue-router'
import Console from '../util/console.ts'
import { PROJECT_ACTIONS_MENU as COMPONENT_NAME } from '../mountable-component-names.ts'
// import type { ComponentProps } from '../mountable-component-names.ts'

const logger = new Console(COMPONENT_NAME)

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

const props = withDefaults(defineProps</* ComponentProps[typeof COMPONENT_NAME] */{
  projectId: number,
  projectName: string,
  forceProjectName?: boolean,
  enableOverviewItem?: boolean,
  testOpen?: boolean,
}>(), {
  forceProjectname: false,
  enableOverviewItem: true,
  testOpen: true,
})

const appData = useAppDataStore()

// data
const open = ref(false)
const referenceElement = ref<null|HTMLElement>(null)
const triggerButton = ref<null|NcButtonType>(null)
const positioned = ref(false)
const project = ref<null|Project>(null)

// computed
const showProjectName = computed(() => props.forceProjectName || positioned)
const projectFolder = computed(() => project.value?.folders?.projectsfolder || null)
const projectFolderLink = computed(() => nextcloudGenerateUrl('/apps/files/?dir=' + projectFolder.value))
const projectFolderLinkTarget = computed(() => md5(projectFolderLink.value))
const wikiPage = computed(() => project.value?.wikiPage || '')
const projectNotesLink = computed(() => nextcloudGenerateUrl('/apps/dokuwiki?wikiPage=' + wikiPage.value))
const projectEventsLink = computed(() => nextcloudGenerateUrl('/apps/calendar'))
const financeMode = computed(() => ((globalState?.userPermissions || 0) & Authorization.PERMISSION_FINANCE) && globalState?.financeMode)

// watchers
watch(open, (state, oldState) => {
  if (!state && positioned.value) {
    // logger.info('WATCHER CLOSE MENU')
    // this.closeMenu()
  }
  logger.info('OPEN CHANGED', state, oldState)
})
watch(() => props.projectId, async (newValue/*, oldValue */) => {
  await syncProjectData(newValue)
})

// methods
const toProjectRouteData = (template: string):RouterLocation => {
  return {
    name: 'legacy-page',
    params: {
      template,
      projectId: '' + props.projectId,
      projectName: props.projectName,
    },
  }
}
const syncProjectData = async (projectId: number) => {
  project.value = await appData.getProject(projectId) || null
  // vueSet(this.project, 'folders', this.project.folders)
}
const openProjectOverview = () => {
  open.value = false
  closeNavigation()
  asyncEmit(BusEvents.PROJECT_POPUP, {
    projectId: props.projectId,
    projectName: props.projectName,
  })
}
const openInstrumentationNumbers = (event: MouseEvent) => {
  event.preventDefault()
  open.value = false
  closeNavigation()
  asyncEmit(BusEvents.PROJECT_INSTRUMENTATION_NUMBERS_POPUP, {
    projectId: props.projectId,
    projectName: props.projectName,
  })
}
const openParticipantFields = (event: MouseEvent) => {
  event.preventDefault()
  open.value = false
  closeNavigation()
  asyncEmit(BusEvents.PROJECT_PARTICIPANT_FIELDS_POPUP, {
    projectId: props.projectId,
    projectName: props.projectName,
  })
}
const openProjectNotes = (event: MouseEvent) => {
  event.preventDefault()
  open.value = false
  closeNavigation()
  asyncEmit(BusEvents.WIKI_POPUP, {
    wikiPage: project.value!.wikiPage,
    popupTitle: t(appId, 'Project Wiki for {projectName}', { projectName: props.projectName }),
  })
}
const openProjectEvents = (event: MouseEvent) => {
  event.preventDefault()
  open.value = false
  closeNavigation()
  asyncEmit(BusEvents.PROJECT_EVENTS_POPUP, {
    projectId: props.projectId,
    projectName: props.projectName,
  })
}
const setPosition = (x?: number, y?: number) => {
  if (x !== undefined && y !== undefined) {
    referenceElement.value!.style.position = 'fixed'
    referenceElement.value!.style.left = x + 'px'
    referenceElement.value!.style.top = y + 'px'

    positioned.value = true
  } else if (positioned.value) {
        referenceElement.value!.style.position = ''
    referenceElement.value!.style.left = ''
    referenceElement.value!.style.top = ''

    positioned.value = false
  }
}
const closeMenu = async () => {
  logger.info('-> closeMenu()')
  if (open.value) {
    open.value = false
    await nextTick()
  }
  if (positioned.value) {
    // the open trigger was a context menu click, so there is not
    // point to return the focus to the menu button.
    triggerButton.value?.$el.blur()
  }
  for (let i = 0; i < 2; ++i) {
    await nextFrame()
    await nextTick()
  }
  setPosition()
  logger.info('<- closeMenu()')
}
const nextFrame = () => {
  return new Promise(resolve => requestAnimationFrame(() => {
    requestAnimationFrame(resolve)
  }))
}
const openMenu = async (x?: number, y?: number) => {
  logger.info('-> openMenu()')
  setPosition(x, y)
  open.value = true
  if (positioned.value) {
    await nextTick()
    triggerButton.value?.$el.blur()
  }
  logger.info('<- openMenu()')
}
const moveToAnchor = async (event?: MouseEvent) => {
  if (!open.value || !positioned.value) {
    return
  }
  logger.info('-> moveToAnchor()')
  event?.preventDefault()
  await closeMenu()
  await nextTick()
  openMenu()
  logger.info('<- moveToAnchor()')
}

const router = useRouter()

const getRouteHref = (route: RouterLocation) => {
  const routeProps = router.resolve(route)
  return routeProps?.href || '#'
}

const isOpen = () => open.value

// we need to expose some methods in order to allow legacy code to
// open, close and position the menu.
defineExpose({
  isOpen,
  openMenu,
  closeMenu,
})

onBeforeMount(async () => {
  await syncProjectData(props.projectId)
})

const actions = ref<null|NcActionsType>(null)

onMounted(() => {
  const origCloseMenu = actions.value!.closeMenu
  actions.value!.closeMenu = (returnFocus) => origCloseMenu(positioned.value ? false : returnFocus)
  referenceElement.value = actions.value!.$refs.popover.$refs.popover.$refs.reference
  triggerButton.value = actions.value!.$refs.triggerButton
  asyncSubscribe(PROJECT_ACTIONS, (event) => {
    const projectId = event?.projectId
    const newOpenState = event?.open
    if (!newOpenState
      && open.value
      && +projectId !== -props.projectId //
      && (+projectId <= 0 || +projectId === +props.projectId)) {
      closeMenu()
    } else if (newOpenState && projectId === props.projectId) {
      openMenu(event?.x || undefined, event?.y || undefined)
    }
  })
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
