<!--
 - Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  <LegacyPageActionsMenu ref="actions"
                         :menu-caption="projectName"
                         :enable-overview-item="enableOverviewItem"
                         :entity-id="entityId"
                         :project-id="entityId"
                         :project-name="projectName"
                         :template="template"
  >
    <template #actions>
      <NcActionRouter v-tooltip.right="tooltips['project-action:project-participants']"
                      :class="[appName + '-project-actions']"
                      :to="toProjectRouteData('project-participants')"
                      :name="t(appName, 'Participants')"
                      exact
                      :close-after-click="true"
      >
        <template #icon>
          <ProjectParticipantsIcon />
        </template>
      </NcActionRouter>
      <NcActionLink v-tooltip.right="tooltips['project-action:project-instrumentation-numbers']"
                    :class="[appName + '-project-actions']"
                    :name="t(appName, 'Instrumentation Numbers')"
                    :href="getRouteHref(toProjectRouteData('project-instrumentation-numbers'))"
                    :close-after-click="true"
                    @click="openInstrumentationNumbers"
      >
        <template #icon>
          <InstrumentationNumbersIcon />
        </template>
      </NcActionLink>
      <NcActionLink v-tooltip.right="tooltips['project-action:participant-fields']"
                    :class="[appName + '-project-actions']"
                    :name="t(appName, 'Participant Fields')"
                    :href="getRouteHref(toProjectRouteData('project-participant-fields'))"
                    :close-after-click="true"
                    @click="openParticipantFields"
      >
        <template #icon>
          <ParticipantFieldsIcon />
        </template>
      </NcActionLink>
      <NcActionSeparator />
      <NcActionLink v-tooltip.right="tooltips['project-action:files']"
                    :class="[appName + '-project-actions']"
                    :name="t(appName, 'Project Files')"
                    :href="projectFolderLink"
                    :target="projectFolderLinkTarget"
                    :close-after-click="true"
      >
        <template #icon>
          <ProjectFolderIcon />
        </template>
      </NcActionLink>
      <NcActionLink v-tooltip.right="tooltips['project-action:wiki']"
                    :class="[appName + '-project-actions']"
                    :name="t(appName, 'Project Notes')"
                    :href="projectNotesLink"
                    :close-after-click="true"
                    @click="openProjectNotes"
      >
        <template #icon>
          <ProjectNotesIcon />
        </template>
      </NcActionLink>
      <NcActionLink v-tooltip.right="tooltips['project-action:events']"
                    :class="[appName + '-project-actions']"
                    :name="t(appName, 'Events')"
                    :href="projectEventsLink"
                    :close-after-click="true"
                    @click="openProjectEvents"
      >
        <template #icon>
          <ProjectEventsIcon />
        </template>
      </NcActionLink>
      <NcActionButton v-tooltip.right="tooltips['project-action:email']"
                      :class="[appName + '-project-actions']"
                      :name="t(appName, 'Em@il')"
                      :close-after-click="true"
                      @click="openProjectEmail"
      >
        <template #icon>
          <ProjectEmailIcon />
        </template>
      </NcActionButton>
      <NcActionSeparator v-if="financeMode" />
      <NcActionRouter v-tooltip.right="tooltips['project-action:business-contacts']"
                      :class="[appName + '-project-actions']"
                      :to="toProjectRouteData('project-associates')"
                      :name="t(appName, 'Business Contacts / Associates')"
                      exact
                      :close-after-click="true"
      >
        <template #icon>
          <ProjectAssociatesIcon />
        </template>
      </NcActionRouter>
      <NcActionRouter v-tooltip.right="tooltips['project-action:sepa-bank-accounts']"
                      :class="[appName + '-project-actions']"
                      :to="toProjectRouteData('sepa-bank-accounts')"
                      :name="t(appName, 'Debit Mandates')"
                      exact
                      :close-after-click="true"
      >
        <template #icon>
          <SepaBankAccountsIcon />
        </template>
      </NcActionRouter>
      <NcActionRouter v-tooltip.right="tooltips['project-action:payments']"
                      :class="[appName + '-project-actions']"
                      :to="toProjectRouteData('project-payments')"
                      :name="t(appName, 'Payments')"
                      exact
                      :close-after-click="true"
      >
        <template #icon>
          <span class="font-currency-symbol">{{ globalState.currencySymbol }}</span>
        </template>
      </NcActionRouter>
      <NcActionRouter v-tooltip.right="tooltips['project-action:invoices']"
                      :class="[appName + '-project-actions']"
                      :to="toProjectRouteData('invoices')"
                      :name="t(appName, 'Invoices')"
                      exact
                      :close-after-click="true"
      >
        <template #icon>
          <InvoicesIcon />
        </template>
      </NcActionRouter>
      <NcActionLink v-tooltip.right="tooltips['project-action:financial-balance']"
                    :class="[appName + '-project-actions']"
                    :name="t(appName, 'Financial Balance')"
                    :href="financialBalanceLink"
                    :target="financialBalanceLinkTarget"
                    :close-after-click="true"
      >
        <template #icon>
          <ProjectFolderIcon />
        </template>
      </NcActionLink>
    </template>
  </LegacyPageActionsMenu>
</template>
<script setup lang="ts">
import LegacyPageActionsMenu from './LegacyPageActionsMenu.vue'
import {
  NcActionButton,
  NcActionLink,
  NcActionRouter,
  NcActionSeparator,
} from '@nextcloud/vue'
import globalState from '../app/globalstate.ts'
import { appName } from '../config.ts'
import { translate as t } from '@nextcloud/l10n'
import ProjectParticipantsIcon from 'vue-material-design-icons/AccountMultiple.vue'
import ProjectAssociatesIcon from 'vue-material-design-icons/Handshake.vue'
import InstrumentationNumbersIcon from 'vue-material-design-icons/CircleSlice5.vue'
import InvoicesIcon from 'vue-material-design-icons/InvoiceMultiple.vue'
import ParticipantFieldsIcon from 'vue-material-design-icons/TableAccount.vue'
import ProjectFolderIcon from 'vue-material-design-icons/Folder.vue'
import ProjectNotesIcon from 'vue-material-design-icons/MessageBulleted.vue'
import ProjectEmailIcon from 'vue-material-design-icons/EmailArrowRight.vue'
import ProjectEventsIcon from 'vue-material-design-icons/Calendar.vue'
import SepaBankAccountsIcon from 'vue-material-design-icons/BankTransfer.vue'
// import ProjectPaymentsIcon from 'vue-material-design-icons/CurrencyEur.vue' // Mmmh. l10n?
import { emit as asyncEmit } from '../services/async-event-bus.ts'
import { closeNavigation } from '../services/navigation.ts'
import useAppDataStore from '../stores/app-data.ts'
import useTooltipsStore from '../stores/tooltips.ts'
import type { Project } from '../stores/app-data.ts'
import { generateUrl as nextcloudGenerateUrl } from '@nextcloud/router'
import md5 from 'blueimp-md5'
import {
  computed,
  ref,
  watch,
  onBeforeMount,
} from 'vue'
import {
  useRoute,
  useRouter,
} from 'vue-router/composables'
import * as Authorization from '../authorization.ts'
import * as BusEvents from '../event-bus-events.ts'
import type { Location as RouterLocation } from 'vue-router'
import Console from '../util/console.ts'
import { PROJECT_ACTIONS_MENU as COMPONENT_NAME } from '../mountable-component-names.ts'
import { PROJECT_EVENTS_LISTING_NAME } from '../router/calendar-routes.ts'

const logger = new Console(COMPONENT_NAME)

const props = withDefaults(defineProps</* ComponentProps[typeof COMPONENT_NAME] */{
  enableOverviewItem?: boolean,
  entityId: number,
  projectName: string,
  template: string,
}>(), {
  enableOverviewItem: true,
})

const appData = useAppDataStore()

// data
const open = ref(false)
const positioned = ref(false)
const project = ref<null|Project>(null)

const tooltipKeys = [
  'project-infopage',
  'project-action:project-participants',
  'project-action:project-instrumentation-numbers',
  'project-action:participant-fields',
  'project-action:files',
  'project-action:wiki',
  'project-action:events',
  'project-action:email',
  'project-action:sepa-bank-accounts',
  'project-action:payments',
  'project-action:financial-balance',
]

const tooltipsProvider = useTooltipsStore()
tooltipsProvider.provideTooltips(tooltipKeys)
const tooltips = tooltipsProvider.tooltipsData

const actions = ref<null|typeof LegacyPageActionsMenu>(null)

const isOpen = () => !!actions.value?.isOpen()
const closeMenu = () => {
  actions.value && actions.value.closeMenu()
}
const openMenu = (x?: number, y?: number) => {
  actions.value && actions.value.openMenu(x, y)
}

// we need to expose some methods in order to allow legacy code to
// open, close and position the menu.
defineExpose({
  isOpen,
  openMenu,
  closeMenu,
})

// computed
const projectFolder = computed(() => project.value?.folders?.projectsFolder || null)
const projectFolderLink = computed(() => nextcloudGenerateUrl('/apps/files/?dir=' + projectFolder.value))
const projectFolderLinkTarget = computed(() => md5(projectFolderLink.value))
const wikiPage = computed(() => project.value?.wikiPage || '')
const projectNotesLink = computed(() => nextcloudGenerateUrl('/apps/dokuwiki?wikiPage=' + wikiPage.value))
const projectEventsLink = computed(() => nextcloudGenerateUrl('/apps/calendar'))
const financeMode = computed(() => ((globalState?.userPermissions || 0) & Authorization.PERMISSION_FINANCE) && globalState?.financeMode)
const projectBalanceFolder = computed(() => project.value?.folders?.balancesFolder || null)
const financialBalanceLink = computed(() => nextcloudGenerateUrl('/apps/files/?dir=' + projectBalanceFolder.value))
const financialBalanceLinkTarget = computed(() => md5(financialBalanceLink.value))

// watchers
watch(open, (state, oldState) => {
  if (!state && positioned.value) {
    // logger.info('WATCHER CLOSE MENU')
    // this.closeMenu()
  }
  logger.info('OPEN CHANGED', { state, oldState })
})
watch(() => props.entityId, async (newValue/*, oldValue */) => {
  await syncProjectData(newValue)
})

// methods
const toProjectRouteData = (template: string):RouterLocation => {
  return {
    name: 'legacy-page',
    params: {
      template,
      projectId: '' + props.entityId,
      projectName: props.projectName,
    },
  }
}
const syncProjectData = async (projectId: number) => {
  project.value = await appData.getProject(projectId) || null
  if (project.value) {
    await project.value.getFolders()
  }
  // vueSet(this.project, 'folders', this.project.folders)
}
const openInstrumentationNumbers = (event: MouseEvent) => {
  event.preventDefault()
  open.value = false
  closeNavigation()
  asyncEmit(BusEvents.PROJECT_INSTRUMENTATION_NUMBERS_POPUP, {
    projectId: props.entityId,
    projectName: props.projectName,
  })
}
const openParticipantFields = (event: MouseEvent) => {
  event.preventDefault()
  open.value = false
  closeNavigation()
  asyncEmit(BusEvents.PROJECT_PARTICIPANT_FIELDS_POPUP, {
    projectId: props.entityId,
    projectName: props.projectName,
  })
}
const openProjectNotes = (event: MouseEvent) => {
  event.preventDefault()
  open.value = false
  closeNavigation()
  asyncEmit(BusEvents.WIKI_POPUP, {
    wikiPage: project.value!.wikiPage,
    popupTitle: t(appName, 'Project Wiki for {projectName}', { projectName: props.projectName }),
  })
}
const openProjectEvents = (event: MouseEvent) => {
  event.preventDefault()
  open.value = false
  closeNavigation()
  const location = {
    name: PROJECT_EVENTS_LISTING_NAME,
    params: {
      ...currentRoute.params,
      eventsProjectName: props.projectName,
    },
    query: currentRoute.query,
  }
  return router.push(location)
}
const openProjectEmail = (event: MouseEvent) => {
  event.preventDefault()
  open.value = false
  closeNavigation()
  asyncEmit(BusEvents.EMAIL_POPUP, {
    projectId: props.entityId,
    projectName: props.projectName,
    reopen: true,
  })
}
const router = useRouter()
const currentRoute = useRoute()

const getRouteHref = (route: RouterLocation) => {
  const routeProps = router.resolve(route)
  return routeProps?.href || '#'
}

onBeforeMount(async () => {
  await syncProjectData(props.entityId)
})

</script>
<style lang="scss" scoped>
.container {
  display: flex;
  :deep(.button-vue__icon) svg {
    width: 28px;
    height: 28px;
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
}
</style>
<style lang="scss" scoped>
.#{$appName}-project-actions.project-name.app-navigation-caption {
  font-weight: bold;
  color: blue;
  font-style: italic;
  text-align: center;
  display: inline-block;
  margin: auto;
  width: 100%;
  padding: 0;
}
.font-currency-symbol {
  display: inline-block;
  width: var(--default-clickable-area);
  height: var(--default-clickable-area);
  text-align: center;
  font-size: large;
  font-weight: bold;
}
.#{$appName}-project-actions::v-deep {
  .action-link__longtext-wrapper, .action-router__longtext-wrapper {
    br {
      display:none;
    }
    .action-link__longtext, .action-router__longtext {
      &:empty {
        display:none;
      }
    }
  }
}
</style>
