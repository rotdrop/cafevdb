<!--
 - SPDX-License-Identifier: AGPL-3.0-or-later
 -->
<template>
  <div>
    <section v-show="visible"
             ref="envelope"
             class="emailform-envelope emailform ui-widget ui-front"
             :class="appNameTag"
             :style="{ zIndex }"
             role="region"
             :aria-labelledby="appPrefix('email-form-title')"
             :aria-busy="blocked"
    >
      <header class="emailform-envelope-header">
        <h2 :id="appPrefix('email-form-title')" ref="heading" tabindex="-1">
          {{ title }}
        </h2>
        <NcButton :disabled="blocked"
                  :aria-label="t(appName, 'Minimize')"
                  @click="visible = false"
        >
          <template #icon>
            <MinimizeIcon />
          </template>
        </NcButton>
        <NcButton :disabled="blocked"
                  :aria-label="t(appName, 'Close')"
                  @click="requestClose"
        >
          <template #icon>
            <CloseIcon />
          </template>
        </NcButton>
      </header>
      <!-- Only the legacy handlers own descendants of this HTML island. -->
      <!-- eslint-disable vue/no-v-html -->
      <div id="emailformdialog"
           ref="legacyHtmlContainer"
           class="emailform-envelope-content"
           :inert="blocked || undefined"
           v-html="contents"
      />
      <!-- eslint-enable vue/no-v-html -->
    </section>
    <NcButton v-if="!visible"
              class="emailform-envelope-restore"
              :style="{ zIndex }"
              @click="show"
    >
      {{ title }}
    </NcButton>
  </div>
</template>

<script setup lang="ts">
import type { EmailFormEnvelopeProps } from '../types/email-form-envelope.ts'

import { translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import { nextTick, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import MinimizeIcon from 'vue-material-design-icons/WindowMinimize.vue'
import { appName, appNameTag, appPrefix } from '../config.ts'

const props = defineProps<EmailFormEnvelopeProps>()
const emit = defineEmits<{
  ready: [container: HTMLElement]
  close: []
  cleanup: [container: HTMLElement]
}>()

const title = ref(props.title)
const visible = ref(true)
const blocked = ref(false)
const zIndex = ref(100)
const envelope = useTemplateRef<HTMLElement>('envelope')
const heading = useTemplateRef<HTMLElement>('heading')
const legacyHtmlContainer = useTemplateRef<HTMLElement>('legacyHtmlContainer')
let previousFocus: HTMLElement | null = null

const show = () => {
  for (const front of document.querySelectorAll<HTMLElement>('.ui-front')) {
    if (front !== envelope.value) {
      const index = Number.parseInt(getComputedStyle(front).zIndex)
      if (Number.isFinite(index)) {
        zIndex.value = Math.max(zIndex.value, index + 1)
      }
    }
  }
  visible.value = true
  void nextTick(() => heading.value?.focus())
}

const requestClose = () => {
  if (!blocked.value) {
    emit('close')
  }
}

onMounted(() => {
  previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null
  show()
  emit('ready', legacyHtmlContainer.value!)
})

onBeforeUnmount(() => {
  emit('cleanup', legacyHtmlContainer.value!)
  if (previousFocus?.isConnected) {
    previousFocus.focus()
  }
})

defineExpose({
  setTitle: (value: string) => { title.value = value },
  setBlocked: (value: boolean) => { blocked.value = value },
  show,
})
</script>

<style scoped lang="scss">
.emailform-envelope {
  position: fixed;
  inset: var(--header-height, 50px) 0 0;
  display: flex;
  flex-direction: column;
  border: 0;
  border-radius: 0;
  background: var(--color-main-background);
  overflow: hidden;

  .emailform-envelope-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    flex-shrink: 0;
    border-bottom: 1px solid var(--color-border);

    h2 {
      flex: 1;
      min-width: 0;
      margin: 0;
      overflow-wrap: anywhere;
    }
  }

  .emailform-envelope-content {
    flex: 1;
    min-height: 0;
    padding: 0;
    overflow: hidden;
  }

  :deep(#emailformwrapper) {
    display: flex;
    flex-direction: column;
    height: 100%;
  }

  :deep(#emailformtabs) {
    flex-shrink: 0;
  }

  :deep(form) {
    flex: 1;
    min-height: 0;
    overflow: hidden;
  }

  :deep(.ui-tabs-panel) {
    box-sizing: border-box;
    width: 100%;
  }

  :deep(fieldset.page) {
    margin-inline: auto;
  }
}

.emailform-envelope-restore {
  position: fixed;
  top: var(--header-height, 50px);
  right: 16px;
}
</style>
