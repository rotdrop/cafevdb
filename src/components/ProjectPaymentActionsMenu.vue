<!--
 - Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  <LegacyPageActionsMenu ref="actions"
                         :menuCaption="menuCaption"
                         :enableOverviewItem="enableOverviewItem"
                         :entityId="entityId"
                         :projectId="projectId"
                         :projectName="projectName"
                         :template="template"
  >
    <template #actions>
      <NcActionButton v-tooltip="tooltips['project-payment-action:donation-receipt:download']"
                      :name="t(appName, 'Download Donation Receipt')"
                      :class="[cssClass]"
                      :closeAfterClick="true"
                      :disabled="!isDonation"
                      @click="handleDonationReceiptDownload"
      >
        <template #icon>
          <IconDonationReceiptDownload />
        </template>
      </NcActionButton>
      <NcActionButton v-tooltip="tooltips['project-payment-action:donation-receipt:email']"
                      :name="t(appName, 'Email Donation Receipt')"
                      :class="[cssClass]"
                      :closeAfterClick="true"
                      :disabled="true || !isDonation"
                      @click="handleDonationReceiptEmail"
      >
        <template #icon>
          <IconDonationReceiptEmail />
        </template>
      </NcActionButton>
      <NcActionButton v-tooltip="tooltips['project-payment-action:standard-receipt:download']"
                      :name="t(appName, 'Download Standard Receipt')"
                      :class="[cssClass]"
                      :closeAfterClick="true"
                      :disabled="isDonation"
                      @click="handleStandardReceiptDownload"
      >
        <template #icon>
          <IconStandardReceiptDownload />
        </template>
      </NcActionButton>
      <NcActionButton v-tooltip="tooltips['project-payment-action:standard-receipt:email']"
                      :name="t(appName, 'Email Standard Receipt')"
                      :class="[cssClass]"
                      :closeAfterClick="true"
                      :disabled="isDonation"
                      @click="handleStandardReceiptEmail"
      >
        <template #icon>
          <IconStandardReceiptEmail />
        </template>
      </NcActionButton>
      <NcActionButton v-tooltip="tooltips['project-payment-action:payment:download-data']"
                      :name="t(appName, 'Download Substitution Data')"
                      :class="[cssClass]"
                      :closeAfterClick="true"
                      @click="handleSubstitutionDataDownload"
      >
        <template #icon>
          <IconSubstitutionDataDownload />
        </template>
      </NcActionButton>
    </template>
  </LegacyPageActionsMenu>
</template>

<script setup lang="ts">
// import type { ComponentProps } from '../mountable-component-names.ts'
import type { MailMergeOperation, MailMergePayload } from '../types/ajax/mail-merge.ts'

import { getCurrentUser } from '@nextcloud/auth'
import { showError, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { NcActionButton } from '@nextcloud/vue'
import { computed, ref } from 'vue'
import IconSubstitutionDataDownload from 'vue-material-design-icons/CodeJson.vue'
import IconStandardReceiptDownload from 'vue-material-design-icons/ReceiptOutline.vue'
import IconStandardReceiptEmail from 'vue-material-design-icons/ReceiptSendOutline.vue'
import IconDonationReceiptDownload from 'vue-material-design-icons/ReceiptTextPlusOutline.vue'
import IconDonationReceiptEmail from 'vue-material-design-icons/ReceiptTextSendOutline.vue'
import LegacyPageActionsMenu from './LegacyPageActionsMenu.vue'
import { appName } from '../config.ts'
import * as BusEvents from '../event-bus-events.ts'
import { PROJECT_PAYMENT_ACTIONS_MENU as COMPONENT_NAME } from '../mountable-component-names.ts'
import { emit as asyncEmit } from '../services/async-event-bus.ts'
import useErrorHandlerStore from '../stores/error-handler.ts'
import useTooltipsStore from '../stores/tooltips.ts'
import { AppError } from '../toolkit/types/errors.ts'
import axiosFileDownload from '../toolkit/util/axios-file-download.ts'
import { MailMergeDataset, MailMergeDownload } from '../types/ajax/mail-merge.ts'

const props = withDefaults(defineProps<{
  // eslint-disable-next-line vue/no-unused-properties
  amount?: number
  entityId: number
  // eslint-disable-next-line vue/no-unused-properties
  currencyCode?: string
  // eslint-disable-next-line vue/no-unused-properties
  debitorId: number
  // eslint-disable-next-line vue/no-unused-properties
  debitorName: string
  enableOverviewItem?: boolean
  isDonation: boolean
  menuCaption?: string
  projectId: number
  projectName: string
  template: string
}>(), {
  amount: undefined,
  currencyCode: undefined,
  // eslint-disable-next-line vue/no-boolean-default
  enableOverviewItem: true,
  menuCaption: undefined,
})

const errorHandlerProvider = useErrorHandlerStore()

const errorHandler = errorHandlerProvider.getHandler()

// data
const cssClass = computed(() => appName + '-project-payment-actions')

const tooltipKeys = [
  'project-payment-action:donation-receipt:download',
  'project-payment-action:donation-receipt:email',
  'project-payment-action:standard-receipt:download',
  'project-payment-action:standard-receipt:send',
  'project-payment-action:payment:download-data',
]

const tooltipsProvider = useTooltipsStore()
tooltipsProvider.provideTooltips(tooltipKeys)
const tooltips = tooltipsProvider.tooltipsData

const actions = ref<null|typeof LegacyPageActionsMenu>(null)

const isOpen = () => !!actions.value?.isOpen()
const closeMenu = () => {
  if (actions.value) {
    actions.value.closeMenu()
  }
}
const openMenu = (x?: number, y?: number) => {
  if (actions.value) {
    actions.value.openMenu(x, y)
  }
}

// we need to expose some methods in order to allow legacy code to
// open, close and position the menu.
defineExpose({
  isOpen,
  openMenu,
  closeMenu,
})

const handleDonationReceiptDownload = () => {
  return handleMailMergeDownload('donationReceipt', MailMergeDownload)
}
const handleDonationReceiptEmail = () => {
  showError(t(appName, 'Sending receipts by email is not yet supported.'), { timeout: TOAST_PERMANENT_TIMEOUT })
}
const handleStandardReceiptDownload = () => {
  return handleMailMergeDownload('standardReceipt', MailMergeDownload)
}
const handleStandardReceiptEmail = () => {
  showError(t(appName, 'Sending receipts by email is not yet supported.'), { timeout: TOAST_PERMANENT_TIMEOUT })
}
const handleSubstitutionDataDownload = () => {
  return handleMailMergeDownload('standardReceipt', MailMergeDataset)
}

/**
 * @param templateName TBD.
 *
 * @param operation TBD.
 */
async function handleMailMergeDownload(
  templateName: string,
  operation: MailMergeOperation = MailMergeDownload,
) {
  asyncEmit(BusEvents.PUSH_BUSY_STATE)
  const postData: MailMergePayload = {
    senderId: getCurrentUser()?.uid,
    operation,
    compositePaymentIds: [props.entityId],
    projectId: props.projectId,
    templateName,
  }
  try {
    await axiosFileDownload('documents/mail-merge', postData)
    asyncEmit(BusEvents.POP_BUSY_STATE)
  } catch (error) {
    asyncEmit(BusEvents.POP_BUSY_STATE)
    const messageData = { error, paymentId: props.entityId }
    const message = (operation === MailMergeDownload)
      ? t(appName, 'Unable to download the receipt for the payment with id {paymentId}: {error}.', messageData)
      : t(appName, 'Unable to download the mail-merge substituions for the payment with id {paymentId}: {error}.', messageData)
    errorHandler(
      new AppError(
        { component: COMPONENT_NAME },
        message,
        { cause: error },
      ),
    )
  }
}
</script>

<style lang="scss" scoped>
  // empty
</style>
