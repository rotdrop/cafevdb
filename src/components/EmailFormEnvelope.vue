<!--
 - SPDX-License-Identifier: AGPL-3.0-or-later
 -->
<template>
  <div>
    <section v-show="visible"
             ref="envelope"
             class="emailform-envelope emailform ui-widget ui-front"
             :class="appNameTag"
             :style="{ zIndex, insetInlineStart: `${navigationOffset}px` }"
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
      <div id="emailformdialog"
           ref="legacyHtmlContainer"
           class="emailform-envelope-content"
           :inert="blocked || undefined"
      >
        <div id="emailformwrapper">
          <div class="emailform-navigation">
            <div role="tablist" :aria-label="t(appName, 'Email Form')" class="emailform-tabs">
              <NcButton v-for="tab in tabs"
                        :id="`emailform${tab.id}-tab`"
                        :key="tab.id"
                        role="tab"
                        :aria-controls="panelId(tab.id)"
                        :aria-selected="activeTab === tab.id"
                        :tabindex="activeTab === tab.id ? 0 : -1"
                        :disabled="blocked || isDisabled(tab.id)"
                        :variant="activeTab === tab.id ? 'primary' : 'tertiary'"
                        @click="selectTab(tab.id)"
                        @keydown="navigateTabs($event, tab.id)"
              >
                {{ tab.label }}
              </NcButton>
            </div>
            <NcActions :disabled="blocked" :menuName="t(appName, 'Manual')" forceName>
              <NcActionButton @click="openManual(false)">
                {{ t(appName, 'Manual (other tab)') }}
              </NcActionButton>
              <NcActionButton @click="openManual(true)">
                {{ t(appName, 'Manual (popup)') }}
              </NcActionButton>
            </NcActions>
          </div>
          <!-- Only the legacy handlers own descendants of the HTML islands. -->
          <!-- eslint-disable vue/no-v-html -->
          <form :id="appPrefix('email-form')"
                method="post"
                :name="appPrefix('email-form')"
                :class="[appPrefix('email-form'), { 'project-mode': form.projectId > 0 }]"
          >
            <fieldset :id="appPrefix('email-form-data')" class="form-data" v-html="form.formData" />
            <div v-for="tab in tabs"
                 v-show="activeTab === tab.id"
                 :id="panelId(tab.id)"
                 :key="tab.id"
                 class="emailform-panel resize-target"
                 role="tabpanel"
                 :aria-labelledby="`emailform${tab.id}-tab`"
                 :aria-hidden="activeTab !== tab.id"
                 tabindex="0"
                 v-html="tab.contents"
            />
          </form>
          <!-- eslint-enable vue/no-v-html -->
          <div id="attachment_upload_wrapper" class="data-upload-wrapper">
            <form id="attachment_upload_form"
                  data-upload-id="1"
                  class="file-upload-form"
                  :action="form.uploadAction"
                  method="post"
                  enctype="multipart/form-data"
                  target="attachment_upload_target_1"
            >
              <input id="requesttoken"
                     type="hidden"
                     name="requesttoken"
                     :value="form.requestToken"
              >
              <input type="hidden" class="max_human_file_size" :value="`(max ${form.uploadMaxHumanFilesize})`">
              <input id="attachment_upload_start"
                     type="file"
                     class="file-upload-start"
                     name="files[]"
                     multiple
              >
            </form>
            <div class="uploadprogresswrapper">
              <div class="uploadprogressbar" />
              <input type="button"
                     class="stop"
                     style="display:none"
                     :value="t(appName, 'Cancel upload')"
              >
            </div>
          </div>
          <div id="sendingprogresswrapper">
            <div class="messagecount" />
            <div v-for="stage in ['smtp', 'imap']"
                 :key="stage"
                 class="progress"
                 :class="[appPrefix('row'), stage]"
            >
              <span class="title">{{ stage === 'smtp' ? t(appName, 'Message Delivery') : t(appName, 'Copy to Sent') }}</span>
              <span class="progressbar" />
            </div>
          </div>
        </div>
      </div>
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
import type { EmailFormCommand, EmailFormEnvelopeProps, EmailFormTab } from '../types/email-form-envelope.ts'

import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { NcActionButton, NcActions, NcButton } from '@nextcloud/vue'
import { emit as emitEvent, subscribe, unsubscribe } from '@rotdrop/async-nextcloud-event-bus'
import { nextTick, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import MinimizeIcon from 'vue-material-design-icons/WindowMinimize.vue'
import { appName, appNameTag, appPrefix } from '../config.ts'
import { EMAIL_FORM_COMMAND, EMAIL_FORM_EVENT, WIKI_POPUP } from '../event-bus-events.ts'

const props = defineProps<EmailFormEnvelopeProps>()

const title = ref(props.title)
const activeTab = ref<EmailFormTab>(props.single ? 'composer' : 'recipients')
const recipientsDisabled = ref(props.single)
const tabs = [
  { id: 'recipients' as const, label: t(appName, 'Recipients'), contents: props.form.recipients },
  { id: 'composer' as const, label: t(appName, 'Message Composition'), contents: props.form.composer },
  { id: 'status' as const, label: t(appName, 'Status and Preview'), contents: '' },
]
const panelId = (tab: EmailFormTab) => tab === 'status' ? 'emailformdebug' : `emailform${tab}`
const isDisabled = (tab: EmailFormTab) => tab === 'recipients' && recipientsDisabled.value
const visible = ref(true)
const blocked = ref(false)
const zIndex = ref(100)
const navigationOffset = ref(0)
const envelope = useTemplateRef<HTMLElement>('envelope')
const heading = useTemplateRef<HTMLElement>('heading')
const legacyHtmlContainer = useTemplateRef<HTMLElement>('legacyHtmlContainer')
let previousFocus: HTMLElement | null = null
let navigationObserver: MutationObserver | undefined
let navigationResizeObserver: ResizeObserver | undefined
const navigation = document.getElementById('app-navigation-vue')?.closest<HTMLElement>('.app-navigation')

const updateNavigationOffset = () => {
  if (!navigation || navigation.classList.contains('app-navigation--closed')) {
    navigationOffset.value = 0
    return
  }
  const container = navigation.parentElement!.getBoundingClientRect()
  const start = getComputedStyle(navigation).direction === 'rtl' ? window.innerWidth - container.right : container.left
  navigationOffset.value = Math.max(0, start + navigation.getBoundingClientRect().width)
}

updateNavigationOffset()

const selectTab = async (tab: EmailFormTab) => {
  if (blocked.value || isDisabled(tab) || activeTab.value === tab) {
    return
  }
  const previous = activeTab.value
  const panel = legacyHtmlContainer.value!.querySelector<HTMLElement>(`#${panelId(tab)}`)!
  emitEvent(EMAIL_FORM_EVENT, { action: 'before-tab-change', previous, tab, panel })
  activeTab.value = tab
  await nextTick()
  if (activeTab.value === tab && panel.isConnected) {
    emitEvent(EMAIL_FORM_EVENT, { action: 'tab-changed', previous, tab, panel })
  }
}

const navigateTabs = async (event: KeyboardEvent, tab: EmailFormTab) => {
  const enabled = tabs.filter(({ id }) => !isDisabled(id))
  const index = enabled.findIndex(({ id }) => id === tab)
  let next: number
  switch (event.key) {
    case 'ArrowLeft':
      next = (index + enabled.length - 1) % enabled.length
      break
    case 'ArrowRight':
      next = (index + 1) % enabled.length
      break
    case 'Home':
      next = 0
      break
    case 'End':
      next = enabled.length - 1
      break
    default: return
  }
  event.preventDefault()
  if (blocked.value) {
    return
  }
  await selectTab(enabled[next]!.id)
  document.getElementById(`emailform${enabled[next]!.id}-tab`)?.focus()
}

const openManual = (popup: boolean) => {
  if (blocked.value) {
    return
  }
  const wikiPage = [props.form.wikiNamespace, appName, 'documentation', 'user-manual', 'emailform'].join(':')
  if (popup) {
    emitEvent(WIKI_POPUP, {
      wikiPage,
      popupTitle: t(appName, 'User Manual: {section}', { section: t(appName, 'Email Form') }, { escape: false }),
    })
  } else {
    window.open(generateUrl('/apps/dokuwiki/{wikiPage}', { wikiPage }), `${appName}:user-manual`)
  }
}

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
    emitEvent(EMAIL_FORM_EVENT, { action: 'close' })
  }
}

const handleCommand = (command: EmailFormCommand) => {
  switch (command.action) {
    case 'show':
      show()
      break
    case 'title':
      title.value = command.title
      break
    case 'blocked':
      blocked.value = command.blocked
      break
    case 'select-tab': return selectTab(command.tab)
    case 'enable-recipients':
      recipientsDisabled.value = false
      break
  }
}

onMounted(() => {
  if (navigation) {
    navigationObserver = new MutationObserver(updateNavigationOffset)
    navigationObserver.observe(navigation, { attributes: true, attributeFilter: ['class'] })
    navigationResizeObserver = new ResizeObserver(updateNavigationOffset)
    navigationResizeObserver.observe(navigation)
    window.addEventListener('resize', updateNavigationOffset)
  }
  subscribe(EMAIL_FORM_COMMAND, handleCommand)
  previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null
  show()
  emitEvent(EMAIL_FORM_EVENT, { action: 'ready', container: legacyHtmlContainer.value! })
})

onBeforeUnmount(() => {
  navigationObserver?.disconnect()
  navigationResizeObserver?.disconnect()
  window.removeEventListener('resize', updateNavigationOffset)
  unsubscribe(EMAIL_FORM_COMMAND, handleCommand)
  emitEvent(EMAIL_FORM_EVENT, { action: 'cleanup', container: legacyHtmlContainer.value! })
  if (previousFocus?.isConnected) {
    previousFocus.focus()
  }
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
  transition: inset-inline-start var(--animation-slow, 200ms);

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

  .emailform-navigation {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    padding: 4px 8px;
    border-bottom: 1px solid var(--color-border);
  }

  .emailform-tabs {
    display: flex;
    flex: 1;
    min-width: 0;
    overflow-x: auto;

    > * {
      flex-shrink: 0;
    }
  }

  #emailformwrapper > form {
    flex: 1;
    min-height: 0;
    overflow: hidden;
  }

  .emailform-panel {
    box-sizing: border-box;
    width: 100%;
    padding: 1em 1.4em;
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
