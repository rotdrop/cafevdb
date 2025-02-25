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
 -
 -->
<template>
  <NcModal :close-on-click-outside="false"
           :has-next="false"
           :has-previous="false"
           :label-id="modalPageHeadingId"
           class="browser-history-modal"
           size="large"
           v-bind="$attrs"
           v-on="$listeners"
           @click="dataPopupShown = undefined"
  >
    <template #default>
      <NcActions :class="['browser-history-actions', { loading }]">
        <NcActionButton @click="reloadHistoryStates">
          <template #icon>
            <IconReload :size="20" />
            {{ t(appName, 'Reload History States') }}
          </template>
        </NcActionButton>
      </NcActions>
      <h2 :id="modalPageHeadingId" class="modal-page-heading">
        {{ heading }}
      </h2>
      <ul v-for="(state, mtime) in historyData" :key="mtime">
        <NcListItem active
                    :name="stateDisplayName(state, +mtime)"
                    :bold="true"
                    :counter-number="Object.keys(state.history).length"
                    :href="generateAppUrl(state.history[state.position].path.replace(/^\/+/, ''))"
                    counter-type="highlighted"
                    :force-display-actions="true"
                    @click.prevent="pushRoute(+mtime, state.position)"
        >
          <template #icon>
            <IconHistoryState />
          </template>
          <template #subname>
            <NcEllipsisedOption v-tooltip="t(appName, 'Active page when the history was saved.')"
                                :name="pathDisplayName(state.history[state.position])"
            />
          </template>
          <template #extra-actions>
            <NcButton type="primary"
                      :aria-label="t(appName, 'Toggle Details')"
                      @click="dataPopupShown = undefined; expandedState = expandedState === +mtime ? undefined : +mtime"
            >
              <template #icon>
                <IconShowDetails v-if="expandedState !== +mtime" />
                <IconHideDetails v-else />
              </template>
            </NcButton>
          </template>
          <template #actions>
            <NcActionButton v-tooltip="t(appName, `Push the listed items after the current view
and navigate to the last active view of the saved history.`)"
                            @click="pushHistoryChain(+mtime)"
            >
              <template #icon>
                <IconLoad />
              </template>
              {{ t(appName, 'Push History') }}
            </NcActionButton>
            <NcActionButton v-tooltip="t(appName, `Replace the current browser history by the listed items
and navigate to the last active view of the saved history.`)"
                            disabled
            >
              <template #icon>
                <IconLoad />
              </template>
              {{ t(appName, 'Replace History') }}
            </NcActionButton>
            <NcActionButton v-tooltip="t(appName, `Append the listed items at the end of the current browser history
and navigate to the last active view of the saved history.`)"
                            disabled
            >
              <template #icon>
                <IconLoad />
              </template>
              {{ t(appName, 'Append to Current') }}
            </NcActionButton>
            <NcActionButton @click="dataPopupShown = undefined; deleteHistoryState(+mtime)">
              <template #icon>
                <IconDelete />
              </template>
              {{ t(appName, 'Delete') }}
            </NcActionButton>
          </template>
        </NcListItem>
        <!-- .stop in order to prevent floating-vue to close the tooltip -->
        <NcListItem v-for="(entry, key) in state.history"
                    v-show="expandedState === +mtime"
                    :key="key"
                    v-tooltip="{
                      content: () => makePostDataTooltip(+mtime, key),
                      html: true,
                      shown: isDataPopupShown(+mtime, key),
                      triggers: [],
                    }"
                    :bold="key === state.position"
                    :href="generateAppUrl(state.history[state.position].path.replace(/^\/+/, ''))"
                    :force-display-actions="true"
                    @click.stop.prevent="pushRoute(+mtime, key)"
        >
          <template #icon>
            <IconLinkPosition v-if="key === state.position" />
            <IconLink v-else />
          </template>
          <template #name>
            <span v-tooltip="key === state.position ? t(appName, 'Active page when the history was saved.') : undefined"
                  :class="{ 'current-position': key === state.position, 'history-entry-name': true }"
            >{{ '' + key }}</span>
          </template>
          <template #subname>
            <NcEllipsisedOption :name="pathDisplayName(entry)" />
          </template>
          <template #actions>
            <!-- .stop in order to prevent floating-vue to close the tooltip -->
            <NcActionButton v-tooltip="t(appName, 'Show the raw data submitted to the server (expert use).')"
                            @click.stop.prevent="toggleDataPopupShown(+mtime, key)"
            >
              <template #icon>
                <IconViewData v-if="!isDataPopupShown(+mtime, key)" />
                <IconHideData v-else />
              </template>
              {{ t(appName, 'Data-Record') }}
            </NcActionButton>
          </template>
        </NcListItem>
      </ul>
    </template>
  </NcModal>
</template>
<script setup lang="ts">
import { appName } from '../config.ts'
import useHistoryStore from '../stores/history.ts'
import type {
  FetchMode,
  HistoryPersistenceRecord,
  RouterHistoryState,
} from '../stores/history.ts'
import {
  NcActionButton,
  NcActions,
  NcButton,
  NcEllipsisedOption,
  NcListItem,
  NcModal,
} from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import IconHistoryState from 'vue-material-design-icons/History.vue'
import IconShowDetails from 'vue-material-design-icons/UnfoldMoreHorizontal.vue'
import IconHideDetails from 'vue-material-design-icons/UnfoldLessHorizontal.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconLoad from 'vue-material-design-icons/Upload.vue'
import IconLink from 'vue-material-design-icons/Link.vue'
import IconLinkPosition from 'vue-material-design-icons/LinkVariant.vue'
import IconViewData from 'vue-material-design-icons/Eye.vue'
import IconHideData from 'vue-material-design-icons/EyeOff.vue'
import IconReload from 'vue-material-design-icons/Reload.vue'
import {
  ref,
  del as vueDel,
} from 'vue'
import { useRouter } from 'vue-router/composables'
import generateAppUrl from '../toolkit/util/generate-url.js'
import { v4 as uuidv4 } from 'uuid'
import Console from '../util/console.ts'
import { sanitizePostData } from '../util/legacy-post-data.ts'
import type { TemplatePostData } from '@rotdrop/async-nextcloud-event-bus'

const COMPONENT_NAME = 'BrowserHistoryModal'
const logger = new Console(COMPONENT_NAME)

logger.debug('HERE I AM')

withDefaults(defineProps<{
  heading?: string,
}>(), {
  heading: t(appName, 'Manage Saved Web-Browser History'),
})

const emit = defineEmits([
  'update:show',
])

const modalPageHeadingId = ref<string>(uuidv4())

const history = useHistoryStore()

const historyData = ref<Record<number, HistoryPersistenceRecord<FetchMode> > >({})

const pathDisplayName = (historyEntry: RouterHistoryState<'shallow'>) => {
  const name = historyEntry.path
  return name === '/' ? t(appName, 'Home') : name.replace(/^\/+/, '')
}

const stateDisplayName = <T extends FetchMode>(state: HistoryPersistenceRecord<T>, mtime: number) => {
  return moment(mtime * 1000).format('LLL') + ' @ ' + state.position
}

const expandedState = ref<undefined|number>(undefined)

const dataPopupShown = ref<undefined | string>(undefined)

const isDataPopupShown = (mtime: number, key: string) => dataPopupShown.value === '' + mtime + key

const toggleDataPopupShown = (mtime: number, key: string) => {
  dataPopupShown.value = isDataPopupShown(mtime, key) ? undefined : ('' + mtime + key)
}

const requestData: Record<string, TemplatePostData> = {}

const loading = ref(false)

const router = useRouter()

const pushRoute = async (timestamp: number, key: string) => {
  const entry = historyData.value[timestamp].history[key]
  const postData = await loadPostData(timestamp, key)
  if (!postData) {
    return
  }
  logger.info('POST DATA', postData)
  const resolved = router.resolve(entry.path)
  logger.info('RESOLVED ROUTE', resolved)
  const params = sanitizePostData(Object.assign(postData, resolved.location.params))
  const location = {
    name: resolved.route.name!, // @todo error handling
    params,
  }
  logger.info('ABOUT TO PUSH ROUTE', location)
  return router.push(location)
}

const pushHistoryChain = async (timestamp: number) => {
  const promises = Object.keys(historyData.value[timestamp].history).map(key => loadPostData(timestamp, key))
  await Promise.all(promises) // the attached error handler should catch all errors
  for (const entry of Object.values(historyData.value[timestamp].history)) {
    if (!entry.post) {
      return // user has already be informed
    }
  }
  await history.pushHistoryChain(historyData.value[timestamp].history, historyData.value[timestamp].position)
  emit('update:show', false)
}

const reloadHistoryStates = async () => {
  loading.value = true
  const data = await history.loadHistoryStates()
  historyData.value = data || {}
  for (const key of Object.keys(requestData)) {
    delete requestData[key]
  }
  for (const state of Object.values(historyData.value)) {
    state.requestData = requestData
  }
  loading.value = false
}

reloadHistoryStates()

const deleteHistoryState = async (timestamp: number) => {
  const status = await history.deleteHistoryState(timestamp)
  if (status) {
    vueDel(historyData.value, timestamp)
    if (history.modificationTime === history.saveTime && timestamp === history.saveTime) {
      history.saveTime = 0
    }
  }
}

const loadPostData = async (timestamp: number, key: string) => {
  if (historyData.value[timestamp].history[key].post) {
    return historyData.value[timestamp].history[key].post
  }
  const entry = await history.loadHistoryEntry(timestamp, key)
  if (entry) {
    requestData[entry.hash] = entry.post
    historyData.value[timestamp].history[key].post = entry.post
  }
  return historyData.value[timestamp].history[key].post
}

const makePostDataTooltip = async (timestamp: number, key: string) => {
  const data = await loadPostData(timestamp, key)
  return data
    ? '<pre style="text-align:left;">' + JSON.stringify(data, undefined, 2) + '</pre>'
    : t(appName, 'No data coulde be found.')
}
</script>
<style scoped lang="scss">
.browser-history-modal {
  .modal-page-heading {
    margin-left: 6px;
  }
  .history-entry-name {
    &.current-position {
      font-weight: bold;
      font-style: italic;
      color: red;
    }
  }
  :deep(.browser-history-actions) {
    z-index: 1;
    position: absolute;
    top: 4px;
    inset-inline-end: calc(var(--button-size) + var(--default-grid-baseline));
  }
}
</style>
