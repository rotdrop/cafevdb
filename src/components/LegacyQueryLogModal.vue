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
 -
 -->
<template>
  <NcModal :closeOnClickOutside="false"
           :hasNext="false"
           :hasPrevious="false"
           :labelId="queryLogEntryHeadingId"
           size="large"
           container="#body-user"
           v-bind="$attrs"
  >
    <template #default>
      <h2 :id="queryLogEntryHeadingId" class="query-log-entry-heading">
        {{ t(appName, 'Legacy Sql Query Log') }}
      </h2>
      <ul class="flex-container flex-column">
        <NcListItem bold
                    :active="false"
                    class="query-log-item"
                    :details="queryLogEntry.duration + ' ms'"
                    :counterNumber="queryLogEntry.affectedRows"
                    :forceDisplayActions="true"
        >
          <template #name>
            <h5 class="sql-query">
              {{ t(appName, 'SQL Query') }}
            </h5>
          </template>
          <template #subname>
            <pre>{{ sqlQueryCode }}</pre>
          </template>
          <template #actions>
            <NcActionButton v-tooltip="t(appName, 'Copy to Clipboard')"
                            :name="t(appName, 'Copy to Clipboard')"
                            @click="copyToClipboard"
            >
              <template #icon>
                <IconClipBoard />
              </template>
            </NcActionButton>
          </template>
        </NcListItem>
      </ul>
    </template>
  </NcModal>
</template>

<script setup lang="ts">
import { showError, showInfo } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import {
  NcActionButton,
  NcListItem,
  NcModal,
} from '@nextcloud/vue'
import { mariadb as sqlDialect, formatDialect as sqlFormat } from 'sql-formatter'
import { v4 as uuidv4 } from 'uuid'
import {
  computed,
  ref,
} from 'vue'
// import IconClipBoard from 'vue-material-design-icons/ClipboardOutline.vue'
import IconClipBoard from 'vue-material-design-icons/Clippy.vue'
import { appName } from '../config.ts'
import { LEGACY_QUERY_LOG as COMPONENT_NAME } from '../mountable-component-names.ts'
import Console from '../util/console.ts'
const props = defineProps<{ queryLogEntry: LegacySqlQueryLogEntry }>()

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

logger.info('SQL QUERY MODAL', props)

const queryLogEntryHeadingId = ref<string>(uuidv4())

const sqlQueryCode = computed(() => sqlFormat(props.queryLogEntry.query || '', { dialect: sqlDialect }))

const copyToClipboard = async () => {
  try {
    await navigator.clipboard.writeText(sqlQueryCode.value)
    showInfo(t(appName, 'Query has been copied to the clipboard.'))
  } catch (error) {
    logger.error('CLIPBOARD ERROR', { error })
    showError(t(appName, 'Failed copying query to the clipboard: {error}.', { error: '' + error }))
  }
}

</script>

<style scoped lang="scss">
@use '../../style/mixins/flex.scss';
@include flex.flexRules;
.query-log-entry-heading {
  margin-left: 6px;
}
:deep(.query-log-item) {
  .list-item__anchor {
    height: auto;
  }
  .list-item-content__subname {
    white-space: normal;
  }
  h5 {
    margin: auto;
    color: inherit;
    text-overflow: ellipsis;
    overflow: hidden;
  }
  .list-item-content__actions {
    align-self: start;
  }
  .list-item-content__details {
    align-items: start;
    justify-content: start;
  }
}
</style>
