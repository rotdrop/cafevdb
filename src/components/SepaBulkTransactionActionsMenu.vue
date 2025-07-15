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
               :class="[{ positioned }, appName + '-sepa-bulk-transaction-actions']"
               :force-menu="true"
               force-semantic-type="menu"
               :open.sync="open"
               @closed="closeMenu"
    >
      <NcActionCaption v-if="showMenuCaption"
                       :class="[ appName + '-sepa-bulk-transaction-actions', 'project-name']"
                       :name="actionMenuCaption"
      />
      <NcActionSeparator v-if="showMenuCaption" />
      <NcActionButton v-if="enableOverviewItem"
                      :class="[appName + '-sepa-bulk-transaction-actions']"
                      :name="t(appName, 'Overview')"
                      :close-after-click="true"
                      @click="openOverview"
      >
        <template #icon>
          <IconOverview />
        </template>
      </NcActionButton>
      <NcActionSeparator v-if="enableOverviewItem" />
      <NcActionButton v-tooltip="tooltips['sepa-bulk-transaction:download']"
                      :class="[appName + '-sepa-bulk-transaction-actions']"
                      :name="t(appName, 'Bank Transaction Data')"
                      :close-after-click="true"
                      @click="handleTransactionDataDownload"
      >
        <template #icon>
          <IconBankTransfer />
        </template>
      </NcActionButton>
      <NcActionButton v-tooltip="tooltips['sepa-bulk-transaction:announce']"
                      :class="[appName + '-sepa-bulk-transaction-actions']"
                      :name="t(appName, 'Email Pre-Notification')"
                      :close-after-click="true"
                      @click="handlePreNotificationEmail"
      >
        <template #icon>
          <IconEmail />
        </template>
      </NcActionButton>
      <NcActionButton v-tooltip="tooltips['sepa-bulk-transaction:gnucash-balance']"
                      :class="[appName + '-sepa-bulk-transaction-actions']"
                      :name="t(appName, 'GnuCash Balance Data')"
                      :close-after-click="true"
      >
        <template #icon>
          <IconGnuCashBalances />
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
import IconBankTransfer from 'vue-material-design-icons/BankTransfer.vue'
import IconGnuCashBalances from 'vue-material-design-icons/BankCheck.vue'
import IconEmail from 'vue-material-design-icons/Email.vue'
import { emit as asyncEmit, subscribe as asyncSubscribe } from '../services/async-event-bus.ts'
import { SEPA_BULK_TRANSACTION_ACTIONS } from '../event-bus-events.ts'
import { closeNavigation } from '../services/navigation.js'
import useTooltipsStore from '../stores/tooltips.ts'
import axiosFileDownload from '../toolkit/util/axios-file-download.ts'
import useErrorHandlerStore from '../stores/error-handler.ts'
import { AppError } from '../types/errors.ts'
import {
  computed,
  ref,
  watch,
  nextTick,
  onMounted,
} from 'vue'
import * as BusEvents from '../event-bus-events.ts'
import Console from '../util/console.ts'
import { SEPA_BULK_TRANSACTION_ACTIONS_MENU as COMPONENT_NAME } from '../mountable-component-names.ts'

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
  forceMenuCaption?: boolean,
  bulkTransactionId: number,
  projectId: number,
  projectName: string,
  enableOverviewItem?: boolean,
  testOpen?: boolean,
}>(), {
  forceMenuCaption: false,
  enableOverviewItem: true,
  testOpen: true,
})

// data
const open = ref(false)
const referenceElement = ref<null|HTMLElement>(null)
const triggerButton = ref<null|NcButtonType>(null)
const positioned = ref(false)
// const project = ref<null|Project>(null)

const tooltipKeys = [
  'sepa-bulk-transaction:download',
  'sepa-bulk-transaction:announce',
  'sepa-bulk-transaction:gnucash-balance',
]

const tooltipsProvider = useTooltipsStore()
tooltipsProvider.provideTooltips(tooltipKeys)
const tooltips = tooltipsProvider.tooltipsData

const showMenuCaption = computed(() => props.forceMenuCaption || positioned.value)
// @TODO generate a useful title identifying the transaction, i.e. with the total amount and the creating time.
const actionMenuCaption = computed(() => 'THIS HAS TO BE IMPLEMENTED')

// watchers
watch(open, (state, oldState) => {
  if (!state && positioned.value) {
    // logger.info('WATCHER CLOSE MENU')
    // this.closeMenu()
  }
  logger.info('OPEN CHANGED', { state, oldState })
})

const openOverview = () => {
  open.value = false
  closeNavigation()
  logger.info('PLEASE IMPLEMENT')
  asyncEmit(BusEvents.SEPA_BULK_TRANSACTION_POPUP, {
    bulkTransactionId: props.bulkTransactionId,
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
  logger.info('-> closeMenu()')
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
  logger.info('<- closeMenu()')
}
const nextFrame = () => {
  return new Promise(resolve => requestAnimationFrame(() => {
    requestAnimationFrame(resolve)
  }))
}
const openMenu = async (x?: number, y?: number) => {
  logger.info('-> openMenu()', x, y, positioned.value)
  setPosition(x, y)
  open.value = true
  if (positioned.value) {
    await nextTick()
    triggerButton.value?.$el.blur()
  }
  logger.info('<- openMenu()', x, y, positioned.value)
}
const moveToAnchor = async (event?: MouseEvent) => {
  if (!open.value || !positioned.value) {
    return
  }
  logger.info('-> moveToAnchor()')
  event?.preventDefault()
  await closeMenu()
  await nextTick()
  openMenu()
  logger.info('<- moveToAnchor()')
}

const isOpen = () => {
  logger.info('OPEN STATE', open.value)
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
  asyncSubscribe(SEPA_BULK_TRANSACTION_ACTIONS, (event) => {
    const projectId = event?.projectId
    const newOpenState = event?.open
    if (!newOpenState
      && open.value
      && +projectId !== -props.projectId
      && (+projectId <= 0 || +projectId === +props.projectId)) {
      closeMenu()
    } else if (newOpenState && projectId === props.projectId) {
      openMenu(event?.x || undefined, event?.y || undefined)
    }
  })
})

const handleTransactionDataDownload = async () => {
  asyncEmit(BusEvents.PUSH_BUSY_STATE)
  const post = {
    bulkTransactionId: props.bulkTransactionId,
    projectId: props.projectId,
    projectName: props.projectName,
  }
  try {
    await axiosFileDownload('finance/sepa/bulk-transactions/export', post)
    asyncEmit(BusEvents.POP_BUSY_STATE)
  } catch (error) {
    asyncEmit(BusEvents.POP_BUSY_STATE)
    errorHandler(
      new AppError(
        { component: COMPONENT_NAME },
        t(appName, 'Unable to export the bulktransaction with id "{bulkTransactionId}".', props),
        { cause: error },
      ),
    )
  }
}

const handlePreNotificationEmail = async () => {
  asyncEmit(BusEvents.EMAIL_POPUP, {
    projectId: props.projectId,
    projectName: props.projectName,
    post: {
      bulkTransactionId: props.bulkTransactionId,
      projectId: props.projectId,
      projectName: props.projectName,
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
.#{$appName}-project-actions.project-name.app-navigation-caption {
  font-weight: bold;
  color: blue;
  font-style: italic;
  text-align: center;
  display: inline-block;
  margin: auto;
  width: 100%;
  padding: 0;
}
.font-currency-symbol {
  display: inline-block;
  width: var(--default-clickable-area);
  height: var(--default-clickable-area);
  text-align: center;
  font-size: large;
  font-weight: bold;
}
.#{$appName}-project-actions::v-deep {
  .action-link__longtext-wrapper, .action-router__longtext-wrapper {
    br {
      display:none;
    }
    .action-link__longtext, .action-router__longtext {
      &:empty {
        display:none;
      }
    }
  }
}
</style>
