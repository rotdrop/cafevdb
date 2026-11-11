<!--
 - SPDX-License-Identifier: AGPL-3.0-or-later
 -->
<template>
  <template v-if="minimizedEmailFormTitle !== null">
    <div class="buttonseparator" />
    <NcButton class="emailform-envelope-restore"
              :class="appPrefix('top-nav-button')"
              :aria-label="label"
              :title="label"
              @click="restore"
    >
      <template #icon>
        <EmailIcon />
      </template>
    </NcButton>
  </template>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import { emit } from '@rotdrop/async-nextcloud-event-bus'
import { computed } from 'vue'
import EmailIcon from 'vue-material-design-icons/EmailOutline.vue'
import { appName, appPrefix } from '../config.ts'
import { EMAIL_FORM_COMMAND } from '../event-bus-events.ts'
import { minimizedEmailFormTitle } from '../services/email-form-state.ts'

const label = computed(() => t(appName, 'Restore {title}', { title: minimizedEmailFormTitle.value ?? '' }))
const restore = () => emit(EMAIL_FORM_COMMAND, { action: 'show' })
</script>

<style scoped lang="scss">
.buttonseparator {
  background: var(--cafevdb-toolbar-separator-color);
  height: var(--cafevdb-toolbar-separator-height);
  width: var(--cafevdb-toolbar-separator-width);
  opacity: var(--cafevdb-toolbar-separator-opacity);
  margin: var(--cafevdb-toolbar-separator-margin);
}
</style>
