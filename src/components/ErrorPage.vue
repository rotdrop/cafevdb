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
    <ul class="flex-container flex-column">
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
          <div>
            <!-- TODO: split the message to have a nice continuation, see nextcloud-vue -->
            {{ envelopeErrorMessage }}
          </div>
        </template>
        <template #actions>
          <NcActionButton :name="t(appName, 'report error')"
                          close-after-click
                          @click="showProblemReport = !showProblemReport"
          />
          <NcActionButton v-if="logEntry"
                          close-after-click
                          :name="t(appName, 'open details')"
                          @click="detailsModalOpen = true"
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
      <NcListItem v-else-if="originalError && isJqJsonXHR"
                  :name="t(appName, 'jQuery AJAX ERROR WITH RESPONSE DATA')"
                  :subname="errorMessage"
      />
      <NcListItem v-else-if="originalError && isJqXHR"
                  :name="t(appName, 'jQuery AJAX ERROR WITHOUT RESPONSE DATA')"
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
          <NcButton v-if="!submitted"
                    v-tooltip="hints['error-page:problem-report:cancel']"
                    type="tertiary"
                    name="cancel"
                    @click="showProblemReport = false"
          >
            <template #icon>
              <IconCancel :size="20" />
            </template>
          </NcButton>
          <NcButton v-if="!submitted"
                    v-tooltip="hints['error-page:problem-report:submit']"
                    type="tertiary"
                    name="submit"
                    @click="reportError"
          >
            <template #icon>
              <IconSubmit :size="20" />
            </template>
          </NcButton>
          <NcButton v-if="submitted"
                    v-tooltip="hints['error-page:problem-report:modify-comment']"
                    type="tertiary"
                    name="modify comment"
                    @click="submitted = false"
          >
            <template #icon>
              <IconEdit />
            </template>
          </NcButton>
          <NcButton v-if="submitted"
                    v-tooltip="hints['error-page:problem-report:close']"
                    type="tertiary"
                    name="close"
                    @click="showProblemReport = false"
          >
            <template #icon>
              <IconClose />
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
                    :readonly="submitted"
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
import { computed, ref } from 'vue'
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
import IconClose from 'vue-material-design-icons/Close.vue'
import IconEdit from 'vue-material-design-icons/TextBoxEdit.vue'
import { getCurrentUser } from '@nextcloud/auth'
import NextcloudLogModal from './LogEntry/LogDetailsModal.vue'
import Console from '../util/console.ts'
import { serializeError, isErrorLike } from 'serialize-error'
import type { ErrorLike } from 'serialize-error'
import md5 from 'blueimp-md5'
import axios from '@nextcloud/axios'
import generateAppUrl from '../toolkit/util/generate-url.js'
import { showError, showInfo, /* TOAST_DEFAULT_TIMEOUT, */ TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import { asyncComputed } from '@vueuse/core'
import { tooltips } from '../util/tooltips.ts'
import {
  JQueryAjaxError,
  isJqXHR as isJqXHRGuard,
  isJqJsonXHR as isJqJsonXHRGuard,
  isJqNextcloudLogEntryXHR,
} from '../types/ajax/jqxhr-error.ts'

const COMPONENT_NAME = 'ErrorPage'
const logger = new Console(COMPONENT_NAME)

const props = defineProps <{
  error: Error | AxiosError | AxiosError<NextcloudExceptionLogEntry>,
}>()

const tooltipKeys = [
  'error-page:problem-report:cancel',
  'error-page:problem-report:submit',
  'error-page:problem-report:close',
  'error-page:problem-report:modify-comment',
]
const initialTooltips = Object.fromEntries(tooltipKeys.map(key => { return [key, ''] as [string, string] }))

const hints = asyncComputed(
  (/* onCancel */) => tooltips(tooltipKeys),
  initialTooltips,
  { lazy: true },
)

const router = useRouter()

const envelopeError = computed(() =>
  (props.error instanceof AppError || props.error instanceof JQueryAjaxError) && (props.error.cause instanceof Error || isJqXHRGuard(props.error.cause))
    ? props.error
    : null)
const originalError = computed(() =>
  envelopeError.value && (envelopeError.value.cause instanceof Error || isJqXHRGuard(envelopeError.value.cause))
    ? envelopeError.value.cause
    : envelopeError.value)

logger.debug('ERRORS', envelopeError, originalError)

const isAxiosError = computed(() => isAxiosErrorGuard(originalError.value))
const isAxiosErrorResponse = computed(() => isAxiosErrorResponseGuard(originalError.value))
const isJqXHR = computed(() => isJqXHRGuard(originalError.value))
const isJqJsonXHR = computed(() => isJqJsonXHRGuard(originalError.value))

const logEntry = computed(() =>
  isNextcloudExceptionResponse(originalError.value)
    ? originalError.value.response.data
    : isJqNextcloudLogEntryXHR(originalError.value)
      ? originalError.value.responseJSON
      : null,
)
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
const submitted = ref(false)
const userComment = ref('')
const substitutions = ref<Record<string, string>>({})

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
const serializedError = removeStack(serializeError(props.error, { useToJSON: false }))

logger.debug('SERIALIZED ERROR', { serializedError, origError: props.error })
const systemErrorString = JSON.stringify(serializedError, undefined, 2)

const currentUser = getCurrentUser()
const currentUserDisplay = `${currentUser?.uid} AKA ${currentUser?.displayName}`
const currentUserHeading = t(appName, 'Personal Comments by {user}', { user: currentUserDisplay })
const effectiveUserComment = computed(() => userComment.value ? userComment.value : t(appName, 'No comment.'))
const markDownDocLink = ref('https://www.markdownguide.org/cheat-sheet/')

const reportText = computed(() =>
  `# ${t(appName, 'Problem Report')}
## ${currentUserHeading}
${effectiveUserComment.value}
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
    logger.error('Unable to fetch logreader translations', e)
    translationsLoaded.value = true // still open untranslated
  })

const submittedUserComments: Record<string, string> = {}

const reportError = async () => {
  userComment.value = userComment.value.trim()
  const postData = {
    user: currentUser,
    userComment: userComment.value,
    errorData: serializedError,
  }
  const userCommentHash = md5(userComment.value)
  if (Object.keys(submittedUserComments).includes(userCommentHash)) {
    showInfo(t(
      appName,
      'Your comment has already been submitted with the following notification: {notification}.',
      { notification: submittedUserComments[userCommentHash] },
      undefined,
      { escape: false },
    ))
    submitted.value = true
    return
  }
  console.info('POST DATA', postData)
  const url = generateAppUrl('vue-app/a/problem-report')
  try {
    const result = await axios.post(url, postData)
    const messages = [
      t(appName, 'Your problem-report has been submitted.'),
    ]
    if (Array.isArray(result.data.messages)) {
      messages.splice(0, 1, ...result.data.messages)
    }
    const notification = messages.join(' ')
    showInfo(notification, { timeout: TOAST_PERMANENT_TIMEOUT })
    submittedUserComments[userCommentHash] = notification
    submitted.value = true
  } catch (reportError) {
    // @todo should make this a reusable utility function
    // just notifiy the user that it did not work out
    logger.error('Unable to submit the problem report', reportError)
    const errorString = reportError instanceof Error
      ? reportError.toString()
      : t(appName, 'unknown error')
    const messages = [
      t(appName, 'Could not submit the problem report: "{errorString}".', { errorString }),
    ]
    if (isAxiosErrorResponseGuard(reportError) && Array.isArray(reportError.response.data.messages)) {
      messages.splice(0, 1, ...reportError.response.data.messages)
    }
    showError(messages.join(' '), { timeout: TOAST_PERMANENT_TIMEOUT })
  }
}

</script>
<style scoped lang="scss">
@import './../../style/flex.scss';
.container {
  :deep(.envelope-error) {
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
  }
  .problem-report {
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
<style lange="scss">
.modal-mask {
  &, * {
    box-sizing: border-box;
  }
}
</style>
