<!--
 - SPDX-FileCopyrightText: 2026 Claus-Justus Heine
 - SPDX-License-Identifier: AGPL-3.0-or-later
 -->
<template>
  <NcActions class="pme-export-menu"
             :menuName="t(appName, 'Export Table')"
             :disabled="disabled || downloading"
             :forceMenu="true"
             forceSemanticType="menu"
             variant="secondary"
             size="small"
             placement="bottom"
  >
    <template #icon>
      <ExportIcon :size="20" />
    </template>
    <NcActionButton :name="t(appName, 'Excel Export')"
                    :closeAfterClick="true"
                    @click="download('EXCEL')"
    />
    <NcActionButton :name="t(appName, 'CSV Export')"
                    :closeAfterClick="true"
                    @click="download('CSV')"
    />
  </NcActions>
</template>

<script setup lang="ts">
import type { PmeExportMenuProps } from '../types/pme-export-menu.ts'

import { translate as t } from '@nextcloud/l10n'
import { emit as asyncEmit } from '@rotdrop/async-nextcloud-event-bus'
import { ref } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import ExportIcon from 'vue-material-design-icons/Download.vue'
import { appName } from '../config.ts'
import { POP_BUSY_STATE, PUSH_BUSY_STATE } from '../event-bus-events.ts'
import axiosFileDownload from '../toolkit/util/axios-file-download.ts'
import { showError } from '../toolkit/util/toasts.ts'

const props = defineProps<PmeExportMenuProps>()
const downloading = ref(false)
const download = async (format: 'EXCEL'|'CSV') => {
  if (props.disabled || downloading.value) {
    return
  }
  downloading.value = true
  asyncEmit(PUSH_BUSY_STATE)
  try {
    const post = new FormData(props.form)
    post.set('exportFormat', format)
    await axiosFileDownload('page/pme/export', post)
  } catch (error) {
    console.error('Unable to export table', { format, error })
    showError(t(appName, 'Unable to download table in format "{format}".', { format }))
  } finally {
    downloading.value = false
    asyncEmit(POP_BUSY_STATE)
  }
}
</script>

<style scoped lang="scss">
.pme-export-menu {
  display: inline-flex;

  :deep(.action-item__menutoggle) {
    --default-clickable-area: 28px;
    --clickable-area-small: 28px;
    --font-weight-element: bold;
    box-sizing: border-box;
    height: 28px;
    min-height: 28px;
    margin-block: 0;
  }
}
</style>
