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
  <div class="container flex-container">
    <NcAppSidebar v-show="!calendarAppFullActive"
                  :name="project ? t(appName, '{name} - Appointments', { name: project.name }) : t(appName, 'Appointments')"
                  :forceMenu="false"
                  @close="handleClose()"
                  @opened="handleOpened()"
    >
      <template #description>
        <div class="global-actions-container flex-container flex-center">
          <NcActions v-tooltip.left="hints['projectevents:all:new']"
                     :type="actionButtonType"
                     class="new-event-menu fc-event"
                     dataIsNew="yes"
                     :ariaLabel="t(appName, 'create a new appointment')"
                     :disabled="isLoading"
          >
            <template #icon>
              <IconNew />
            </template>
            <NcActionRouter v-for="(item, uri) in routerEventAdd"
                            :key="uri"
                            v-tooltip.left="hints['projectevents:all:new:' + uri]"
                            :to="item.location"
                            :closeAfterClick="true"
            >
              <template #icon>
                <component :is="calendarIcons[uri]" />
              </template>
              {{ item.label }}
            </NcActionRouter>
          </NcActions>
          <NcButton v-tooltip="hints['projectevents:all:select']"
                    :dataTooltip="hints['projectevents:all:select']"
                    :ariaLabel="t(appName, 'mark all events')"
                    :disabled="isLoading"
                    @click="markAllEvents(true)"
          >
            <template #icon>
              <DynamicSvgIcon :size="24" :data="svgEmailChecked" class="material-design-icon" />
            </template>
          </NcButton>
          <NcButton v-tooltip="hints['projectevents:all:deselect']"
                    :aria-label="t(appName, 'unmark all events')"
                    :disabled="isLoading"
                    @click="markAllEvents(false)"
          >
            <template #icon>
              <DynamicSvgIcon :size="24" :data="svgEmailCross" class="material-design-icon" />
            </template>
          </NcButton>
          <NcButton v-tooltip="hints['projectevents:all:sendmail']"
                    :ariaLabel="t(appName, 'open email editor')"
                    :disabled="isLoading"
                    @click="emailEditor"
          >
            <template #icon>
              <DynamicSvgIcon :size="24" :data="svgEmailUnchecked" class="material-design-icon" />
            </template>
            {{ t(appName, 'Em@il') }}
          </NcButton>
          <NcButton v-tooltip="hints['projectevents:all:download']"
                    :ariaLabel="t(appName, 'export events')"
                    :disabled="isLoading"
                    @click="exportEvents"
          >
            <template #icon>
              <IconExport />
            </template>
          </NcButton>
          <NcActions v-tooltip="hints['projectevents:manual']"
                     :type="actionButtonType"
          >
            <template #icon>
              <IconInfo />
            </template>
            <NcActionLink :href="wikiManualUrl"
                          :target="wikiManualUrlTarget"
                          :closeAfterClick="true"
            >
              <template #icon>
                <IconManualOtherWindow />
              </template>
              {{ t(appName, 'Manual (other tab or window)') }}
            </NcActionLink>
            <NcActionButton :closeAfterClick="true"
                            @click="onUserManualPopup"
            >
              <template #icon>
                <IconManualPopup />
              </template>
              {{ t(appName, 'Manual (popup)') }}
            </NcActionButton>
          </NcActions>
          <NcButton v-tooltip="hints['projectevents:all:reload']"
                    :class="{ loading: showLoadingIndicator }"
                    :disabled="!project || isLoading"
                    :ariaLabel="t(appName, 'reload the project appointments')"
                    @click="handleReload"
          >
            <template #icon>
              <IconReload />
            </template>
          </NcButton>
        </div>
      </template>
      <template #default>
        <ul v-for="matrixEntry in eventMatrix" :key="matrixEntry.uri">
          <NcListItem class="calendar-header"
                      :class="{ 'no-events': matrixEntry.events.length === 0 }"
                      active
                      :name="matrixEntry.name + (matrixEntry.events.length > 0 ? '' : ': ' + t(appName, 'no events'))"
                      oneLine
                      bold
                      :counterNumber="matrixEntry.events.length"
                      counterType="highlighted"
                      forceDisplayActions
                      @click.prevent="toggleCalendarVisibility(matrixEntry)"
          >
            <template #icon>
              <component :is="calendarIcons[matrixEntry.uri]" />
            </template>
            <template v-if="matrixEntry.events.length > 0"
                      #extra-actions
            >
              <NcButton variant="primary"
                        :ariaLabel="t(appName, 'Toggle Details')"
                        @click="toggleCalendarVisibility(matrixEntry)"
              >
                <template #icon>
                  <IconHideDetails v-if="showCalendarEvent(matrixEntry)" />
                  <IconShowDetails v-else />
                </template>
              </NcButton>
            </template>
          </NcListItem>
          <NcListItem v-if="matrixEntry.events.length > 20 && Object.keys(eventsByYear[matrixEntry.uri] ?? {}).length > 1"
                      v-show="showCalendarEvent(matrixEntry)"
          >
            <template #details>
              <NcSelect v-model="matrixEntryYear[matrixEntry.uri]"
                        :options="Object.keys(eventsByYear[matrixEntry.uri] ?? {})"
                        :placeholder="t(appName, 'select a year')"
              />
            </template>
          </NcListItem>
          <NcListItem v-for="event in matrixEntry.events"
                      v-show="showCalendarEvent(matrixEntry, event)"
                      :key="event.instanceId"
                      class="project-event fc-event"
                      :class="{ detached: event.deleted }"
                      :to="routerEventEdit[event.instanceId] || ''"
                      :dataObjectId="routerEventEdit[event.instanceId]?.params.object || ''"
                      :dataRecurrenceId="routerEventEdit[event.instanceId]?.params.recurrenceId || ''"
                      :exactPath="true"
                      :forceDisplayActions="true"
                      :oneLine="false"
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
                <IconRecordAbsence v-if="hasAbsenceField[event.instanceId]"
                                   v-tooltip="hints['projectevents:event:absence-field:indicator']"
                />
              </div>
            </template>
            <template #indicator>
              <div class="flex-container flex-baseline">
                <span v-tooltip="hints['projectevents:event:event-uid']"
                      :class="'event-uid-' + eventSeries[event.uid]"
                ><span>{{ eventSeriesIndicator(event) }}</span></span>
                <span v-tooltip="hints['projectevents:event:event-series-uid']"
                      :class="'event-series-uid-' + eventRelations[event.seriesUid ?? '']"
                ><span>{{ eventRelationsIndicator(event) }}</span></span>
              </div>
            </template>
            <template #actions>
              <NcActionRadio v-if="eventRelations[event.seriesUid]"
                             v-model:modelValue="actionScope[event.uid]"
                             v-tooltip="hints['projectevents:event:scope:single']"
                             value="single"
                             :name="'action-scope-' + event.uid"
                             :closeAfterClick="true"
                             @change="updateActionScope(event, 'single')"
              >
                {{ t(appName, 'act only on this event') }}
              </NcActionRadio>
              <NcActionRadio v-if="eventSeries[event.uid]"
                             v-model:modelValue="actionScope[event.uid]"
                             v-tooltip="hints['projectevents:event:scope:series']"
                             value="series"
                             :name="'action-scope-' + event.uid"
                             :closeAfterClick="true"
                             @change="updateActionScope(event, 'series')"
              >
                {{ t(appName, 'act on the event series') }}
              </NcActionRadio>
              <NcActionRadio v-if="eventRelations[event.seriesUid]"
                             v-model:modelValue="actionScope[event.uid]"
                             v-tooltip="hints['projectevents:event:scope:related']"
                             value="related"
                             :name="'action-scope-' + event.uid"
                             :closeAfterClick="true"
                             @change="updateActionScope(event, 'related')"
              >
                {{ t(appName, 'act on all related events') }}
              </NcActionRadio>
              <NcActionSeparator v-if="eventRelations[event.seriesUid]" />
              <NcActionButton v-tooltip="hints['projectevents:event:absence-field:check']"
                              type="checkbox"
                              :closeAfterClick="true"
                              :disabled="!mutationsAllowed || !!event.deleted || !CALENDARS[matrixEntry.uri]?.public"
                              @click="toggleAbsenceField(event)"
              >
                <template #icon>
                  <IconRecordAbsence v-if="hasAbsenceField[event.instanceId]" class="icon-record-absence" />
                  <IconDoNotRecordAbsence v-else class="icon-do-not-record-absence" />
                </template>
                {{ t(appName, 'record absence') }}
              </NcActionButton>
              <NcActionButton v-tooltip="hints['projectevents:event:select']"
                              :disabled="!!event.deleted"
                              type="checkbox"
                              :closeAfterClick="true"
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
              <NcActionRouter v-tooltip="hints['projectevents:event:edit']"
                              :to="routerEventEdit[event.instanceId] || ''"
                              :exactPath="true"
                              :closeAfterClick="true"
              >
                <template #icon>
                  <IconEventEdit />
                </template>
                {{ t(appName, 'edit') }}
              </NcActionRouter>
              <NcActionLink v-if="actionScope[event.uid] !== 'series'"
                            v-tooltip="hints['projectevents:event:calendar-app:single']"
                            :href="calendarAppEventEdit[event.instanceId] || ''"
                            :target="calendarAppTarget"
                            :closeAfterClick="true"
              >
                <template #icon>
                  <IconCalendar />
                </template>
                {{ t(appName, 'open in calendar-app') }}
              </NcActionLink>
              <NcActionLink v-else
                            v-tooltip="hints['projectevents:event:calendar-app:series']"
                            :href="calendarAppEventEditSeries[event.uid] || ''"
                            :target="calendarAppTarget"
                            :closeAfterClick="true"
              >
                <template #icon>
                  <IconCalendar />
                </template>
                {{ t(appName, 'open in calendar-app') }}
              </NcActionLink>
              <NcActionButton v-tooltip="hints['projectevents:event:delete']"
                              :closeAfterClick="true"
                              :disabled="!mutationsAllowed"
                              @click="handleDeleteEvent(matrixEntry, event)"
              >
                <template #icon>
                  <IconEventDelete />
                </template>
                {{ t(appName, 'delete') }}
              </NcActionButton>
              <NcActionButton v-if="!event.deleted"
                              v-tooltip="hints['projectevents:event:detach']"
                              :closeAfterClick="true"
                              :disabled="!mutationsAllowed"
                              @click="handleProjectLink(event, false)"
              >
                <template #icon>
                  <IconEventDetach />
                </template>
                {{ t(appName, 'detach from project') }}
              </NcActionButton>
              <NcActionButton v-else
                              v-tooltip="hints['projectevents:event:reattach']"
                              :closeAfterClick="true"
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
    <div class="app-calendar simple-editor-anchor">
      <router-view />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { CalendarObjectInstanceStore, CalendarObjectsStore } from '@nextcloud/app-calendar'
import type { EventArgs } from '@rotdrop/async-nextcloud-event-bus'
import type {
  Component,
  WatchStopHandle,
} from 'vue'
import type {
  RouteLocationRaw,
  RouteRecord,
} from 'vue-router'
import type { EventMatrixRow } from '../../build/ts-types/php-modules/Service/DTO.ts'
import type {
  CalendarObjectAddLocation,
  CalendarObjectEditLocation,
} from '../router/calendar-routes.ts'
import type {
  CalendarUris,
  EventMatrixEvent,
  Project,
  ProjectEventMatrix,
} from '../stores/app-data.ts'

import useCalendarObjectInstance from '@nextcloud/app-calendar/src/store/calendarObjectInstance.js'
import useCalendarObjects from '@nextcloud/app-calendar/src/store/calendarObjects.js'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import {
  NcActionButton,
  NcActionLink,
  NcActionRadio,
  NcActionRouter,
  NcActions,
  NcActionSeparator,
  NcAppSidebar,
  NcButton,
  NcListItem,
  NcSelect,
} from '@nextcloud/vue'
import md5 from 'blueimp-md5'
import capitalize from 'capitalize'
import { DateTime } from 'luxon'
import { storeToRefs } from 'pinia'
import {
  computed,
  nextTick,
  onBeforeMount,
  onUnmounted,
  reactive,
  ref,
  watch,
} from 'vue'
import {
  onBeforeRouteUpdate,
  useRoute,
  useRouter,
} from 'vue-router'
import DynamicSvgIcon from '@rotdrop/nextcloud-vue-components/lib/components/DynamicSvgIcon.vue'
import IconManagement from 'vue-material-design-icons/AccountGroup.vue'
import IconRecordAbsence from 'vue-material-design-icons/AccountMultipleCheckOutline.vue'
import IconDoNotRecordAbsence from 'vue-material-design-icons/AccountMultipleOutline.vue'
import IconRehearsals from 'vue-material-design-icons/AccountMusicOutline.vue'
import IconFinance from 'vue-material-design-icons/BankTransfer.vue'
import IconCalendar from 'vue-material-design-icons/Calendar.vue'
import IconEventEdit from 'vue-material-design-icons/CalendarEdit.vue'
import IconExport from 'vue-material-design-icons/CalendarExport.vue'
import IconEventDelete from 'vue-material-design-icons/CalendarRemove.vue'
import IconOther from 'vue-material-design-icons/CogOutline.vue'
import IconInfo from 'vue-material-design-icons/InformationVariant.vue'
import IconEventAttach from 'vue-material-design-icons/LinkVariant.vue'
import IconEventDetach from 'vue-material-design-icons/LinkVariantOff.vue'
import IconManualPopup from 'vue-material-design-icons/MessageTextOutline.vue'
import IconManualOtherWindow from 'vue-material-design-icons/OpenInNew.vue'
import IconNew from 'vue-material-design-icons/Plus.vue'
import IconReload from 'vue-material-design-icons/Reload.vue'
import IconConcerts from 'vue-material-design-icons/TimerMusicOutline.vue'
import IconHideDetails from 'vue-material-design-icons/UnfoldLessHorizontal.vue'
import IconShowDetails from 'vue-material-design-icons/UnfoldMoreHorizontal.vue'
import { NIL as UUID_NIL } from '../../build/ts-types/php-modules/Common/Uuid.ts'
import { RECORD_ABSENCE_CATEGORY } from '../../build/ts-types/php-modules/Service/EventsService.ts'
import { CALENDARS } from '../../build/ts-types/php-modules/Settings/ConfigConstants.ts'
import svgEmailUnchecked from '../../img/email-new-path.svg?raw'
import svgEmailCross from '../../img/email-new-x-path.svg?raw'
import svgEmailChecked from '../../img/email-new-yes-path.svg?raw'
import { appName } from '../config.ts'
import {
  EMAIL_POPUP,
  LEGACY_UPDATE_EVENTS_SELECTION,
  WIKI_POPUP,
} from '../event-bus-events.ts'
// _at_ts-expect-error: 7016
import {
  CALENDAR_APP_ROUTES,
  PROJECT_EVENTS_LISTING_NAME,
} from '../router/calendar-routes.ts'
import appTranslate from '../services/app-l10n.ts'
import {
  emit as asyncEmit,
  subscribe as asyncSubscribe,
  unsubscribe as asyncUnSubscribe,
} from '../services/async-event-bus.ts'
import calendarStoreSetup from '../services/calendar-store-setup.ts'
import useAppDataStore from '../stores/app-data.ts'
import useErrorHandlerStore from '../stores/error-handler.ts'
import useTooltipsStore from '../stores/tooltips.ts'
import { AppError } from '../toolkit/types/errors.ts'
import axiosFileDownload from '../toolkit/util/axios-file-download.ts'
import Console from '../util/console.ts'
import {
  dokuWikiSection,
  dokuWikiUrl,
  dokuWikiUrlTarget,
} from '../util/doku-wiki.ts'

const props = withDefaults(defineProps<{
  projectName: string
}>(), {})

const COMPONENT_NAME = PROJECT_EVENTS_LISTING_NAME

const logger = new Console(COMPONENT_NAME)

const recordAbsenceCategory = appTranslate(RECORD_ABSENCE_CATEGORY)

const errorHandlerProvider = useErrorHandlerStore()

const errorHandler = errorHandlerProvider.getHandler()

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
      params: { ...currentRoute.params },
      query: { ...currentRoute.query },
    },
    transition: currentRoute.transition,
  }
  : {
    location: {
      name: 'home',
    },
    transition: 'unknown',
  }

logger.info('COMPUTED ORIGIN', { origin, prev })

const project = ref<null | Project>(null)
const projectEventMatrix = computed<undefined | ProjectEventMatrix>(() => project.value?.eventMatrix)

const calendarOrdering: { [Key in EventMatrixRow['uri']]: number } = {
  concerts: 0,
  rehearsals: 10,
  management: 20,
  finance: 20,
  other: 40,
  '': 99,
}
const emptyEventMatrix: EventMatrixRow[] = []
for (const uri of Object.keys(calendarOrdering) as ((CalendarUris|'')[])) {
  emptyEventMatrix.push({ name: t(appName, uri), uri, calendarId: -1, urlPath: '', events: [] })
}
emptyEventMatrix.sort((a, b) => calendarOrdering[a.uri] - calendarOrdering[b.uri])

const eventMatrix = computed<EventMatrixRow[]>(
  () => !projectEventMatrix.value
    ? emptyEventMatrix
    : Object.values(projectEventMatrix.value).sort((a, b) => calendarOrdering[a.uri] - calendarOrdering[b.uri]),
)

// const popOverActive = computed(() => currentRoute.path.includes('/popover/'))
const calendarAppFullActive = computed(() => currentRoute.path.includes('/full/'))

const actionButtonType = ref('secondary')

const tooltipKeys = [
  'projectevents:event',
  'projectevents:event:scope',
  'projectevents:event:scope:single',
  'projectevents:event:scope:series',
  'projectevents:event:scope:related',
  'projectevents:event:select',
  'projectevents:event:absence-field:check',
  'projectevents:event:edit',
  'projectevents:event:calendar-app:single',
  'projectevents:event:calendar-app:series',
  'projectevents:event:delete',
  'projectevents:event:detach',
  'projectevents:event:reattach',
  'projectevents:event:event-uid',
  'projectevents:event:event-series-uid',
  'projectevents:event:absence-field:indicator',
  'projectevents:manual',
  'projectevents:all:select',
  'projectevents:all:deselect',
  'projectevents:all:sendmail',
  'projectevents:all:download',
  'projectevents:all:reload',
  'projectevents:all:new',
]

for (const uri of Object.keys(calendarOrdering)) {
  if (uri) {
    tooltipKeys.push('projectevents:all:new:' + uri)
  }
}

const tooltipsProvider = useTooltipsStore()
tooltipsProvider.provideTooltips(tooltipKeys)
const hints = tooltipsProvider.tooltipsData

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
    popupTitle: t(appName, 'User Manual: {section}', { section: t(appName, 'Project Events') }, { escape: false }),
  })
  isWikiLoading.value = false
}

const calendarIcons: { [Key in CalendarUris|'']?: Component } = {
  management: IconManagement,
  finance: IconFinance,
  concerts: IconConcerts,
  rehearsals: IconRehearsals,
  other: IconOther,
  '': IconCalendar,
}

type ActionScope = 'single'|'series'|'related'

const currentYear = new Date().getFullYear()
const matrixEntryYear = reactive<{ [Key in CalendarUris | '']?: number }>({})
for (const uri of Object.keys(calendarOrdering) as ((CalendarUris | '')[])) {
  matrixEntryYear[uri] = currentYear
}
const calendarUriByEventObject: Record<string, EventMatrixRow['uri']|undefined> = {}
const instanceIdByEventObject: Record<string, string> = {}
const eventsByYear = reactive<{ [Key in EventMatrixRow['uri']]?: Record<string, EventMatrixEvent[]> }>({})
const yearsByEvent = reactive<Record<string, number>>({})
const expandedState = ref<{ [Key in CalendarUris]?: boolean }>({})
const hasAbsenceField = ref<Record<string, boolean>>({})
const attachmentMark = ref<Record<string, boolean>>({})
const routerEventEdit = ref<Record<string, CalendarObjectEditLocation>>({})
const routerEventAdd = ref<Record<string, { location: CalendarObjectAddLocation, label: string }>>({})
const calendarAppEventEdit = ref<Record<string, string>>({})
const calendarAppEventEditSeries = ref<Record<string, string>>({})
const calendarAppTarget = computed(() => md5(appName + ': event edit in calendar app sidebar'))
const actionScope = ref<Record<string, ActionScope>>({})

const eventSeries = ref<Record<string, number>>({})
const eventRelations = ref<Record<string, number>>({})

const relatedEvents: Record<string, EventMatrixEvent[]> = {}
const seriesEvents: Record<string, EventMatrixEvent[]> = {}

// Block other async reload request until one has finished
const eventListLock = Promise.withResolvers<void>()
eventListLock.resolve()

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

// Make sure that the event of the route is open in the events listing.
router.afterEach((to, _from) => {
  // check if the current route contains a calendar app component
  if (!CALENDAR_APP_ROUTES.includes(to.name! as string)) {
    return
  }
  const calendarUri = calendarUriByEventObject[to.params.object as string]
  const instanceId = instanceIdByEventObject[to.params.object as string]
  if (calendarUri) {
    expandedState.value[calendarUri] = true
    if (instanceId && yearsByEvent[instanceId]) {
      matrixEntryYear[calendarUri] = yearsByEvent[instanceId]
    }
  }
})

const handleOpened = () => {
  logger.debug('OPENED EVENT', { currentRoute })
  // check if the current route contains a calendar app component
  if (CALENDAR_APP_ROUTES.includes(currentRoute.name as string)) {
    for (const instance of Object.values(currentRoute.matched[currentRoute.matched.length - 1].instances)) {
      // @ts-expect-error: 2339 Too lazy to generate the types.
      if (typeof instance?.repositionPopover === 'function') {
        nextTick().then(() => {
          // @ts-expect-error: 2339 Too lazy to generate the types.
          instance.repositionPopover(true)
        })
      }
    }
  }
}

const handleClose = () => {
  if (origin?.transition === 'push' && origin?.transition !== 'push') {
    logger.info('TRY GO TO PREVIOUS ON CLOSE', { origin })
    router.go(-1) // maybe we want to avoid this altogether ...
  } else if (origin) {
    logger.info('TRY PUSH TO ORIGIN', { origin })
    router.push(origin.location)
  }
}

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

const syncProjectData = async (projectName: string) => {
  await aquireEventListLock()
  isLoading.value = true
  project.value = await appData.getProject(projectName) || null
  if (project.value) {
    await Promise.allSettled([
      project.value.getEventMatrix(),
    ])
    if (projectEventMatrix.value) {
      let seriesCounter = 1
      let relationsCounter = 1
      const calendarNames: { [Key in CalendarUris | '']?: string } = {}
      for (const entry of Object.values(projectEventMatrix.value)) {
        expandedState.value[entry.uri] = !!expandedState.value?.[entry.uri]
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
            context.categories.push(recordAbsenceCategory)
          }
          const nowSeconds = Math.round(Date.now() / 1000 / 3600) * 3600
          const params = {
            ...currentRoute.params,
            allDay: '1',
            dtstart: '' + nowSeconds,
            dtend: '' + nowSeconds,
            context: btoa(JSON.stringify(context)),
          }
          const query = currentRoute.query as Record<string, string>
          routerEventAdd.value[entry.uri] = { location: { name, params, query }, label }
        }
        for (const key in Object.keys(eventRelations.value)) {
          delete eventRelations.value[key]
        }
        for (const key in Object.keys(eventSeries.value)) {
          delete eventSeries.value[key]
        }
        eventsByYear[entry.uri] = {}
        for (const event of entry.events) {
          const eventStartDate = DateTime.fromISO(event.start).toISODate()!
          const eventYear = eventStartDate.substring(0, 4)
          if (eventsByYear[entry.uri]![eventYear]) {
            eventsByYear[entry.uri]![eventYear].push(event)
          } else {
            eventsByYear[entry.uri]![eventYear] = [event]
          }
          yearsByEvent[event.instanceId] = +eventYear
          hasAbsenceField.value[event.instanceId] = !!event.absenceField
          attachmentMark.value[event.instanceId] = !!attachmentMark.value?.[event.instanceId]
          actionScope.value[event.uid] = actionScope.value?.[event.uid] || 'single'

          const name = 'EditPopoverView'
          const context = {}
          const eventObject = btoa(event.urlPath)
          calendarUriByEventObject[eventObject] = entry.uri
          instanceIdByEventObject[eventObject] = event.instanceId
          const params = {
            ...currentRoute.params,
            object: eventObject,
            // recurrenceId: event.times.start.stamp, // event.recurrenceId is different
            recurrenceId: '' + (+event.recurrenceId > 0 ? event.recurrenceId : event.times.start.stamp),
            context: btoa(JSON.stringify(context)),
          }
          const query = currentRoute.query as Record<string, string>
          routerEventEdit.value[event.instanceId] = { name, params, query }

          const calendarAppUrlParams = {
            view: 'timeGridWeek',
            timeRange: eventStartDate,
            mode: 'full',
            objectId: params.object,
            recurrenceId: params.recurrenceId,
          }
          calendarAppEventEdit.value[event.instanceId] = generateUrl('/apps/calendar/{view}/{timeRange}/edit/{mode}/{objectId}/{recurrenceId}', calendarAppUrlParams)

          if (event.seriesUid !== UUID_NIL) {
            if (eventRelations.value[event.seriesUid] === undefined) {
              eventRelations.value[event.seriesUid] = relationsCounter++
              relatedEvents[event.seriesUid] = []
            }
            relatedEvents[event.seriesUid].push(event)
          }
          if (+event.recurrenceId > 0) {
            if (eventSeries.value[event.uid] === undefined) {
              eventSeries.value[event.uid] = seriesCounter++
              seriesEvents[event.uid] = []
              calendarAppUrlParams.recurrenceId = '' + DateTime.fromISO(event.seriesStart, { setZone: true }).toUnixInteger()
              calendarAppEventEditSeries.value[event.uid] = generateUrl('/apps/calendar/{view}/{timeRange}/edit/{mode}/{objectId}/{recurrenceId}', calendarAppUrlParams)
            }
            seriesEvents[event.uid].push(event)
          }
          // check if the current route contains a calendar app component
          if (CALENDAR_APP_ROUTES.includes(currentRoute.name as string)) {
            if (eventObject === currentRoute.params.object) {
              expandedState.value[entry.uri] = true
              for (const instance of Object.values(currentRoute.matched[currentRoute.matched.length - 1].instances)) {
                // @ts-expect-error: 2339 Just too lazy to define the types.
                if (typeof instance.repositionPopover === 'function') {
                  nextTick().then(() => {
                    // @ts-expect-error: 2339 Just too lazy to define the types.
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

let calendarObjectsStore: CalendarObjectsStore
let calendarObjectInstanceStore: CalendarObjectInstanceStore
const mutationsAllowed = ref(false)
let stopModificationCountWatch: WatchStopHandle

const syncEventListTrigger = ref(false)

// some mutations do not require a reload, so pause the mutation
// observer for those
let ignoreCalendarObjectMutations = false

const handleReload = async () => {
  syncProjectData(project.value?.name || '')
  if (currentRoute.name === 'EditPopoverView' || currentRoute.name === 'EditFullView') {
    // careful: just shooting down the stores will trigger a watcher
    // which then will access no longer defined objects.
    const {
      calendarObject,
    } = await calendarObjectInstanceStore.getCalendarObjectInstanceByObjectIdAndRecurrenceId({
      objectId: currentRoute.params.object as string,
      recurrenceId: +currentRoute.params.recurrenceId,
      reload: true,
    })
    for (const [id, objectInstance] of Object.entries(calendarObjectsStore.calendarObjects)) {
      if (objectInstance !== calendarObject) {
        delete calendarObjectsStore.calendarObjects[id]
      }
    }
  } else {
    // just shoot down the stores.
    calendarObjectsStore.$reset()
    calendarObjectInstanceStore.$reset()
  }
}

const toggleCalendarVisibility = (entry: EventMatrixRow) => {
  expandedState.value[entry.uri] = !expandedState.value[entry.uri]
}
const showCalendarEvent = (matrixEntry: EventMatrixRow, event?: EventMatrixEvent) => {
  const show = !!expandedState.value[matrixEntry.uri]
  if (!event) {
    return show
  }
  if (matrixEntry.events.length > 20 && Object.keys(eventsByYear[matrixEntry.uri]!).length > 1) {
    return show && (+matrixEntryYear[matrixEntry.uri]! === +yearsByEvent[event.instanceId])
  } else {
    return show
  }
}

const briefEventDate = (event: EventMatrixEvent) => {
  const times = event.times
  if (times.start.date === times.end.date) {
    if (times.allDay) {
      return times.start.date
    }
    if (times.start.time === '00:00') {
      return t(appName, '{startDate}, until {endTime}', { startDate: times.start.date, endTime: times.end.time })
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
      if (event.seriesUid === UUID_NIL) {
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
  calendarId: number
  uri: string
  recurrenceId?: number
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
  for (const entry of Object.values(projectEventMatrix.value)) {
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
      selectedEvents: Object.values(legacyEventSelection),
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
    selectedEvents: Object.values(legacyEventSelection),
  }
  try {
    await axiosFileDownload('projects/events/download', post)
  } catch (error) {
    errorHandler(
      new AppError(
        { component: COMPONENT_NAME },
        t(appName, 'Unable to export selected events.'),
        { cause: error },
      ),
    )
  }
}

calendarStoreSetup().then((_arg) => {
  calendarObjectInstanceStore = useCalendarObjectInstance()
  calendarObjectsStore = useCalendarObjects()
  logger.debug('STORES', {
    calendarObjectInstanceStore,
    calendarObjectsStore,
  })
  mutationsAllowed.value = true
  const { modificationCount } = storeToRefs(calendarObjectsStore)
  logger.debug('MOD COUNT REF', { modificationCount })
  stopModificationCountWatch = watch(
    modificationCount,
    (value) => {
      logger.debug('OBSERVED MODIFICATION COUNT CHANGE', value)
      if (!value || !props.projectName) {
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
    recurrenceId: +routerEventEdit.value[event.instanceId].params.recurrenceId,
  })
}

const mutateCategory = async (event: EventMatrixEvent, category: string, enable: boolean) => {
  const { calendarObjectInstance, calendarObject } = await setCalendarObjectInstance(event)
  if (enable) {
    calendarObjectInstanceStore.addCategory({ calendarObjectInstance, category })
    logger.debug('ADD CATETGORY', {
      event,
      category,
      calendarObjectInstance,
      calendarObject,
    })
  } else {
    calendarObjectInstanceStore.removeCategory({ calendarObjectInstance, category })
    logger.debug('REMOVE CATETGORY', {
      event,
      category,
      calendarObjectInstance,
      calendarObject,
    })
  }
  calendarObjectsStore.updateCalendarObject({ calendarObject })
  calendarObjectInstance.eventComponent.markDirty()
  await calendarObjectInstanceStore.saveCalendarObjectInstance({
    thisAndAllFuture: false,
    calendarId: calendarObject.calendarId,
  })
}

const mutateAbsenceField = async (event: EventMatrixEvent, enable: boolean) => {
  await mutateCategory(event, recordAbsenceCategory, enable)
  if (enable) {
    event.absenceField = 9999
    hasAbsenceField.value[event.instanceId] = true
  } else {
    event.absenceField = 0
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
  } catch {
    errorHandler(new AppError({ component: COMPONENT_NAME }, t(appName, 'Unable to modify the absence field.')))
  }
  ignoreCalendarObjectMutations = false
  releaseEventListLock()
}

const singleEventProjectLink = async (event: EventMatrixEvent, linkToProject: boolean) => {
  await mutateCategory(event, project.value!.name, linkToProject)
  if (linkToProject) {
    event.deleted = undefined
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

const deleteSingleEvent = async (matrixEntry: EventMatrixRow, event: EventMatrixEvent) => {
  // const { calendarObjectInstance, calendarObject } = await setCalendarObjectInstance(event)
  await setCalendarObjectInstance(event)
  // logger.debug('DELETE EVENT', {
  //   calendarObjectInstance,
  //   calendarObject,
  // })
  await calendarObjectInstanceStore.deleteCalendarObjectInstance({ thisAndAllFuture: false })
  // logger.debug('AFTER DELETE')

  // if one of the event editors is open we should close it ...
  if ((currentRoute.name === 'EditPopoverView' || currentRoute.name === 'EditFullView')
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
  if (event.seriesUid !== UUID_NIL) {
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

const handleDeleteEvent = async (matrixEntry: EventMatrixRow, event: EventMatrixEvent) => {
  if (!calendarObjectInstanceStore || !projectEventMatrix.value) {
    return
  }
  await aquireEventListLock()
  ignoreCalendarObjectMutations = true
  try {
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
        t(appName, 'Unable to delete events.'),
        { cause: error },
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
  event.seriesUid !== UUID_NIL
    ? String.fromCharCode('α'.charCodeAt(0) + eventRelations.value[event.seriesUid] - 1)
    : ''

syncProjectData(props.projectName).then(() => logger.debug('Project-data has been synced'))
watch(() => props.projectName, async (newValue, _oldValue) => {
  await syncProjectData(newValue)
})

watch(syncEventListTrigger, async (value) => {
  if (!value) {
    return
  }
  handleReload()
  // if the start date has changed then we probably have to chance the route
  if (currentRoute.name === 'EditPopoverView' || currentRoute.name === 'EditFullView') {
    const instance = calendarObjectInstanceStore.calendarObjectInstance!
    const startDate = instance.startDate
    if (instance.recurrenceRule.frequency === 'NONE' || instance.isAllDay) {
      let recurrenceId: string|number = Math.round(calendarObjectInstanceStore.calendarObjectInstance!.startDate.getTime() / 1000)
      if (instance.isAllDay) {
        // we need the GMT time of the date
        recurrenceId -= startDate.getTimezoneOffset() * 60
      }
      recurrenceId = '' + recurrenceId
      if (currentRoute.params.recurrenceId !== recurrenceId) {
        logger.info('TRY ROUTER REPLACE', { recurrenceId, params: { ...currentRoute.params } })
        const location: CalendarObjectEditLocation = {
          name: currentRoute.name!,
          params: {
            ...currentRoute.params as { object: string, context: string },
            recurrenceId,
          },
          query: currentRoute.query as Record<string, string>,
        }
        await router.replace(location as RouteLocationRaw)
      }
    }
  }
  // await syncProjectData(props.projectName)
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
@use '../../style/mixins/flex.scss';
@use '../../style/color-palette.scss' as *;

@include flex.flexRules;
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

.simple-editor-anchor {
  position: relative;
}
</style>
