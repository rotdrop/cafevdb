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
      <NcActionButton v-tooltip.right="tooltips['sepa-bulk-transaction:download']"
                      :class="[appName + '-sepa-bulk-transaction-actions']"
                      :name="t(appName, 'Bank Transaction Data')"
                      :closeAfterClick="true"
                      @click="handleTransactionDataDownload"
      >
        <template #icon>
          <IconBankTransfer />
        </template>
      </NcActionButton>
      <NcActionButton v-tooltip.right="tooltips['sepa-bulk-transaction:announce']"
                      :class="[appName + '-sepa-bulk-transaction-actions']"
                      :name="t(appName, 'Email Pre-Notification')"
                      :closeAfterClick="true"
                      @click="handlePreNotificationEmail"
      >
        <template #icon>
          <IconEmail />
        </template>
      </NcActionButton>
      <NcActionButton v-tooltip.right="tooltips['sepa-bulk-transaction:gnucash-balance']"
                      :class="[appName + '-sepa-bulk-transaction-actions']"
                      :name="t(appName, 'GnuCash Balance Data')"
                      :closeAfterClick="true"
                      @click="handleGnuCashBalanceDownload"
      >
        <template #icon>
          <IconGnuCashBalances />
        </template>
      </NcActionButton>
    </template>
  </LegacyPageActionsMenu>
</template>

<script setup lang="ts">
// import type { ComponentProps } from '../mountable-component-names.ts'

import { translate as t } from '@nextcloud/l10n'
import { NcActionButton } from '@nextcloud/vue'
import { useTemplateRef } from 'vue'
import IconGnuCashBalances from 'vue-material-design-icons/BankCheck.vue'
import IconBankTransfer from 'vue-material-design-icons/BankTransfer.vue'
import IconEmail from 'vue-material-design-icons/Email.vue'
import LegacyPageActionsMenu from './LegacyPageActionsMenu.vue'
import { EnumSepaBulkTransactionsExportPurpose, EnumSepaBulkTransactionsTopic } from '../../build/ts-types/php-modules/Controller.ts'
import { END_POINT } from '../../build/ts-types/php-modules/Controller/SepaBulkTransactionsController.ts'
import { appName } from '../config.ts'
import * as BusEvents from '../event-bus-events.ts'
import { SEPA_BULK_TRANSACTION_ACTIONS_MENU as COMPONENT_NAME } from '../mountable-component-names.ts'
import { emit as asyncEmit } from '../services/async-event-bus.ts'
import useErrorHandlerStore from '../stores/error-handler.ts'
import useTooltipsStore from '../stores/tooltips.ts'
import { AppError } from '../toolkit/types/errors.ts'
import axiosFileDownload from '../toolkit/util/axios-file-download.ts'

const props = withDefaults(
  // defineProps<ComponentProps[typeof COMPONENT_NAME]>
  defineProps<{
    enableOverviewItem?: boolean
    entityId: number
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

const errorHandlerProvider = useErrorHandlerStore()

const errorHandler = errorHandlerProvider.getHandler()

const tooltipKeys = [
  'sepa-bulk-transaction:download',
  'sepa-bulk-transaction:announce',
  'sepa-bulk-transaction:gnucash-balance',
]

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

// we need to expose some methods in order to allow legacy code to
// open, close and position the menu.
defineExpose({
  isOpen,
  openMenu,
  closeMenu,
})

const handleTransactionDataDownload = async () => {
  asyncEmit(BusEvents.PUSH_BUSY_STATE)
  const post = {
    bulkTransactionId: props.entityId,
    projectId: props.projectId,
    projectName: props.projectName,
    purpose: EnumSepaBulkTransactionsExportPurpose.BANK_IMPORT,
    format: 'aqbanking',
  }
  try {
    await axiosFileDownload(`${END_POINT}/${EnumSepaBulkTransactionsTopic.EXPORT}`, post)
    asyncEmit(BusEvents.POP_BUSY_STATE)
  } catch (error) {
    asyncEmit(BusEvents.POP_BUSY_STATE)
    errorHandler(
      new AppError(
        { component: COMPONENT_NAME },
        t(appName, 'Unable to export the bank import-data for the bulktransaction with id "{entityId}".', props),
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
      bulkTransactionId: props.entityId,
      projectId: props.projectId,
      projectName: props.projectName,
    },
  })
}

const handleGnuCashBalanceDownload = async () => {
  asyncEmit(BusEvents.PUSH_BUSY_STATE)
  const post = {
    bulkTransactionId: props.entityId,
    projectId: props.projectId,
    projectName: props.projectName,
    purpose: EnumSepaBulkTransactionsExportPurpose.BALANCING_ITEMS,
    format: 'gnucash',
  }
  try {
    await axiosFileDownload('finance/sepa/bulk-transactions/export', post)
    asyncEmit(BusEvents.POP_BUSY_STATE)
  } catch (error) {
    asyncEmit(BusEvents.POP_BUSY_STATE)
    errorHandler(
      new AppError(
        { component: COMPONENT_NAME },
        t(appName, 'Unable to export the balancing items for the bulktransaction with id "{entityId}".', props),
        { cause: error },
      ),
    )
  }
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
    &, :deep(*) {
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
</style>
