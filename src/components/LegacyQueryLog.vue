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
  <div class="template-container">
    <NcActions v-model:open="open"
               :menuName="t(appName, 'Query Log')"
               :forceMenu="true"
               forceSemanticType="menu"
               :closeAfterClick="true"
    >
      <NcActionButton v-for="logEntry in queryLog"
                      :key="logEntry.queryHash"
                      v-tooltip="logEntry.query"
                      :name="logLogEntryLabel(logEntry)"
                      closeAfterClick
                      @click="showLogEntry(logEntry)"
      />
    </NcActions>
    <LegacyQueryLogModal :show="doShowLogEntry"
                         :queryLogEntry="currentLogEntry"
                         @update:show="doShowLogEntry = false"
    />
  </div>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import {
  NcActionButton,
  NcActions,
} from '@nextcloud/vue'
import {
  getCurrentInstance,
  onMounted,
  onUnmounted,
  ref,
} from 'vue'
import LegacyQueryLogModal from './LegacyQueryLogModal.vue'
import { appName } from '../config.ts'
import { LEGACY_QUERY_LOG as COMPONENT_NAME } from '../mountable-component-names.ts'
import Console from '../util/console.ts'
const props = defineProps<{ queryLog: LegacySqlQueryLogEntry[] }>()

// Will work in the future ...
// import type { LegacySqlQueryLogEntry } from '../types/legacy-query-log.d.ts'

const logger = new Console(COMPONENT_NAME)

interface LegacySqlQueryLogEntry {
  query: string // SQL code
  queryHash: string // hash code for indexing
  affectedRows: number // integral
  duration: number // micro seconds
  errorCode: number // 0 on success
  errorInfo: null|string
}

const logLogEntryLabel = (logEntry: LegacySqlQueryLogEntry) => {
  const queryTag = logEntry.query.length > 24
    ? logEntry.query.substring(0, 24) + '&#8230;'
    : logEntry.query
  return `${logEntry.duration} ms: ${queryTag}`
}

const open = ref(false)
const doShowLogEntry = ref(false)
const currentLogEntry = ref<LegacySqlQueryLogEntry>(props.queryLog[0])

const showLogEntry = (logEntry: LegacySqlQueryLogEntry) => {
  currentLogEntry.value = logEntry
  doShowLogEntry.value = true
}

onMounted(() => {
  open.value = true
  logger.info('CURRENT INSTANCE', { instance: getCurrentInstance()?.proxy })
})

onUnmounted(() => {
  logger.info('UNMOUNTED')
})

</script>
