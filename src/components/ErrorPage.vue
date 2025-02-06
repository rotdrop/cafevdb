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
    <ul>
      <NcListItem v-if="envelopeError"
                  bold
                  :active="true"
                  class="envelope-error"
                  :force-display-actions="true"
      >
        <template #name>
          <h5 class="envelope-error-heading">
            {{ envelopeErrorMessage }}
          </h5>
        </template>
        <template #subname>
          <NextcloudLogModal v-if="logEntry"
                             :open.sync="detailsModalOpen"
                             :current-entry="logEntry"
                             :translations-loaded="translationsLoaded"
          />
        </template>
        <template #actions>
          <NcActionButton v-if="logEntry"
                          close-after-click
                          :name="t(appName, 'open details')"
                          @click="detailsModalOpen = true"
          />
          <NcActionButton :name="t(appName, 'report error')"
                          close-after-click
                          @click="showProblemReport = !showProblemReport"
          />
          <NcActionButton :name="t(appName, 'go to previous page')"
                          close-after-click
                          @click="router.back()"
          />
          <NcActionButton :name="t(appName, 'try to reload')"
                          close-after-click
                          @click="router.go(0)"
          />
        </template>
      </NcListItem>
      <NcListItem v-else-if="originalError && isAxiosErrorResponse"
                  :name="t(appName, 'AXIOS ERROR WITH RESPONSE DATA')"
                  :subname="errorMessage"
      />
      <NcListItem v-else-if="originalError && isAxiosError"
                  :name="t(appName, 'AXIOS ERROR WITHOUT RESPONSE DATA')"
                  :subname="errorMessage"
      />
      <NcListItem v-else-if="originalError && (originalError instanceof Error)"
                  :name="t(appName, 'FRONTEND ERROR')"
                  :subname="errorMessage"
      />
      <NcListItem v-else :name="t(appName, 'UNKNOWN ERROR')" />
      <NcListItem v-if="showProblemReport"
                  bold
                  class="problem-report-list-item problem-report"
                  :force-display-actions="true"
      >
        <template #name>
          <h5 class="problem-report">
            {{ t(appName, 'Problem Report') }}
          </h5>
        </template>
        <template #extra-actions>
          <NcButton type="terciary"
                    name="cancel"
                    @click="showProblemReport = false"
          >
            <template #icon>
              <IconCancel :size="20" />
            </template>
          </NcButton>
          <NcButton type="terciary" name="submit">
            <template #icon>
              <IconSubmit :size="20" />
            </template>
          </NcButton>
        </template>
        <template #subname>
          <h6>{{ t(appName, 'Optional Comment') }}</h6>
          <div>
            {{ t(appName, 'You can optionally add comments. Markdown is supported.') }}
            <span>(</span><a :href="markDownDocLink" :target="md5(markDownDocLink)">Markdown</a><span>).</span>
            {{ t(appName, 'A preview of the error report is shown below.') }}
          </div>
          <textarea v-model="userComment"
                    rows="5"
                    cols="60"
                    class="user-comment"
                    :placeholder="t(appName, 'Please add your personal notes here.')"
          />
          <h6>{{ t(appName, 'Preview') }}</h6>
          <hr>
          <NcRichText class="problem-report-preview"
                      :text="reportText"
                      :autolink="true"
                      :use-markdown="true"
                      :arguments="substitutions"
          />
        </template>
      </NcListItem>
    </ul>
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
import { computed, watch, ref } from 'vue'
import { appName } from '../config.ts'
import { translate as t, loadTranslations } from '@nextcloud/l10n'
import { useRouter } from 'vue-router/composables'
import {
  NcActionButton,
  NcButton,
  NcListItem,
  NcRichText,
} from '@nextcloud/vue'
import IconSubmit from 'vue-material-design-icons/Send.vue'
import IconCancel from 'vue-material-design-icons/Cancel.vue'
import { getCurrentUser } from '@nextcloud/auth'
import NextcloudLogModal from './LogEntry/LogDetailsModal.vue'
import Console from '../util/console.ts'
import { serializeError, isErrorLike } from 'serialize-error'
import type { ErrorLike } from 'serialize-error'
import md5 from 'blueimp-md5'

const COMPONENT_NAME = 'ErrorPage'
const logger = new Console(COMPONENT_NAME)

const props = defineProps <{
  error: Error | AxiosError | AxiosError<NextcloudExceptionLogEntry>,
}>()

const router = useRouter()

const envelopeError = computed(() =>
  props.error instanceof AppError && props.error.cause instanceof Error
    ? props.error
    : null)
const originalError = computed(() =>
  envelopeError.value && envelopeError.value.cause instanceof Error
    ? envelopeError.value.cause
    : envelopeError.value)

logger.debug('ERRORS', envelopeError, originalError)

const isAxiosError = computed(() => isAxiosErrorGuard(originalError.value))
const isAxiosErrorResponse = computed(() => isAxiosErrorResponseGuard(originalError.value))
const logEntry = computed(() =>
  isNextcloudExceptionResponse(originalError.value)
    ? originalError.value.response.data
    : null)
// const exception = computed(() =>
//   isNextcloudExceptionResponse(originalError.value)
//     ? originalError.value.response.data.exception
//     : null)

const makeErrorMessage = (error: Error) => error.name + ': ' + error.message

const errorMessage = computed(() =>
  originalError.value && originalError.value instanceof Error
    ? makeErrorMessage(originalError.value)
    : '')

const envelopeErrorMessage = computed(() =>
  envelopeError.value && envelopeError.value instanceof Error
    ? makeErrorMessage(envelopeError.value)
    : '')

const showProblemReport = ref(false)
const userComment = ref('')
const substitutions = ref<Record<string, string>>({})

const serializedError = serializeError(props.error, { useToJSON: false })
type StackedErrorObject = Omit<ErrorLike, 'stack'> & { stack?: string | string[] }

// Remove the stack but for the first level. Actually, passing level
// with a positive integer will also remove the stack from the
// top-most error, passing level with a negative value will preserve
// the stack arguments up to (-level + 1).
const removeStack = (error: StackedErrorObject, level: number = 0) => {
  if (error.stack) {
    if (level > 0) {
      delete error.stack
    } else if (typeof error.stack === 'string') {
      error.stack = error.stack.split(/\r?\n|\r|\n/g)
    }
  }
  if (isErrorLike(error.cause)) {
    removeStack(error.cause, level + 1)
  }
  if (Array.isArray(error.errors)) {
    for (const subError of error.errors) {
      if (isErrorLike(subError)) {
        removeStack(subError, level + 1)
      }
    }
  }
  return error
}
logger.debug('SERIALIZED ERROR', { serializedError, origError: props.error })
const systemErrorString = JSON.stringify(removeStack(serializedError), undefined, 2)

const currentUser = getCurrentUser()
const currentUserDisplay = `${currentUser?.uid} AKA ${currentUser?.displayName}`
const currentUserHeading = t(appName, 'Personal Comments by {user}', { user: currentUserDisplay })
const effectiveUsrComment = computed(() => userComment.value ? userComment.value : t(appName, 'No comment.'))
const markDownDocLink = ref('https://www.markdownguide.org/cheat-sheet/')

const reportText = computed(() =>
  `# ${t(appName, 'Problem Report')}
## ${currentUserHeading}
${effectiveUsrComment.value}
## ${t(appName, 'System Error Report')}
*${t(appName, 'You cannot change this part of the report.')}*
\`\`\`
${systemErrorString}
\`\`\`
`,
)

const detailsModalOpen = ref(false)
const translationsLoaded = ref(false)

loadTranslations('logreader', () => false)
  .then(() => {
    translationsLoaded.value = true
  })
  .catch((e) => {
    translationsLoaded.value = true // still open untranslated
  })
</script>
<style lang="scss" scoped>
::v-deep {
  .envelope-error, .problem-report {
    h5 {
      margin: auto;
      color: inherit;
    }
  }
}
.problem-report-preview {
  max-width: 80ex;
  min-width: 60ex;
}
::v-deep .problem-report-list-item {
  textarea.user-comment {
    width: 100%;
    resize: vertical;
  }
  .list-item-content__{
    &actions, &extra-actions {
      align-self: start;
    }
  }
  .list-item__anchor {
    height: auto;
  }
}
</style>
