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
  <div class="container">
    <NextcloudLogException v-if="isPHPException"
                           :exception="exception"
                           :is-expanded="true"
    />
    <div v-else-if="isAxiosErrorResponse">
      {{ t(appName, 'AXIOS ERROR WITH RESPONSE DATA') }}
    </div>
    <div v-else-if="isAxiosError">
      {{ t(appName, 'AXIOS ERROR WITHOUT RESPONSE DATA') }}
    </div>
    <div v-else>
      {{ t(appName, 'UNKNOWN ERROR') }}
    </div>
  </div>
</template>
<script setup lang="ts">
import { isNextcloudExceptionResponse } from '../types/ajax/php-exception-response.ts'
import type { NextcloudExceptionLogEntry } from '../types/ajax/php-exception-response.ts'
import type { AxiosError } from 'axios'
import {
  isAxiosErrorResponse as isAxiosErrorResponseGuard,
  isAxiosError as isAxiosErrorGuard,
} from '../types/ajax/axios-type-guards.ts'
import { computed } from 'vue'
import type { PropType } from 'vue'
import { appName } from '../config.ts'
import { translate as t, loadTranslations } from '@nextcloud/l10n'
import NextcloudLogException from '@nextcloud/app-logreader/src/components/exception/LogException.vue'

loadTranslations('logreader', () => console.info('LOGREADER TRANSLATION HAVE BEEN LOADED'))
  .then((...args) => console.info('LOGREADER LOAD PROMISE', ...args))
  .catch((...args) => console.error('LOGREADER TRANSLATIONS NOT LOADED', ...args))

// unfortunately, the Logger app forgets to flags the isExpanded and
// isPrevious props as optional, though it does specify default values
// "false".

// @ts-expect-error The Logger app forgets to flags this property as optional though it provides a default.
NextcloudLogException.props.isExpanded.required = false
// @ts-expect-error The Logger app forgets to flags this property as optional though it provides a default.
NextcloudLogException.props.isPrevious.required = false

const props = defineProps({
  // The error resulting from a try-catch. Hence no type information is available
  error: {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    type: Object as PropType<any | AxiosError | AxiosError<NextcloudExceptionLogEntry> >,
    required: true,
  },
})

const isAxiosError = computed(() => isAxiosErrorGuard(props.error))
const isAxiosErrorResponse = computed(() => isAxiosErrorResponseGuard(props.error))
const isPHPException = computed(() => isNextcloudExceptionResponse(props.error))
const logEntry = computed(() => isPHPException.value ? props.error.response.data : null)
const exception = computed(() => isPHPException.value ? logEntry.value.exception : null)

</script>
