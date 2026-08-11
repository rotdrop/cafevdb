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
      <NcActionButton v-tooltip.right="tooltips['invoice:download']"
                      :class="[cssClass]"
                      :name="t(appName, 'Download Standard Invoice')"
                      :closeAfterClick="true"
                      @click="handleInvoiceDownload(MailMergeDownload)"
      >
        <template #icon>
          <IconInvoiceDownload />
        </template>
      </NcActionButton>
      <NcActionButton v-tooltip.right="tooltips['invoice:send']"
                      :class="[cssClass]"
                      :name="t(appName, 'Email Standard Invoice')"
                      :closeAfterClick="true"
                      @click="handleInvoiceEmail"
      >
        <template #icon>
          <IconEmail />
        </template>
      </NcActionButton>
      <NcActionButton v-tooltip.right="tooltips['invoice:download-data']"
                      :class="[cssClass]"
                      :name="t(appName, 'Download Substitution Data')"
                      :closeAfterClick="true"
                      @click="handleInvoiceDownload(MailMergeDataset)"
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

import { translate as t } from '@nextcloud/l10n'
import { NcActionButton } from '@nextcloud/vue'
import { computed, ref } from 'vue'
import IconSubstitutionDataDownload from 'vue-material-design-icons/CodeJson.vue'
import IconEmail from 'vue-material-design-icons/Email.vue'
import IconInvoiceDownload from 'vue-material-design-icons/FileDownloadOutline.vue'
import LegacyPageActionsMenu from './LegacyPageActionsMenu.vue'
import { appName } from '../config.ts'
import * as BusEvents from '../event-bus-events.ts'
import { INVOICE_ACTIONS_MENU as COMPONENT_NAME } from '../mountable-component-names.ts'
import { emit as asyncEmit } from '../services/async-event-bus.ts'
import useErrorHandlerStore from '../stores/error-handler.ts'
import useTooltipsStore from '../stores/tooltips.ts'
import { AppError } from '../toolkit/types/errors.ts'
import axiosFileDownload from '../toolkit/util/axios-file-download.ts'
import { MailMergeDataset, MailMergeDownload } from '../types/ajax/mail-merge.ts'
import Console from '../util/console.ts'

const props = withDefaults(defineProps<{
  // amount?: number
  // currencyCode?: string
  // debitorId?: number
  // debitorName?: string
  enableOverviewItem?: boolean
  entityId: number
  invoiceNumber: string
  menuCaption?: string
  originatorId: number
  // originatorName?: string
  projectId: number
  projectName: string
  template: string
}>(), {
  amount: undefined,
  currencyCode: undefined,
  debitorId: undefined,
  debitorName: undefined,
  // eslint-disable-next-line vue/no-boolean-default
  enableOverviewItem: true,
  menuCaption: undefined,
  originatorName: undefined,
})

const logger = new Console(COMPONENT_NAME)

const errorHandlerProvider = useErrorHandlerStore()

const errorHandler = errorHandlerProvider.getHandler()

// data
const cssClass = computed(() => appName + '-invoice-actions')

const tooltipKeys = [
  'invoice:download',
  'invoice:send',
  'invoice:download-data',
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

const handleInvoiceDownload = async (operation: MailMergeOperation = MailMergeDownload) => {
  asyncEmit(BusEvents.PUSH_BUSY_STATE)
  const post: MailMergePayload = {
    templateName: 'invoice',
    senderId: props.originatorId,
    invoiceIds: [props.entityId],
    projectId: props.projectId,
    operation,
  }
  try {
    await axiosFileDownload('documents/mail-merge', post)
    asyncEmit(BusEvents.POP_BUSY_STATE)
  } catch (error) {
    asyncEmit(BusEvents.POP_BUSY_STATE)
    logger.error('Unable to download invoice', { props, error })
    const messageData = {
      error: (error as { message?: string }).message || t(appName, 'unknown error'),
      invoiceNumber: props.invoiceNumber,
    }
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
  // empty
</style>
