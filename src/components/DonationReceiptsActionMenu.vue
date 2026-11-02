<!--
 - Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
      <NcActionButton v-tooltip.right="tooltips['donation-receipts:download']"
                      :class="[cssClass]"
                      :name="t(appName, 'Download Donation Receipt')"
                      :closeAfterClick="true"
                      @click="handleDonationReceiptDownload(MailMergeDownload)"
      >
        <template #icon>
          <IconDonationReceiptDownload />
        </template>
      </NcActionButton>
      <NcActionButton v-tooltip.right="tooltips['donation-receipts:send']"
                      :class="[cssClass]"
                      :name="t(appName, 'Email Donation Receipt')"
                      :closeAfterClick="true"
                      @click="handleDonationReceiptEmail"
      >
        <template #icon>
          <IconDonationReceiptEmail />
        </template>
      </NcActionButton>
      <NcActionButton v-tooltip.right="tooltips['donation-receipts:download-data']"
                      :class="[cssClass]"
                      :name="t(appName, 'Download Substitution Data')"
                      :closeAfterClick="true"
                      @click="handleDonationReceiptDownload(MailMergeDataset)"
      >
        <template #icon>
          <IconSubstitutionDataDownload />
        </template>
      </NcActionButton>
    </template>
  </LegacyPageActionsMenu>
</template>

<script setup lang="ts">
import type { MailMergeOperation, MailMergePayload } from '../types/ajax/mail-merge.ts'

import { getCurrentUser } from '@nextcloud/auth'
import { translate as t } from '@nextcloud/l10n'
import { NcActionButton } from '@nextcloud/vue'
import { emit as asyncEmit } from '@rotdrop/async-nextcloud-event-bus'
import { computed, useTemplateRef } from 'vue'
import IconSubstitutionDataDownload from 'vue-material-design-icons/CodeJson.vue'
import IconDonationReceiptDownload from 'vue-material-design-icons/ReceiptTextPlusOutline.vue'
import IconDonationReceiptEmail from 'vue-material-design-icons/ReceiptTextSendOutline.vue'
import LegacyPageActionsMenu from './LegacyPageActionsMenu.vue'
import { appName } from '../config.ts'
import * as BusEvents from '../event-bus-events.ts'
import { DONATION_RECEIPTS_ACTION_MENU as COMPONENT_NAME } from '../mountable-component-names.ts'
import useErrorHandlerStore from '../stores/error-handler.ts'
import useTooltipsStore from '../stores/tooltips.ts'
import { AppError } from '../toolkit/types/errors.ts'
import axiosFileDownload from '../toolkit/util/axios-file-download.ts'
import { MailMergeDataset, MailMergeDownload } from '../types/ajax/mail-merge.ts'
import Console from '../util/console.ts'

const props = withDefaults(
  defineProps<{
    enableOverviewItem?: boolean
    entityId: number
    paymentId: number
    menuCaption?: string
    projectId: number
    projectName: string
    template: string
  }>(),
  {
    // eslint-disable-next-line vue/no-boolean-default
    enableOverviewItem: true,
    menuCaption: undefined,
  },
)

const logger = new Console(COMPONENT_NAME)

const errorHandlerProvider = useErrorHandlerStore()
const errorHandler = errorHandlerProvider.getHandler()

const cssClass = computed(() => appName + '-donation-receipts-actions')

const tooltipKeys = ['donation-receipts:download', 'donation-receipts:send', 'donation-receipts:download-data']
const tooltipsProvider = useTooltipsStore()
tooltipsProvider.provideTooltips(tooltipKeys)
const tooltips = tooltipsProvider.tooltipsData

const actions = useTemplateRef<typeof LegacyPageActionsMenu>('actions')

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

defineExpose({
  isOpen,
  openMenu,
  closeMenu,
})

const handleDonationReceiptDownload = async (operation: MailMergeOperation = MailMergeDownload) => {
  asyncEmit(BusEvents.PUSH_BUSY_STATE)
  const post: MailMergePayload = {
    templateName: 'donationReceipt',
    senderId: getCurrentUser()?.uid,
    compositePaymentIds: [props.paymentId],
    projectId: props.projectId,
    operation,
  }
  try {
    await axiosFileDownload('documents/mail-merge', post)
    asyncEmit(BusEvents.POP_BUSY_STATE)
  } catch (error) {
    asyncEmit(BusEvents.POP_BUSY_STATE)
    logger.error('Unable to download donation receipt', { props, error })
    const messageData = {
      error: (error as { message?: string }).message || t(appName, 'unknown error'),
      receiptId: props.entityId,
    }
    const message
      = operation === MailMergeDownload
        ? t(appName, 'Unable to download the donation receipt with id {receiptId}: {error}.', messageData)
        : t(appName, 'Unable to download the mail-merge substitutions for donation receipt with id {receiptId}: {error}.', messageData)
    errorHandler(new AppError({ component: COMPONENT_NAME }, message, { cause: error }))
  }
}

const handleDonationReceiptEmail = () => {
  asyncEmit(BusEvents.EMAIL_POPUP, {
    projectId: props.projectId,
    projectName: props.projectName,
    post: {
      donationReceiptId: props.entityId,
      singleRecipient: true,
    },
  })
}
</script>

<style lang="scss" scoped>
// empty
</style>
