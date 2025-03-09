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
  <div class="container flex-container">
    <NcAppSidebar v-show="!calendarAppSideBarActive"
                  :name="project ? t(appName, '{name} - Appointments', { name: project.name }) : t(appName, 'Appointments')"
                  :force-menu="false"
                  @close="handleClose()"
    >
      <template #description>
        <div class="global-actions-container flex-container flex-center">
          <NcActions :type="actionButtonType"
                     class="new-event-menu fc-event"
                     data-is-new="yes"
                     :aria-label="t(appName, 'create new appointments')"
                     :disabled="isLoading"
          >
            <template #icon>
              <IconNew />
            </template>
            <NcActionRouter v-for="(item, uri) in routerEventAdd"
                            :key="uri"
                            :to="item.location"
                            :exact="true"
                            :close-after-click="true"
            >
              <template #icon>
                <component :is="calendarIcons[uri]" />
              </template>
              {{ item.label }}
            </NcActionRouter>
          </NcActions>
          <NcActions :inline="1000"
                     :force-name="true"
                     :type="actionButtonType"
          >
            <NcActionButton :aria-label="t(appName, 'mark all events')"
                            :disabled="isLoading"
                            @click="markAllEvents(true)"
            >
              <template #icon>
                <DynamicSvgIcon :size="24" :data="svgEmailChecked" class="material-design-icon" />
              </template>
            </NcActionButton>
            <NcActionButton :aria-label="t(appName, 'unmark all events')"
                            :disabled="isLoading"
                            @click="markAllEvents(false)"
            >
              <template #icon>
                <DynamicSvgIcon :size="24" :data="svgEmailCross" class="material-design-icon" />
              </template>
            </NcActionButton>
            <NcActionButton :aria-label="t(appName, 'open email editor')"
                            :disabled="isLoading"
                            @click="emailEditor"
            >
              <template #icon>
                <DynamicSvgIcon :size="24" :data="svgEmailUnchecked" class="material-design-icon" />
              </template>
              {{ t(appName, 'Em@il') }}
            </NcActionButton>
            <NcActionButton :aria-label="t(appName, 'export events')"
                            :disabled="isLoading"
                            @click="exportEvents"
            >
              <template #icon>
                <IconExport />
              </template>
            </NcActionButton>
          </NcActions>
          <NcActions :type="actionButtonType">
            <template #icon>
              <IconInfo />
            </template>
            <NcActionLink :href="wikiManualUrl"
                          :target="wikiManualUrlTarget"
                          :close-after-click="true"
            >
              <template #icon>
                <IconManualOtherWindow />
              </template>
              {{ t(appId, 'Manual (other tab or window)') }}
            </NcActionLink>
            <NcActionButton :close-after-click="true"
                            @click="onUserManualPopup"
            >
              <template #icon>
                <IconManualPopup />
              </template>
              {{ t(appId, 'Manual (popup)') }}
            </NcActionButton>
          </NcActions>
          <NcButton :class="{ loading: showLoadingIndicator }"
                    :disabled="!project || isLoading"
                    :aria-label="t(appName, 'reload the project appointments')"
                    @click="syncProjectData(project?.id || -1)"
          >
            <template #icon>
              <IconReload />
            </template>
          </NcButton>
        </div>
      </template>
      <template #default>
        <ul v-for="matrixEntry in eventMatrix" :key="matrixEntry.uri">
          <NcListItem :class="['calendar-header', { 'no-events': matrixEntry.events.length === 0 }]"
                      active
                      :name="matrixEntry.name + (matrixEntry.events.length > 0 ? '' : ': ' + t(appName, 'no events'))"
                      one-line
                      bold
                      :counter-number="matrixEntry.events.length"
                      counter-type="highlighted"
                      force-display-actions
                      @click.prevent="toggleCalendarVisibility(matrixEntry)"
          >
            <template #icon>
              <component :is="calendarIcons[matrixEntry.uri]" />
            </template>
            <template v-if="matrixEntry.events.length > 0"
                      #extra-actions
            >
              <NcButton type="primary"
                        :aria-label="t(appName, 'Toggle Details')"
                        @click="toggleCalendarVisibility(matrixEntry)"
              >
                <template #icon>
                  <IconHideDetails v-if="showCalendarEvents(matrixEntry)" />
                  <IconShowDetails v-else />
                </template>
              </NcButton>
            </template>
          </NcListItem>
          <NcListItem v-for="event in matrixEntry.events"
                      v-show="showCalendarEvents(matrixEntry)"
                      :key="event.instanceId"
                      :class="['project-event', 'fc-event', { detached: event.deleted }]"
                      :to="routerEventEdit[event.instanceId] || ''"
                      :data-object-id="routerEventEdit[event.instanceId]?.params.object || ''"
                      :data-recurrence-id="routerEventEdit[event.instanceId]?.params.recurrenceId || ''"
                      :exact="true"
                      :force-display-actions="true"
                      :one-line="false"
          >
            <template #name>
              <span class="event-date">{{ briefEventDate(event) }}</span><span v-if="event.deleted" class="detached-note">&nbsp;({{ t(appName, 'detached') }})</span>
            </template>
            <template #subname>
              <span class="event-summary">{{ event.summary }}</span>
            </template>
            <template #details>
              <div class="flex-container flex-center">
                <DynamicSvgIcon v-if="attachmentMark[event.instanceId]"
                                :size="18"
                                :data="svgEmailChecked"
                                class="material-design-icon"
                />
                <IconRecordAbsence v-if="hasAbsenceField[event.instanceId]" />
              </div>
            </template>
            <template #indicator>
              <div class="flex-container flex-baseline">
                <span :class="'event-uid-' + eventSeries[event.uid]"><span>{{ eventSeriesIndicator(event) }}</span></span>
                <span :class="'event-series-uid-' + eventRelations[event.seriesUid]"><span>{{ eventRelationsIndicator(event) }}</span></span>
              </div>
            </template>
            <template #actions>
              <NcActionRadio v-if="eventRelations[event.seriesUid]"
                             v-model="actionScope[event.uid]"
                             value="single"
                             :name="'action-scope-' + event.uid"
                             :close-after-click="true"
                             @change="updateActionScope(event, 'single')"
              >
                {{ t(appId, 'act only on this event') }}
              </NcActionRadio>
              <NcActionRadio v-if="eventSeries[event.uid]"
                             v-model="actionScope[event.uid]"
                             value="series"
                             :name="'action-scope-' + event.uid"
                             :close-after-click="true"
                             @change="updateActionScope(event, 'series')"
              >
                {{ t(appId, 'act on the event series') }}
              </NcActionRadio>
              <NcActionRadio v-if="eventRelations[event.seriesUid]"
                             v-model="actionScope[event.uid]"
                             value="related"
                             :name="'action-scope-' + event.uid"
                             :close-after-click="true"
                             @change="updateActionScope(event, 'related')"
              >
                {{ t(appId, 'act on all related events') }}
              </NcActionRadio>
              <NcActionSeparator v-if="eventRelations[event.seriesUid]" />
              <NcActionButton type="checkbox"
                              :close-after-click="true"
                              :disabled="!mutationsAllowed || !!event.deleted"
                              @click="toggleAbsenceField(event)"
              >
                <template #icon>
                  <IconRecordAbsence v-if="hasAbsenceField[event.instanceId]" class="icon-record-absence" />
                  <IconDoNotRecordAbsence v-else class="icon-do-not-record-absence" />
                </template>
                {{ t(appName, 'record absence') }}
              </NcActionButton>
              <NcActionButton :disabled="!!event.deleted"
                              type="checkbox"
                              :close-after-click="true"
                              @click="toggleAttachmentMark(event)"
              >
                <template #icon>
                  <DynamicSvgIcon :size="24"
                                  :data="attachmentMark[event.instanceId] ? svgEmailChecked : svgEmailUnchecked"
                                  class="material-design-icon"
                  />
                </template>
                {{ t(appName, 'mark for download / em@il') }}
              </NcActionButton>
              <NcActionRouter :to="routerEventEdit[event.instanceId] || ''"
                              :exact="true"
                              :close-after-click="true"
              >
                <template #icon>
                  <IconEventEdit />
                </template>
                {{ t(appName, 'edit') }}
              </NcActionRouter>
              <NcActionLink :href="calendarAppEventEdit[event.instanceId] || ''"
                            :target="calendarAppTarget"
                            :close-after-click="true"
              >
                <template #icon>
                  <IconCalendar />
                </template>
                {{ t(appName, 'open in calendar-app') }}
              </NcActionLink>
              <NcActionButton :close-after-click="true"
                              :disabled="!mutationsAllowed"
                              @click="handleDeleteEvent(matrixEntry, event)"
              >
                <template #icon>
                  <IconEventDelete />
                </template>
                {{ t(appName, 'delete') }}
              </NcActionButton>
              <NcActionButton v-if="!event.deleted"
                              :close-after-click="true"
                              :disabled="!mutationsAllowed"
                              @click="handleProjectLink(event, false)"
              >
                <template #icon>
                  <IconEventDetach />
                </template>
                {{ t(appName, 'detach from project') }}
              </NcActionButton>
              <NcActionButton v-else
                              :close-after-click="true"
                              :disabled="!mutationsAllowed"
                              @click="handleProjectLink(event, true)"
              >
                <template #icon>
                  <IconEventAttach />
                </template>
                {{ t(appName, 'reattach to project') }}
              </NcActionButton>
            </template>
          </NcListItem>
        </ul>
      </template>
    </NcAppSidebar>
    <div class="app-calendar">
      <router-view />
    </div>
  </div>
</template>
<script setup lang="ts">
import { appName } from '../config.ts'
import { translate as t } from '@nextcloud/l10n'
import {
  NcActionButton,
  NcActionLink,
  NcActionRadio,
  NcActionRouter,
  NcActionSeparator,
  NcActions,
  NcAppSidebar,
  NcButton,
  NcListItem,
} from '@nextcloud/vue'
import IconCalendar from 'vue-material-design-icons/Calendar.vue'
import IconConcerts from 'vue-material-design-icons/TimerMusicOutline.vue'
import IconDoNotRecordAbsence from 'vue-material-design-icons/AccountMultipleOutline.vue'
import IconEventDelete from 'vue-material-design-icons/CalendarRemove.vue'
import IconEventDetach from 'vue-material-design-icons/LinkVariantOff.vue'
import IconEventAttach from 'vue-material-design-icons/LinkVariant.vue'
import IconEventEdit from 'vue-material-design-icons/CalendarEdit.vue'
import IconExport from 'vue-material-design-icons/CalendarExport.vue'
import IconFinance from 'vue-material-design-icons/BankTransfer.vue'
import IconHideDetails from 'vue-material-design-icons/UnfoldLessHorizontal.vue'
import IconInfo from 'vue-material-design-icons/InformationVariant.vue'
import IconManagement from 'vue-material-design-icons/AccountGroup.vue'
import IconManualOtherWindow from 'vue-material-design-icons/OpenInNew.vue'
import IconManualPopup from 'vue-material-design-icons/MessageTextOutline.vue'
import IconNew from 'vue-material-design-icons/Plus.vue'
import IconOther from 'vue-material-design-icons/CogOutline.vue'
import IconRecordAbsence from 'vue-material-design-icons/AccountMultipleCheckOutline.vue'
import IconRehearsals from 'vue-material-design-icons/AccountMusicOutline.vue'
import IconReload from 'vue-material-design-icons/Reload.vue'
import IconShowDetails from 'vue-material-design-icons/UnfoldMoreHorizontal.vue'
import DynamicSvgIcon from './DynamicSvgIcon.vue'
import svgEmailChecked from '../../img/email-new-yes-path.svg?raw'
import svgEmailUnchecked from '../../img/email-new-path.svg?raw'
import svgEmailCross from '../../img/email-new-x-path.svg?raw'
import useAppDataStore from '../stores/app-data.ts'
import useErrorHandlerStore from '../stores/error-handler.ts'
import { AppError } from '../types/errors.ts'
import { generateUrl } from '@nextcloud/router'
import generateAppUrl from '../toolkit/util/generate-url.js'
import moment from '@nextcloud/moment'
import axios from '@nextcloud/axios'
import type {
  CalendarUris,
  EventMatrixEntry,
  EventMatrixEvent,
  Project,
  ProjectEventMatrix,
} from '../stores/app-data.ts'
import {
  ref,
  watch,
  computed,
  nextTick,
  onBeforeMount,
  onUnmounted,
  set as vueSet,
} from 'vue'
import type {
  VueConstructor,
  WatchStopHandle,
} from 'vue'
import {
  useRoute,
  useRouter,
  onBeforeRouteUpdate,
} from 'vue-router/composables'
import type { RouteRecord } from 'vue-router'
import capitalize from 'capitalize'
import {
  dokuWikiSection,
  dokuWikiUrl,
  dokuWikiUrlTarget,
} from '../util/doku-wiki.ts'
import {
  LEGACY_UPDATE_EVENTS_SELECTION,
  EMAIL_POPUP,
  WIKI_POPUP,
} from '../event-bus-events.ts'
import {
  emit as asyncEmit,
  subscribe as asyncSubscribe,
  unsubscribe as asyncUnSubscribe,
} from '../services/async-event-bus.ts'
import calendarStoreSetup from '../services/calendar-store-setup.ts'
import Console from '../util/console.ts'
import md5 from 'blueimp-md5'
import { parse as parseContentDisposition } from 'content-disposition'
// _at_ts-expect-error: 7016
import useCalendarObjectInstance from '@nextcloud/app-calendar/src/store/calendarObjectInstance.js'
import useCalendarObjects from '@nextcloud/app-calendar/src/store/calendarObjects.js'
import { storeToRefs } from 'pinia'
import type { Store } from 'pinia'
import type { EventArgs } from '@rotdrop/async-nextcloud-event-bus'
import {
  CALENDAR_APP_ROUTES,
  PROJECT_EVENTS_LISTING_NAME,
} from '../router/calendar-routes.ts'

const COMPONENT_NAME = PROJECT_EVENTS_LISTING_NAME

const logger = new Console(COMPONENT_NAME)

const errorHandlerProvider = useErrorHandlerStore()

const errorHandler = errorHandlerProvider.getHandler()

const props = withDefaults(defineProps<{
  projectId: number,
}>(), {})

const appData = useAppDataStore()

const router = useRouter()
const currentRoute = useRoute()

const isLoading = ref(true)
const isWikiLoading = ref(false)
const showLoadingIndicator = computed(() => isLoading.value || isWikiLoading.value)

let prev: undefined|RouteRecord
for (const match of currentRoute.matched) {
  if (match.name === PROJECT_EVENTS_LISTING_NAME) {
    break
  }
  prev = match
}
const origin = prev
  ? {
    location: {
      name: prev.name,
      param: currentRoute.params,
      query: currentRoute.query,
    },
    transition: currentRoute.transition,
  }
  : {
    location: {
      name: 'home',
    },
    transition: 'unknown',
  }

onBeforeMount(() => {
  logger.debug('CURRENT ROUTE', { currentRoute: { ...currentRoute } })
})
onBeforeRouteUpdate((to, from, next) => {
  logger.debug('ON BEFORE ROUTE UPDATE', {
    to: { ...to },
    from: { ...from },
    origin: { ...(origin || {}) },
  })
  if (origin.location.query && to.query.hash) {
    origin.location.query.hash = to.query.hash
  }
  next()
})

const handleClose = () => {
  if (origin?.transition === 'push') {
    router.go(-1) // maybe we want to avoid this altogether ...
  } else if (origin) {
    router.push(origin.location)
  }
}

const project = ref<null | Project>(null)
const projectEventMatrix = computed<undefined | ProjectEventMatrix>(() => project.value?.eventMatrix)

const calendarOrdering: { [Key in CalendarUris|'']: number } = {
  concerts: 0,
  rehearsals: 10,
  management: 20,
  finance: 20,
  other: 40,
  '': 99,
}
const emptyEventMatrix: EventMatrixEntry[] = []
for (const uri of Object.keys(calendarOrdering) as ((CalendarUris|'')[])) {
  emptyEventMatrix.push({ name: uri, uri, calendarId: -1, urlPath: '', events: [] })
}
emptyEventMatrix.sort((a, b) => calendarOrdering[a.uri] - calendarOrdering[b.uri])

const eventMatrix = computed<EventMatrixEntry[]>(
  () => !projectEventMatrix.value
    ? emptyEventMatrix
    : Object.values(projectEventMatrix.value.matrix).sort((a, b) => calendarOrdering[a.uri] - calendarOrdering[b.uri]),
)

// const popOverActive = computed(() => currentRoute.path.includes('/popover/'))
const calendarAppSideBarActive = computed(() => currentRoute.path.includes('/sidebar/'))

const actionButtonType = ref('secondary')

const wikiManualSection = computed(() => dokuWikiSection([
  appName,
  'documentation',
  'user-manual',
  'projects',
  'project-events',
]))
const wikiManualUrl = computed(() => dokuWikiUrl(wikiManualSection.value))
const wikiManualUrlTarget = computed(() => dokuWikiUrlTarget(wikiManualSection.value))
const onUserManualPopup = async () => {
  isWikiLoading.value = true
  await asyncEmit(WIKI_POPUP, {
    wikiPage: wikiManualSection.value,
    popupTitle: t(appName, 'User Manual: {section}', { section: t(appName, 'Project Events') }, 0, { escape: false }),
  })
  isWikiLoading.value = false
}

interface CalendarEditLocation {
  name: string,
  params: {
    object: string,
    recurrenceId: number,
    context: string,
  },
  query: Record<string, string>,
}

interface CalendarAddLocation {
  name: string,
  params: {
    allDay: boolean,
    dtstart: number, // seconds
    dtend: number, // secons
    context: string,
  },
  query: Record<string, string>,
}

const calendarIcons: { [Key in CalendarUris|'']?: VueConstructor } = {
  management: IconManagement,
  finance: IconFinance,
  concerts: IconConcerts,
  rehearsals: IconRehearsals,
  other: IconOther,
  '': IconCalendar,
}

type ActionScope = 'single'|'series'|'related'

const expandedState = ref<{ [Key in CalendarUris]?: boolean }>({})
const hasAbsenceField = ref<Record<string, boolean> >({})
const attachmentMark = ref<Record<string, boolean> >({})
const routerEventEdit = ref<Record<string, CalendarEditLocation>>({})
const routerEventAdd = ref<Record<string, { location: CalendarAddLocation, label: string }>>({})
const calendarAppEventEdit = ref<Record<string, string> >({})
const calendarAppTarget = md5(appName + ': event edit in calendar app sidebar')
const actionScope = ref<Record<string, ActionScope> >({})

const eventSeries = ref<Record<string, number> >({})
const eventRelations = ref<Record<string, number>>({})

const relatedEvents: Record<string, EventMatrixEvent[]> = {}
const seriesEvents: Record<string, EventMatrixEvent[]> = {}

// Block other async reload request until one has finished
const eventListLock = Promise.withResolvers<void>()
eventListLock.resolve()

const aquireEventListLock = async () => {
  let promise: Promise<void>
  let count = 0
  do {
    logger.debug('ATTEMPT TO AQUIRE EVENT LIST LOCK', ++count)
    await (promise = eventListLock.promise)
  } while (promise !== eventListLock.promise)
  logger.debug('AQUIRED EVENT LIST LOCK', count)
  return Object.assign(eventListLock, Promise.withResolvers<void>())
}

const releaseEventListLock = () => {
  logger.debug('RELEASE EVENT LIST LOCK')
  eventListLock.resolve()
}

const syncProjectData = async (projectId: number) => {
  await aquireEventListLock()
  isLoading.value = true
  project.value = await appData.getProject(projectId) || null
  if (project.value) {
    await Promise.allSettled([
      project.value.getCalendarEvents(),
      project.value.getEventMatrix(),
    ])
    if (projectEventMatrix.value) {
      let seriesCounter = 1
      let relationsCounter = 1
      const calendarNames: { [Key in CalendarUris | '']?: string } = {}
      for (const entry of Object.values(projectEventMatrix.value.matrix)) {
        vueSet(expandedState.value, entry.uri, false || expandedState.value?.[entry.uri])
        calendarNames[entry.uri] = entry.name
        if (entry.uri !== '') {
          const name = 'NewPopoverView'
          const label = capitalize(t(appName, entry.uri))
          const context = {
            categories: [project.value.name, entry.uri, label],
            calendarId: btoa(entry.urlPath),
            title: label + ', ' + project.value.name,
          }
          if (entry.uri === 'rehearsals') {
            context.categories.push(projectEventMatrix.value.categories.L10N.recordAbsence)
          }
          const nowSeconds = Math.round(Date.now() / 1000)
          const params = {
            allDay: true,
            dtstart: nowSeconds,
            dtend: nowSeconds,
            context: btoa(JSON.stringify(context)),
          }
          const query = currentRoute.query
          vueSet(routerEventAdd.value, entry.uri, { location: { name, params, query }, label })
        }
        for (const key in Object.keys(eventRelations.value)) {
          delete eventRelations.value[key]
        }
        for (const key in Object.keys(eventSeries.value)) {
          delete eventSeries.value[key]
        }
        for (const event of entry.events) {
          vueSet(hasAbsenceField.value, event.instanceId, +(event.absenceField || 0) > 0)
          vueSet(attachmentMark.value, event.instanceId, false || attachmentMark.value?.[event.instanceId])
          vueSet(actionScope.value, event.uid, actionScope.value?.[event.uid] || 'single')
          if (event.seriesUid) {
            if (eventRelations.value[event.seriesUid] === undefined) {
              vueSet(eventRelations.value, event.seriesUid, relationsCounter++)
              relatedEvents[event.seriesUid] = []
            }
            relatedEvents[event.seriesUid].push(event)
          }
          if (+event.recurrenceId > 0) {
            if (eventSeries.value[event.uid] === undefined) {
              vueSet(eventSeries.value, event.uid, seriesCounter++)
              seriesEvents[event.uid] = []
            }
            seriesEvents[event.uid].push(event)
          }
          const name = 'EditPopoverView'
          const context = {}
          const eventObject = btoa(event.urlPath)
          const params = {
            object: eventObject,
            recurrenceId: event.times.start.stamp, // event.recurrenceId is different
            context: btoa(JSON.stringify(context)),
          }
          const query = currentRoute.query
          vueSet(routerEventEdit.value, event.instanceId, { name, params, query })
          vueSet(calendarAppEventEdit.value, event.instanceId, generateUrl('/apps/calendar/{view}/{timeRange}/edit/{mode}/{objectId}/{recurrenceId}', {
            view: 'timeGridWeek',
            timeRange: moment(
              event.start.date + ' ' + event.start.timezone,
              'YYYY-MM-DD HH:mm:ss.SSSSSS zz',
            ).format('YYYY-MM-DD'),
            mode: 'sidebar',
            objectId: params.object,
            recurrenceId: params.recurrenceId,
          }))
          // check if the current route contains a calendar app component
          if (CALENDAR_APP_ROUTES.includes(currentRoute.name!)) {
            if (eventObject === currentRoute.params.object) {
              expandedState.value[entry.uri] = true
              for (const instance of Object.values(currentRoute.matched[currentRoute.matched.length - 1].instances)) {
                // @ts-expect-error: 2339
                if (typeof instance.repositionPopover === 'function') {
                  nextTick().then(() => {
                    // @ts-expect-error: 2339
                    instance.repositionPopover()
                  })
                }
              }
            }
          }
        }
      }
      for (const entry of emptyEventMatrix) {
        entry.name = calendarNames[entry.uri] || entry.uri
      }
    }
  }
  isLoading.value = false
  releaseEventListLock()
  logger.debug('DERIVED EVENT MATRIX DATA', {
    eventSeries: { ...eventSeries.value },
    eventRelations: { ...eventRelations.value },
    hasAbsenceField: { ...hasAbsenceField.value },
    routerEventEdit: { ...routerEventEdit.value },
    routerEventAdd: { ...routerEventAdd.value },
    calendarAppEventEdit: { ...calendarAppEventEdit.value },
  })
}

const toggleCalendarVisibility = (entry: EventMatrixEntry) => {
  expandedState.value[entry.uri] = !expandedState.value[entry.uri]
}
const showCalendarEvents = (entry: EventMatrixEntry) => !!expandedState.value[entry.uri]

const briefEventDate = (event: EventMatrixEvent) => {
  const times = event.times
  if (times.start.date === times.end.date) {
    if (times.allday) {
      return times.start.date
    }
    if (times.start.time === '00:00') {
      return `${times.start.date}, till ${times.end.time}`
    }
    return `${times.start.date}, ${times.start.time}`
  } else {
    return `${times.start.date} - ${times.end.date}`
  }
}

const updateActionScope = (event: EventMatrixEvent, scope: ActionScope) => {
  // transition to single: all related events have their scope set to single
  // transition to series: non-series events transition to single
  // transition to related: all related events have their scope set to related
  switch (scope) {
  case 'single':
    if (!event.seriesUid) {
      return
    }
    for (const related of relatedEvents[event.seriesUid]) {
      actionScope.value[related.uid] = scope
    }
    break
  case 'series':
    for (const related of relatedEvents[event.seriesUid]) {
      if (related.uid !== event.uid) {
        actionScope.value[related.uid] = 'single'
      }
    }
    break
  case 'related':
    for (const related of relatedEvents[event.seriesUid]) {
      actionScope.value[related.uid] = scope
    }
    break
  }
}

interface LegacyPostEvent {
  calendarId: number,
  uri: string,
  recurrenceId?: number,
}

const legacyEventSelection: Record<string, string> = {}

const updateAttachmentMark = (event: EventMatrixEvent, mark: boolean) => {
  attachmentMark.value[event.instanceId] = mark
  if (!mark) {
    delete legacyEventSelection[event.instanceId]
  } else {
    const postEvent = {
      calendarId: event.calendarId,
      uri: event.uri,
      recurrenceId: event.recurrenceId || undefined,
    }
    legacyEventSelection[event.instanceId] = JSON.stringify(postEvent)
  }
}

const legacyEventsSelectionHandler = asyncSubscribe(
  LEGACY_UPDATE_EVENTS_SELECTION,
  (event) => {
    if (event.origin === COMPONENT_NAME) {
      return
    }
    for (const instanceId of Object.keys(legacyEventSelection)) {
      delete legacyEventSelection[instanceId]
    }
    for (const instanceId of Object.keys(attachmentMark.value)) {
      attachmentMark.value[instanceId] = false
    }
    for (const legacyItem of event.selection) {
      const legacyEvent: LegacyPostEvent = JSON.parse(legacyItem)
      const instanceId = legacyEvent.uri + (legacyEvent.recurrenceId ? '@' + legacyEvent.recurrenceId : '')
      legacyEventSelection[instanceId] = legacyItem
      attachmentMark.value[instanceId] = true
    }
  },
)

const toggleAttachmentMark = (event: EventMatrixEvent) => {
  const mark = !attachmentMark.value[event.instanceId]
  updateAttachmentMark(event, mark)
  switch (actionScope.value[event.uid]) {
  case 'single':
    break
  case 'related':
    for (const related of relatedEvents[event.seriesUid]) {
      updateAttachmentMark(related, mark && !related.deleted)
    }
    break
  case 'series':
    for (const sibling of seriesEvents[event.uid]) {
      updateAttachmentMark(sibling, mark && !sibling.deleted)
    }
    break
  }
  asyncEmit(LEGACY_UPDATE_EVENTS_SELECTION, {
    origin: COMPONENT_NAME,
    projectId: project.value!.id,
    projectName: project.value!.name,
    selection: Object.values(legacyEventSelection),
  })
}

const markAllEvents = (mark: boolean) => {
  if (!projectEventMatrix.value) {
    return
  }
  for (const entry of Object.values(projectEventMatrix.value.matrix)) {
    for (const event of entry.events) {
      updateAttachmentMark(event, mark && !event.deleted)
    }
  }
  asyncEmit(LEGACY_UPDATE_EVENTS_SELECTION, {
    projectId: project.value!.id,
    projectName: project.value!.name,
    selection: Object.values(legacyEventSelection),
  })
}

const emailEditor = () => {
  if (!project.value) {
    return Promise.resolve()
  }
  const event: EventArgs[typeof EMAIL_POPUP] = {
    projectId: project.value.id,
    projectName: project.value.name,
    post: {
      eventSelect: Object.values(legacyEventSelection),
    },
  }
  return asyncEmit(EMAIL_POPUP, event)
}

/**
 * Generate a download via blob after calling back to the server.
 */
const exportEvents = async () => {
  if (!project.value || !projectEventMatrix.value) {
    return
  }
  const post = {
    projectId: project.value.id,
    projectName: project.value.name,
    eventSelect: Object.values(legacyEventSelection),
  }
  const url = generateAppUrl('projects/events/download')
  try {
    const response = await axios.post(url, post)
    logger.info('RESPONSE', response)
    const contentType = response.headers['content-type'] || 'application/octetstream'
    let fileName = 'download'
    const contentDisposition = response.headers['content-disposition']
    if (contentDisposition) {
      const contentMeta = parseContentDisposition(contentDisposition)
      fileName = contentMeta.parameters.filename || fileName
    }
    const blob = new Blob([response.data], { type: contentType })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(blob)
    link.download = fileName
    link.click()
    URL.revokeObjectURL(link.href)
  } catch (error) {
    errorHandler(new AppError({ component: COMPONENT_NAME }, t(appName, 'Unable to export selected events.')))
  }
}

interface CalendarObject {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  [key: string]: any,
  calendarId: string,
}

interface CalendarObjectInstance {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  [key: string]: any,
  startDate: Date,
  endDate: Date,
  description: null|string,
  categories: string[],
  isAllDay: boolean,
  title: string,
  location: null|string,

}

type CalendarObjectsStore = Store<
  'calendarObjects',
  {
    modificationCount: number,
  }
>

type CalendarObjectInstanceStore = Store<
  'calendarObjectInstance',
  {
    isNew: boolean|null,
    calendarObject: CalendarObject|null,
    calendarObjectInstance: CalendarObjectInstance|null,
    existingEvent: {
      objectId: string|null,
      recurrenceId: number|null,
    },
    getCalendarObjectInstanceByObjectIdAndRecurrenceId: (arg: { objectId: string, recurrenceId: number })
      => Promise<{
        calendarObject: CalendarObject,
        calendarObjectInstance: CalendarObjectInstance,
      }>,
    addCategory: (arg: { calendarObjectInstance: CalendarObjectInstance, category: string }) => void,
    removeCategory: (arg: { calendarObjectInstance: CalendarObjectInstance, category: string }) => void,
    saveCalendarObjectInstance: (arg: { thisAndAllFuture: boolean, calendarId: string }) => Promise<void>,
    deleteCalendarObjectInstance: (arg: { thisAndAllFuture: boolean }) => Promise<void>,
  }
>

let calendarObjectsStore: CalendarObjectsStore
let calendarObjectInstanceStore: CalendarObjectInstanceStore
const mutationsAllowed = ref(false)
let stopModificationCountWatch: WatchStopHandle

const syncEventListTrigger = ref(false)

// some mutations do not require a reload, so pause the mutation
// observer for those
let ignoreCalendarObjectMutations = false

calendarStoreSetup().then((_arg) => {
  calendarObjectInstanceStore = useCalendarObjectInstance()
  calendarObjectsStore = useCalendarObjects()
  logger.info('STORES', {
    calendarObjectInstanceStore,
    calendarObjectsStore,
  })
  mutationsAllowed.value = true
  const { modificationCount } = storeToRefs(calendarObjectsStore)
  logger.debug('MOD COUNT REF', { modificationCount })
  stopModificationCountWatch = watch(
    modificationCount,
    (value) => {
      logger.info('OBSERVED MODIFICATION COUNT CHANGE', value)
      if (!value || !props.projectId) {
        return
      }
      if (!ignoreCalendarObjectMutations) {
        syncEventListTrigger.value = true
      }
    },
  )
})

const setCalendarObjectInstance = (event: EventMatrixEvent) => {
  return calendarObjectInstanceStore.getCalendarObjectInstanceByObjectIdAndRecurrenceId({
    objectId: routerEventEdit.value[event.instanceId].params.object,
    recurrenceId: routerEventEdit.value[event.instanceId].params.recurrenceId,
  })
}

const mutateCategory = async (event: EventMatrixEvent, category: string, enable: boolean) => {
  const { calendarObjectInstance, calendarObject } = await setCalendarObjectInstance(event)
  if (enable) {
    calendarObjectInstanceStore.addCategory({ calendarObjectInstance, category })
    logger.info('ADD CATETGORY', {
      event,
      category,
      calendarObjectInstance,
      calendarObject,
    })
  } else {
    calendarObjectInstanceStore.removeCategory({ calendarObjectInstance, category })
    logger.info('REMOVE CATETGORY', {
      event,
      category,
      calendarObjectInstance,
      calendarObject,
    })
  }
  await calendarObjectInstanceStore.saveCalendarObjectInstance({
    thisAndAllFuture: false,
    calendarId: calendarObject.calendarId,
  })
}

const mutateAbsenceField = async (event: EventMatrixEvent, enable: boolean) => {
  await mutateCategory(event, projectEventMatrix.value!.categories.L10N.recordAbsence, enable)
  if (enable) {
    event.absenceField = 9999
    hasAbsenceField.value[event.instanceId] = true
  } else {
    event.absenceField = null
    hasAbsenceField.value[event.instanceId] = false
  }
}

const toggleAbsenceField = async (event: EventMatrixEvent) => {
  if (!calendarObjectInstanceStore || !projectEventMatrix.value) {
    return
  }
  await aquireEventListLock()
  ignoreCalendarObjectMutations = true
  const newValue = !hasAbsenceField.value[event.instanceId]
  try {
    switch (actionScope.value[event.uid]) {
    case 'single':
      await mutateAbsenceField(event, newValue)
      break
    case 'related':
      for (const related of relatedEvents[event.seriesUid]) {
        await mutateAbsenceField(related, newValue && !related.deleted)
      }
      break
    case 'series':
      for (const sibling of seriesEvents[event.uid]) {
        await mutateAbsenceField(sibling, newValue && !sibling.deleted)
      }
      break
    }
  } catch (error) {
    errorHandler(new AppError({ component: COMPONENT_NAME }, t(appName, 'Unable to modify the absence field.')))
  }
  ignoreCalendarObjectMutations = false
  releaseEventListLock()
}

const singleEventProjectLink = async (event: EventMatrixEvent, linkToProject: boolean) => {
  await mutateCategory(event, project.value!.name, linkToProject)
  if (linkToProject) {
    event.deleted = null
  } else {
    event.deleted = (new Date()).toISOString()
  }
}

const handleProjectLink = async (event: EventMatrixEvent, linkToProject: boolean) => {
  if (!calendarObjectInstanceStore || !projectEventMatrix.value) {
    return
  }
  await aquireEventListLock()
  ignoreCalendarObjectMutations = true
  try {
    switch (actionScope.value[event.uid]) {
    case 'single':
      await singleEventProjectLink(event, linkToProject)
      break
    case 'related': {
      for (const related of relatedEvents[event.seriesUid]) {
        await singleEventProjectLink(related, linkToProject)
      }
      break
    }
    case 'series': {
      for (const sibling of seriesEvents[event.uid]) {
        await singleEventProjectLink(sibling, linkToProject)
      }
      break
    }
    }
  } catch (error) {
    errorHandler(
      new AppError(
        { component: COMPONENT_NAME },
        t(appName, 'Unable to detach events from the project.'),
        { cause: error },
      ),
    )
  }
  ignoreCalendarObjectMutations = false
  releaseEventListLock()
}

const deleteSingleEvent = async (matrixEntry: EventMatrixEntry, event: EventMatrixEvent) => {
  // const { calendarObjectInstance, calendarObject } = await setCalendarObjectInstance(event)
  await setCalendarObjectInstance(event)
  // logger.debug('DELETE EVENT', {
  //   calendarObjectInstance,
  //   calendarObject,
  // })
  await calendarObjectInstanceStore.deleteCalendarObjectInstance({ thisAndAllFuture: false })
  // logger.debug('AFTER DELETE')

  // if one of the event editors is open we should close it ...
  if ((currentRoute.name === 'EditPopoverView' || currentRoute.name === 'EditSidebarView')
      && currentRoute.params.object === routerEventEdit.value[event.instanceId].params.object
      && +currentRoute.params.recurrenceId === +routerEventEdit.value[event.instanceId].params.recurrenceId) {
    // as the event has been deleted, a router.replace() should be
    // appropriate, as navigating back would lead to an error anyway.
    const location = {
      name: COMPONENT_NAME,
      params: { ...currentRoute.params },
      query: { ...currentRoute.query },
    }
    await router.replace(location)
  }

  matrixEntry.events.splice(matrixEntry.events.indexOf(event), 1)
  if (event.seriesUid) {
    relatedEvents[event.seriesUid].splice(relatedEvents[event.seriesUid].indexOf(event), 1)
    if (+event.recurrenceId > 0) {
      seriesEvents[event.uid].splice(seriesEvents[event.uid].indexOf(event), 1)
    }
  }
  delete routerEventEdit.value[event.instanceId]
  delete calendarAppEventEdit.value[event.instanceId]
  delete hasAbsenceField.value[event.instanceId]
  delete attachmentMark.value[event.instanceId]
}

const handleDeleteEvent = async (matrixEntry: EventMatrixEntry, event: EventMatrixEvent) => {
  if (!calendarObjectInstanceStore || !projectEventMatrix.value) {
    return
  }
  await aquireEventListLock()
  ignoreCalendarObjectMutations = true
  try {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    switch (actionScope.value[event.uid]) {
    case 'single':
      await deleteSingleEvent(matrixEntry, event)
      break
    case 'related': {
      const collection = [...relatedEvents[event.seriesUid]]
      for (const related of collection) {
        await deleteSingleEvent(matrixEntry, related)
      }
      break
    }
    case 'series': {
      const collection = [...seriesEvents[event.uid]]
      for (const sibling of collection) {
        await deleteSingleEvent(matrixEntry, sibling)
      }
      break
    }
    }
  } catch (error) {
    errorHandler(
      new AppError(
        { component: COMPONENT_NAME },
        t(appName, 'Unable to delete events.'), { cause: error },
      ),
    )
  }
  ignoreCalendarObjectMutations = false
  releaseEventListLock()
}

const eventSeriesIndicator = (event: EventMatrixEvent) =>
  +event.recurrenceId === 0
    ? ''
    : String.fromCharCode('A'.charCodeAt(0) + eventSeries.value[event.uid] - 1)

const eventRelationsIndicator = (event: EventMatrixEvent) =>
  event.seriesUid
    ? String.fromCharCode('α'.charCodeAt(0) + eventRelations.value[event.seriesUid] - 1)
    : ''

syncProjectData(props.projectId).then(() => logger.debug('Project-data has been synced'))
watch(() => props.projectId, async (newValue/*, oldValue */) => {
  await syncProjectData(newValue)
})

watch(syncEventListTrigger, async (value) => {
  if (!value) {
    return
  }
  // the the start date has changen the we probably have to chance the route
  if (currentRoute.name === 'EditPopoverView' || currentRoute.name === 'EditSidebarView') {
    const recurrenceId = Math.round(calendarObjectInstanceStore.calendarObjectInstance!.startDate.getTime() / 1000)
    if (+currentRoute.params.recurrenceId !== recurrenceId) {
      const location: CalendarEditLocation = {
        name: currentRoute.name!,
        params: {
          ...currentRoute.params as { object: string, context: string },
          recurrenceId,
        },
        // @ts-expect-error: 2322 why???
        query: currentRoute.query,
      }
      // @ts-expect-error: 2769 why???
      await router.replace(location)
    }
  }
  await syncProjectData(props.projectId)
  syncEventListTrigger.value = false
})

onUnmounted(() => {
  asyncUnSubscribe(LEGACY_UPDATE_EVENTS_SELECTION, legacyEventsSelectionHandler)
  if (stopModificationCountWatch) {
    stopModificationCountWatch()
  }
})

</script>
<style scoped lang="scss">
@use "sass:list";
@import '../../style/flex.scss';
@import '../../style/color-palette.scss';
.icon-do-not-record-absence {
  opacity: 0.4;
}
.global-actions-container {
  gap: calc((var(--default-clickable-area) - 16px) / 2 / 2);
}
@for $index from 1 through 8 {
  .event-series-uid-#{$index}, .event-uid-#{$index} {
    color: list.nth($colorPalette, $index);
    background-color: list.nth($colorPalette, $index);
    & span {
      filter: invert(100%);
      background-color: rgba(1, 1, 1, 0);
    }
  }
}
.project-event {
  &.detached {
    .event-date, .event-summary {
      font-weight: normal;
      text-decoration: line-through;
      opacity: 0.5;
    }
  }
}
</style>
