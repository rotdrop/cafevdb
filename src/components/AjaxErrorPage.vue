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
 - @file
 - Wrap an NcSelect into a coponent with submit button.
 -->
<script setup lang="ts">
import { isPHPExceptionResponse } from '../types/ajax/php-exception-response.ts'
import type { PHPExceptionData } from '../types/ajax/php-exception-response.ts'
import type { AxiosError } from 'axios'
import { isAxiosError as isAxiosErrorGuard } from '../types/ajax/axios-type-guards.ts'
import { computed } from 'vue'
import type { PropType } from 'vue'
import { appName } from '../config.ts'
import { translate as t } from '@nextcloud/l10n'

const props = defineProps({
  // The error resulting from a try-catch. Hence no type information is available
  error: {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    type: Object as PropType<any | AxiosError | AxiosError<PHPExceptionData>>,
    required: true,
  },
})

const isAxiosError = computed(() => isAxiosErrorGuard(props.error))
const isPHPException = computed(() => isPHPExceptionResponse(props.error))

const exceptionChainData = !isPHPExceptionResponse(props.error)
  ? null
  : props.error.response!.data

// for use with v-for, generate a flat array of preceding exceptions
const previousExceptions = computed(() => {
  let exception = exceptionChainData?.previous || null
  if (!exception) {
    return []
  }
  const value: PHPExceptionData[] = []
  while (exception) {
    value.push(exception)
    exception = exception.previous
  }
  return value
})
</script>
<template>
  <!-- dummy -->
  <div v-if="isPHPException">
    {{ t(appName, 'PHP Exception with {count} previous exceptions.', {count: previousExceptions.length}) }}
  </div>
  <div v-else-if="isAxiosError">
    {{ t(appName, 'AXIOS ERROR') }}
  </div>
  <div v-else>
    {{ t(appName, 'UNKNOWN ERROR') }}
  </div>
</template>
