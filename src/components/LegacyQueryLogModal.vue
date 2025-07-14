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
           :label-id="queryLogEntryHeadingId"
           size="large"
           container="#body-user"
           v-bind="$attrs"
           v-on="$listeners"
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
                    :counter-number="queryLogEntry.affectedRows"
                    :force-display-actions="true"
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
import { appName } from '../config.ts'
import { translate as t } from '@nextcloud/l10n'
import {
  NcActionButton,
  NcListItem,
  NcModal,
} from '@nextcloud/vue'
// import IconClipBoard from 'vue-material-design-icons/ClipboardOutline.vue'
import IconClipBoard from 'vue-material-design-icons/Clippy.vue'
import {
  computed,
  ref,
} from 'vue'
import { showInfo, showError } from '@nextcloud/dialogs'
import { v4 as uuidv4 } from 'uuid'
import { formatDialect as sqlFormat, mariadb as sqlDialect } from 'sql-formatter'
import Console from '../util/console.ts'
import { LEGACY_QUERY_LOG as COMPONENT_NAME } from '../mountable-component-names.ts'
// Will work in the future ...
// import type { LegacySqlQueryLogEntry } from '../types/legacy-query-log.d.ts'

const logger = new Console(COMPONENT_NAME)

interface LegacySqlQueryLogEntry {
  query: string, // SQL code
  queryHash: string, // hash code for indexing
  affectedRows: number, // integral
  duration: number, // micro seconds
  errorCode: number, // 0 on success
  errorInfo: null|string,
}

const props = defineProps<{ queryLogEntry: LegacySqlQueryLogEntry }>()

logger.info('SQL QUERY MODAL', props)

const queryLogEntryHeadingId = ref<string>(uuidv4())

const sqlQueryCode = computed(() => sqlFormat(props.queryLogEntry.query || '', { dialect: sqlDialect }))

const copyToClipboard = async () => {
  try {
    await navigator.clipboard.writeText(props.queryLogEntry.query)
    showInfo(t(appName, 'Query has been copied to the clipboard.'))
  } catch (error) {
    logger.error('CLIPBOARD ERROR', { error })
    showError(t(appName, 'Failed copying query to the clipboard: {error}.', { error }))
  }
}

</script>
<style scoped lang="scss">
@use '../../style/mixins/flex.scss';
@include flex.flexRules;
.query-log-entry-heading {
  margin-left: 6px;
}
::v-deep .query-log-item {
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
