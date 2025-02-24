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
           :label-id="errorPageHeadingId"
           size="large"
           container="#body-user"
           v-bind="$attrs"
           v-on="$listeners"
  >
    <template #default>
      <h2 :id="errorPageHeadingId" class="error-page-heading">
        {{ heading }}
      </h2>
      <ErrorPage :error="error" />
    </template>
  </NcModal>
</template>
<script setup lang="ts">
import { appName } from '../config.ts'
import { translate as t } from '@nextcloud/l10n'
import {
  NcModal,
} from '@nextcloud/vue'
import {
  ref,
} from 'vue'
import ErrorPage from './ErrorPage.vue'
import { v4 as uuidv4 } from 'uuid'
import type { AxiosError } from 'axios'
import type { NextcloudExceptionLogEntry } from '../types/ajax/php-exception-response.ts'

withDefaults(defineProps<{
  error: Error | AxiosError | AxiosError<NextcloudExceptionLogEntry>,
  heading?: string,
}>(), {
  heading: t(appName, 'Sorry, an Error Occurred'),
})

const errorPageHeadingId = ref<string>(uuidv4())

// import Console from '../util/console.ts'

// const COMPONENT_NAME = 'HtmlErrorPage'
// const logger = new Console(COMPONENT_NAME)
</script>
<style scoped lang="scss">
.error-page-heading {
  margin-left: 6px;
}
</style>
