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
 -->
<template>
  <div class="container">
    <NcActions v-if="positioned"
               :force-menu="true"
               :manual-open="true"
               @click="moveToAnchor"
    >
      <NcActionSeparator v-show="false" />
    </NcActions>
    <NcActions ref="actions"
               :class="[{ positioned }, cssClass]"
               :force-menu="true"
               force-semantic-type="menu"
               :open.sync="open"
               @closed="closeMenu"
    >
      <NcActionCaption v-if="showMenuCaption"
                       :class="[cssClass, 'menu-caption']"
                       :name="menuCaptionBlah"
      />
      <NcActionSeparator v-if="showMenuCaption" class="menu-caption" />
      <NcActionButton v-if="enableOverviewItem"
                      :class="[cssClass]"
                      :name="t(appName, 'Overview')"
                      :close-after-click="true"
                      @click="openOverview"
      >
        <template #icon>
          <IconOverview />
        </template>
      </NcActionButton>
      <NcActionSeparator v-if="enableOverviewItem" class="overview" />
      <NcActionButton v-tooltip="tooltips['invoice:download']"
                      :class="[cssClass]"
                      :name="t(appName, 'Download Standard Invoice')"
                      :close-after-click="true"
                      @click="handleInvoiceDownload(MailMergeDownload)"
      >
        <template #icon>
          <IconInvoiceDownload />
        </template>
      </NcActionButton>
      <NcActionButton v-tooltip="tooltips['invoice:send']"
                      :class="[cssClass]"
                      :name="t(appName, 'Email Standard Invoice')"
                      :close-after-click="true"
                      @click="handleInvoiceEmail"
      >
        <template #icon>
          <IconEmail />
        </template>
      </NcActionButton>
      <NcActionButton v-tooltip="tooltips['invoice:download-data']"
                      :class="[cssClass]"
                      :name="t(appName, 'Download Substitution Data')"
                      :close-after-click="true"
                      @click="handleInvoiceDownload(MailMergeDataset)"
      >
        <template #icon>
          <IconSubstitutionDataDownload />
        </template>
      </NcActionButton>
    </NcActions>
  </div>
</template>
<script setup lang="ts">
import {
  NcActions,
  NcActionButton,
  NcActionCaption,
  NcActionSeparator,
} from '@nextcloud/vue'
import { appName } from '../config.ts'
import { translate as t } from '@nextcloud/l10n'
import IconOverview from 'vue-material-design-icons/InformationOutline.vue'
import IconInvoiceDownload from 'vue-material-design-icons/FileDownloadOutline.vue'
import IconSubstitutionDataDownload from 'vue-material-design-icons/CodeJson.vue'
import IconEmail from 'vue-material-design-icons/Email.vue'
import { emit as asyncEmit, subscribe as asyncSubscribe } from '../services/async-event-bus.ts'
import { INVOICE_ACTIONS } from '../event-bus-events.ts'
import { closeNavigation } from '../services/navigation.js'
import useTooltipsStore from '../stores/tooltips.ts'
import axiosFileDownload from '../toolkit/util/axios-file-download.ts'
import useErrorHandlerStore from '../stores/error-handler.ts'
import { AppError } from '../types/errors.ts'
import {
  computed,
  nextTick,
  onMounted,
  ref,
  watch,
} from 'vue'
import * as BusEvents from '../event-bus-events.ts'
import Console from '../util/console.ts'
import { INVOICE_ACTIONS_MENU as COMPONENT_NAME } from '../mountable-component-names.ts'
import type { MailMergePayload, MailMergeOperation } from '../types/ajax/mail-merge.ts'
import { MailMergeDownload, MailMergeDataset } from '../types/ajax/mail-merge.ts'

const logger = new Console(COMPONENT_NAME)

type NcButtonType = {
  ref?: string,
  $el: HTMLElement,
}
type NcActionsType = {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  closeMenu(returnFocus?: boolean):Promise<any>,
  $refs: {
    popover: { $refs: { popover: { $refs: { reference: HTMLElement, } } } },
    triggerButton: NcButtonType,
  },
}

const errorHandlerProvider = useErrorHandlerStore()

const errorHandler = errorHandlerProvider.getHandler()

const props = withDefaults(defineProps</* ComponentProps[typeof COMPONENT_NAME] */{
  menuCaption: string,
  invoiceNumber: string,
  invoiceId: number,
  originatorId: number,
  projectId: number,
  projectName: string,
  enableOverviewItem?: boolean,
}>(), {
  enableOverviewItem: true,
})

// data
const open = ref(false)
const referenceElement = ref<null|HTMLElement>(null)
const triggerButton = ref<null|NcButtonType>(null)
const positioned = ref(false)
const cssClass = computed(() => appName + '-invoice-actions')
const showMenuCaption = computed(() => positioned.value)
const menuCaptionBlah = computed(() => {
  return props.menuCaption || 'WHAT THE FUCK'
})

const tooltipKeys = [
  'invoice:download',
  'invoice:send',
  'invoice:download-data',
]

const tooltipsProvider = useTooltipsStore()
tooltipsProvider.provideTooltips(tooltipKeys)
const tooltips = tooltipsProvider.tooltipsData

// watchers
watch(open, (state, oldState) => {
  if (!state && positioned.value) {
    // logger.debug('WATCHER CLOSE MENU')
    // this.closeMenu()
  }
  logger.debug('OPEN CHANGED', { state, oldState })
})

const openOverview = () => {
  open.value = false
  closeNavigation()
  asyncEmit(BusEvents.INVOICE_POPUP, {
    invoiceId: props.invoiceId,
    projectId: props.projectId,
    projectName: props.projectName,
  })
}
const setPosition = (x?: number, y?: number) => {
  if (x !== undefined && y !== undefined) {
    referenceElement.value!.style.position = 'fixed'
    referenceElement.value!.style.left = x + 'px'
    referenceElement.value!.style.top = y + 'px'

    positioned.value = true
  } else if (positioned.value) {
        referenceElement.value!.style.position = ''
    referenceElement.value!.style.left = ''
    referenceElement.value!.style.top = ''

    positioned.value = false
  }
}
const closeMenu = async () => {
  logger.debug('-> closeMenu()')
  if (open.value) {
    open.value = false
    await nextTick()
  }
  if (positioned.value) {
    // the open trigger was a context menu click, so there is not
    // point to return the focus to the menu button.
    triggerButton.value?.$el.blur()
  }
  for (let i = 0; i < 2; ++i) {
    await nextFrame()
    await nextTick()
  }
  setPosition()
  logger.debug('<- closeMenu()')
}
const nextFrame = () => {
  return new Promise(resolve => requestAnimationFrame(() => {
    requestAnimationFrame(resolve)
  }))
}
const openMenu = async (x?: number, y?: number) => {
  logger.debug('-> openMenu()', x, y, positioned.value)
  setPosition(x, y)
  open.value = true
  if (positioned.value) {
    await nextTick()
    triggerButton.value?.$el.blur()
  }
  logger.debug('<- openMenu()', x, y, positioned.value)
}
const moveToAnchor = async (event?: MouseEvent) => {
  if (!open.value || !positioned.value) {
    return
  }
  logger.debug('-> moveToAnchor()')
  event?.preventDefault()
  await closeMenu()
  await nextTick()
  openMenu()
  logger.debug('<- moveToAnchor()')
}

const isOpen = () => {
  logger.debug('OPEN STATE', open.value)
  return open.value
}

// we need to expose some methods in order to allow legacy code to
// open, close and position the menu.
defineExpose({
  isOpen,
  openMenu,
  closeMenu,
})

// onBeforeMount(async () => {
//   await syncProjectData(props.projectId)
// })

const actions = ref<null|NcActionsType>(null)

onMounted(() => {
  const origCloseMenu = actions.value!.closeMenu
  actions.value!.closeMenu = (returnFocus) => origCloseMenu(positioned.value ? false : returnFocus)
  referenceElement.value = actions.value!.$refs.popover.$refs.popover.$refs.reference
  triggerButton.value = actions.value!.$refs.triggerButton
  asyncSubscribe(INVOICE_ACTIONS, (event) => {
    const invoiceId = event?.invoiceId
    const newOpenState = event?.open
    if (!newOpenState
      && open.value
      && +invoiceId !== -props.invoiceId
      && (+invoiceId <= 0 || +invoiceId === +props.invoiceId)) {
      closeMenu()
    } else if (newOpenState && invoiceId === props.invoiceId) {
      openMenu(event?.x || undefined, event?.y || undefined)
    }
  })
})

const handleInvoiceDownload = async (operation: MailMergeOperation = MailMergeDownload) => {
  asyncEmit(BusEvents.PUSH_BUSY_STATE)
  const post: MailMergePayload = {
    templateName: 'invoice',
    senderId: props.originatorId,
    invoiceIds: [props.invoiceId],
    projectId: props.projectId,
    operation,
  }
  try {
    await axiosFileDownload('documents/mail-merge', post)
    asyncEmit(BusEvents.POP_BUSY_STATE)
  } catch (error) {
    asyncEmit(BusEvents.POP_BUSY_STATE)
    const messageData = { error, invoiceNumber: props.invoiceNumber }
    const message = (operation === MailMergeDownload)
      ? t(appName, 'Unable to download the invoice with invoice-number {invoiceNumber}: {error}.', messageData)
      : t(appName, 'Unable to download the mail-merge substituions for invoice-number {invoiceNumber}: {error}.', messageData)
    errorHandler(
      new AppError(
        { component: COMPONENT_NAME },
        message,
        { cause: error },
      ),
    )
  }
}

const handleInvoiceEmail = async () => {
  asyncEmit(BusEvents.EMAIL_POPUP, {
    projectId: props.projectId,
    post: {
      ...props,
    },
  })
}

</script>
<style lang="scss" scoped>
.container {
  display: flex;
  :deep(.button-vue__icon) svg {
    width: 28px;
    height: 28px;
  }
  .action-item.action-item--open.positioned {
    &, ::v-deep * {
      width: 0 !important;
      height: 0 !important;
      min-width: 0 !important;
      min-height: 0 !important;
      max-width: 0 !important;
      max-height: 0 !important;
      overflow: hidden;
    }
  }
}
.#{$appName}-invoice-actions.menu-caption.app-navigation-caption {
  font-weight: bold;
  color: blue;
  font-style: italic;
  text-align: center;
  display: inline-block;
  margin: auto;
  width: 100%;
  padding: 0;
}
</style>
