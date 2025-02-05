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
    <div v-if="envelopeError" class="envelope-error">
      {{ envelopeErrorMessage }}
    </div>
    <!-- <NextcloudLogException v-if="exception"
                           :exception="exception"
                           :is-expanded="true"
    /> -->
    <NextcloudLogModal v-if="exception"
                       :open="true"
                       :current-entry="logEntry"
    />
    <div v-else-if="originalError && isAxiosErrorResponse">
      <p>{{ t(appName, 'AXIOS ERROR WITH RESPONSE DATA') }}</p>
      <p>{{ errorMessage }}</p>
    </div>
    <div v-else-if="originalError && isAxiosError">
      <p>{{ t(appName, 'AXIOS ERROR WITHOUT RESPONSE DATA') }}</p>
      <p>{{ errorMessage }}</p>
    </div>
    <div v-else-if="originalError && (originalError instanceof Error)">
      <p>{{ t(appName, 'FRONTEND ERROR') }}</p>
      <p>{{ errorMessage }}</p>
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
import { AppError } from '../types/errors.ts'
import { computed } from 'vue'
import { appName } from '../config.ts'
import { translate as t, loadTranslations } from '@nextcloud/l10n'
import NextcloudLogModal from './LogEntry/LogDetailsModal.vue'
import NextcloudLogException from './LogEntry/exception/LogException.vue'
import Console from '../util/console.ts'

const COMPONENT_NAME = 'ErrorPage'
const logger = new Console(COMPONENT_NAME)

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

const props = defineProps <{
  error: Error | AxiosError | AxiosError<NextcloudExceptionLogEntry>,
}>()

const envelopeError = computed(() =>
  props.error instanceof AppError && props.error.cause instanceof Error
    ? props.error
    : null)
const originalError = computed(() =>
  envelopeError.value && envelopeError.value.cause instanceof Error
    ? envelopeError.value.cause
    : envelopeError.value)

logger.info('ERRORS', envelopeError, originalError)

const isAxiosError = computed(() => isAxiosErrorGuard(originalError.value))
const isAxiosErrorResponse = computed(() => isAxiosErrorResponseGuard(originalError.value))
const logEntry = computed(() =>
  isNextcloudExceptionResponse(originalError)
    ? originalError.value.response.data
    : null)
const exception = computed(() =>
  isNextcloudExceptionResponse(originalError.value)
    ? originalError.value.response.data.exception
    : null)

const makeErrorMessage = (error: Error) => error.name + ': ' + error.message

const errorMessage = computed(() =>
  originalError.value && originalError.value instanceof Error
    ? makeErrorMessage(originalError.value)
    : '')

const envelopeErrorMessage = computed(() =>
  envelopeError.value && envelopeError.value instanceof Error
    ? makeErrorMessage(envelopeError.value)
    : '')

</script>
