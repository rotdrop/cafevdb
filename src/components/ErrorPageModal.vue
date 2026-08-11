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
  <NcModal ref="modal"
           :closeOnClickOutside="false"
           :hasNext="false"
           :hasPrevious="false"
           :labelId="errorPageHeadingId"
           size="large"
           container="#body-user"
           v-bind="$attrs"
  >
    <template #default>
      <h2 :id="errorPageHeadingId" class="error-page-heading">
        {{ heading }}
      </h2>
      <ErrorPage :error="error"
                 :initialView="initialView"
                 :noSummary="noSummary"
                 :closeDetailsLabel="closeDetailsLabel"
                 @close="modal && modal.close()"
      />
    </template>
  </NcModal>
</template>

<script setup lang="ts">
import type { AxiosError } from 'axios'
import type { NextcloudExceptionLogEntry } from '../types/ajax/php-exception-response.ts'

import { translate as t } from '@nextcloud/l10n'
import {
  NcModal,
} from '@nextcloud/vue'
import { v4 as uuidv4 } from 'uuid'
import {
  ref,
} from 'vue'
import ErrorPage from './ErrorPage.vue'
import { appName } from '../config.ts'
// import Console from '../util/console.ts'

// const COMPONENT_NAME = 'ErrorPageModal'
// const logger = new Console(COMPONENT_NAME)

withDefaults(defineProps<{
  error: Error | AxiosError | AxiosError<NextcloudExceptionLogEntry>
  heading?: string
  initialView?: 'summary'|'details'|'report'
  noSummary?: boolean
  closeDetailsLabel?: string
}>(), {
  heading: t(appName, 'Sorry, an Error Occurred'),
  initialView: 'summary',
  noSummary: false,
  closeDetailsLabel: t(appName, 'close details view'),
})

const errorPageHeadingId = ref<string>(uuidv4())
const modal = ref<null|typeof NcModal>(null)

</script>

<style scoped lang="scss">
.error-page-heading {
  margin-left: 6px;
}
</style>
