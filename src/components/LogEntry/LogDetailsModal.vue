<!--
        SPDX-FileCopyrightText: 2023, 2025 Nextcloud Gmbh and Nextcloud contributors
        SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
  <NcModal ref="modal"
           :show="open"
           size="large"
           :has-previous="false"
           :has-next="false"
           :name="name"
           container="#body-user"
           @update:show="$emit('update:open', false)"
  >
    <template #actions>
      <NcActionButton ref="modalActionsReportError"
                      :name="t(appName, 'report error')"
                      close-after-click
                      @click="handleReportError"
      >
        <template #icon>
          <IconReportError :size="20" />
        </template>
      </NcActionButton>
      <NcActionButton :name="t(appName, 'close details view')"
                      close-after-click
                      @click="emit('update:open', false)"
      >
        <template #icon>
          <IconClose :size="20" />
        </template>
      </NcActionButton>
    </template>
    <template #default>
      <div class="log-details">
        <dl :class="cssLevelClass">
          <dt>{{ t('logreader', 'Level') }}</dt>
          <dd>{{ levelString }}</dd>
          <dt>{{ t('logreader', 'App') }}</dt>
          <dd>
            {{ currentEntry?.app || t('logreader', 'No app in context') }}
          </dd>
          <dt>{{ t('logreader', 'Time') }}</dt>
          <dd>{{ timeString }}</dd>
        </dl>        <div class="log-details__actions">
          <NcButton :aria-label="t('logreader', 'Copy raw entry')" type="tertiary" @click="copyRaw">
            <template #icon>
              <IconContentCopy />
            </template>
            {{ t('logreader', 'Copy raw entry') }}
          </NcButton>
          <NcButton :aria-label="t('logreader', 'Copy formatted entry')" type="tertiary" @click="copyFormatted">
            <template #icon>
              <IconContentCopy />
            </template>
            {{ t('logreader', 'Copy formatted entry') }}
          </NcButton>
          <NcButton v-if="currentEntry.exception"
                    class="log-details__btn"
                    @click="isExceptionExpanded = !isExceptionExpanded"
          >
            {{ isExceptionExpanded ? t('logreader', 'Hide exception details') : t('logreader', 'View exception details') }}
          </NcButton>
        </div>
        <template v-if="currentEntry.exception">
          <LogException :exception="currentEntry.exception"
                        class="log-details__exception"
                        :is-expanded="isExceptionExpanded"
          />
          <hr>
        </template>
        <figure class="log-details__raw">
          <figcaption>{{ t('logreader', 'Raw log entry') }}</figcaption>
          <!-- eslint-disable-next-line vue/no-v-html -->
          <pre><code class="hljs language-json" v-html="code" /></pre>
        </figure>
      </div>
    </template>
  </NcModal>
</template>

<script setup lang="ts">
import { appName } from '../../config.ts'
import type { ILogEntry } from '@nextcloud/app-logreader/src/interfaces/index.ts'
import { translate as t } from '@nextcloud/l10n'
import { showSuccess } from '@nextcloud/dialogs'
import { computed, onMounted, ref, watch, watchEffect } from 'vue'
import { copyToCipboard } from '@nextcloud/app-logreader/src/utils/clipboard.ts'
import { useLogFormatting } from '@nextcloud/app-logreader/src/utils/format.ts'
import { LOGGING_LEVEL, LOGGING_LEVEL_NAMES } from '@nextcloud/app-logreader/src/constants.ts'
import {
  NcActionButton,
  NcButton,
  NcModal,
} from '@nextcloud/vue'
import IconContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import IconClose from 'vue-material-design-icons/Close.vue'
import IconReportError from 'vue-material-design-icons/EmailArrowRightOutline.vue'
import hljs from 'highlight.js/lib/core'
import json from 'highlight.js/lib/languages/json'

import 'highlight.js/styles/base16/material-darker.css'
import LogException from './exception/LogException.vue'

hljs.registerLanguage('json', json)

const props = withDefaults(defineProps<{
  open: boolean,
  currentEntry: ILogEntry,
  name?: string
}>(), {
  name: undefined,
})

const emit = defineEmits([
  'update:open',
  'problem-report:show',
])

const { formatTime, formatLogEntry } = useLogFormatting()

/**
 * Whether to show the full exception
 */
const isExceptionExpanded = ref(!!props.currentEntry.exception)

/**
 * Hide exception is current entry is changes
 */
watchEffect(() => {
  isExceptionExpanded.value = !!props.currentEntry.exception
})

/**
 * Formatted data of the entry
 */
const code = computed(
  () =>
    hljs.highlight(JSON.stringify(props.currentEntry, null, 2), { language: 'json' })
      .value,
)

/**
 * Level as translated human readable string
 */
const levelString = computed(() => LOGGING_LEVEL_NAMES[props.currentEntry.level])

/**
 * Time as localized string
 */
const timeString = computed(() => formatTime(props.currentEntry.time))

/**
 * css classes to assign logging level as border color
 */
const cssLevelClass = computed(() => [
  'log-details__info',
  `log-details__info--${LOGGING_LEVEL[props.currentEntry.level]}`,
])

/**
 * Copy the raw log entry as json
 */
const copyRaw = async () => {
  if (await copyToCipboard(JSON.stringify(props.currentEntry))) {
    showSuccess(t('logreader', 'Log entry successfully copied'))
  }
}

/**
 * Copy the log entry formatted to be human readable
 */
const copyFormatted = async () => {
  if (await copyToCipboard(formatLogEntry(props.currentEntry))) {
    showSuccess(t('logreader', 'Log entry successfully copied'))
  }
}

const handleReportError = () => {
  emit('update:open', false)
  emit('problem-report:show', true)
}

const modal = ref(null)

const openMenu = () => {
  console.info('OPEN MENU', { modal: modal.value })
  for (const child of (modal.value?.$children || [])) {
    if (child?.actionsMenuSemanticType === 'menu') {
      child.openMenu()
    }
  }
}

watch(() => props.open, value => {
  console.info('OPEN WATCHER', { value })
  if (value) {
    openMenu()
  }
})

onMounted(() => {
  if (props.open) {
    openMenu()
  }
})

</script>

<style lang="scss" scoped>
.log-details {
  padding: 12px;

  &__raw,
  &__exception {
    padding-block-start: 12px;
  }

  &__info {
    display: flex;
    justify-content: space-between;
    border-block-end: 4px solid;
    padding-inline-end: 50px;
    padding-block: 13px 4px;
    margin-block-end: 13px;

    dt,
    dd {
      padding: 0;
    }

    dt {
      font-weight: bold;

      &::after {
        content: ':';
      }
    }

    &--debug {
      border-block-end-color: var(--color-border-maxcontrast);
    }

    &--info {
      border-block-end-color: var(--color-info);
    }

    &--warning {
      border-block-end-color: var(--color-warning);
    }

    &--error,
    &--fatal {
      border-block-end-color: var(--color-error);
    }
  }

  &__actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: end;
    gap: 9px;
    margin-block: 9px;
  }

  hr {
    color: var(--color-border-dark);
  }
}

.hljs {
  background-color: var(--color-background-dark);
  border-radius: var(--border-radius-large);
}

// For mobile we need to show the details as a vertical list
@media only screen and (max-width: 399px) {
  .log-details {
    &__info {
      display: block;
    }

    dd {
      margin-inline-start: 12px;
    }
  }
}
</style>
