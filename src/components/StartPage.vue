<!--
 - @copyright Copyright (c) 2024, 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
 -
 - @author Claus-Justus Heine <himself@claus-justus-heine.de>
 -
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
 - GNU Affero General Public License for more detail.s
 -
 - You should have received a copy of the GNU Affero General Public License
 - along with this program. If not, see <http://www.gnu.org/licenses/>.
 -
 -->

<template>
  <NcEmptyContent>
    <template #name>
      <h2>{{ t(appName, '{orchestraName} Orchestra Management App', { orchestraName }) }}</h2>
    </template>
    <template #icon>
      <DynamicSvgIcon :size="64" :data="appIcon" :title="orchestraName + ' logo'" />
      <!-- eslint-disable-next-line vue/no-v-html -->
      <!-- <span class="app-icon" v-html="icon" /> -->
    </template>
    <template #description>
      <span>
        {{ t(appName, 'Please click on the ☰-button in order to open the navigation menu.') }}
        {{ t(appName, 'Please click on your avatar or initials (top-right) for logout and configuration options.') }}
      </span>
    </template>
  </NcEmptyContent>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import { NcEmptyContent } from '@nextcloud/vue'
import { ref } from 'vue'
import DynamicSvgIcon from '@rotdrop/nextcloud-vue-components/lib/components/DynamicSvgIcon.vue'
import appIcon from '../../img/cafevdb.svg?raw'
import { appName } from '../config.ts'
import getInitialState from '../toolkit/util/initial-state.ts'

const initialState = getInitialState({ section: 'CAFEVDB' })

// TRANSLATORS: unknown orchestra name placeholder
const orchestraName = ref(initialState?.orchestra || t(appName, '[UNKNOWN]'))

</script>

<style lang="scss" scoped>
.empty-content :deep() {
  h2 ~ p {
    text-align: center;
    width: 72ex;
  }
  .hint {
    color: var(--color-text-lighter);
  }
  .empty-content__icon {
    margin-top: 16px;
  }
}
</style>
