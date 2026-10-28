<!--
 - Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2011-2016, 2020-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  <div class="templateroot personal-settings">
    <div class="personal-settings-content">
      <component :is="tabComponents[tab.key]"
                 v-for="tab in tree"
                 :key="tab.key"
                 :entry="tab"
                 @error="handleSettingsError"
      />
    </div>
    <SettingsNavigation v-if="isGroupAdmin" :tree="tree" />
    <div v-if="!!appError" class="flex-container flex-justify-center">
      <ErrorPageModal :show="!!appError"
                      :error="appError"
                      @update:show="appError = null"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import { onUnmounted, ref } from 'vue'
import type { Component } from 'vue'
import ErrorPageModal from './ErrorPageModal.vue'
import AboutSettings from './personal-settings/AboutSettings.vue'
import CmsSettings from './personal-settings/CmsSettings.vue'
import DevelopmentSettings from './personal-settings/DevelopmentSettings.vue'
import EmailSettings from './personal-settings/EmailSettings.vue'
import GeneralSettings from './personal-settings/GeneralSettings.vue'
import OrchestraSettings from './personal-settings/OrchestraSettings.vue'
import SharingSettings from './personal-settings/SharingSettings.vue'
import SettingsNavigation from './personal-settings/SettingsNavigation.vue'
import UserSettings from './personal-settings/UserSettings.vue'
import { getSettingsTree } from './personal-settings/settings-tree.ts'
import { appName as appId } from '../config.ts'
import useErrorHandlerStore from '../stores/error-handler.ts'
import { AppError } from '../toolkit/types/errors.ts'
import getInitialState from '../toolkit/util/initial-state.ts'
import Console from '../util/console.ts'

const COMPONENT_NAME = 'PersonalSettings'
const logger = new Console(COMPONENT_NAME)

const errorHandlerProvider = useErrorHandlerStore()

const appError = ref<null | AppError>(null)
const errorHandler = <E extends AppError>(error: E) => {
  logger.debug('TOP LEVEL ERROR', error)
  if (!error.cause) {
    appError.value = new AppError({ component: COMPONENT_NAME }, t(appId, 'Top-Level Error'), { cause: error })
  } else {
    appError.value = error
  }
}
errorHandlerProvider.pushHandler(errorHandler)

const handleSettingsError = (error: Error) => {
  errorHandler(new AppError(
    { component: COMPONENT_NAME },
    t(appId, 'Unable to save personal setting.'),
    { cause: error },
  ))
}

const initialState = getInitialState<{ isGroupAdmin?: boolean }>({ section: 'CAFEVDB', defaults: {} })
const isGroupAdmin = !!initialState?.isGroupAdmin

const tree = getSettingsTree(isGroupAdmin)
const tabComponents: Record<string, Component> = {
  user: UserSettings,
  general: GeneralSettings,
  orchestra: OrchestraSettings,
  sharing: SharingSettings,
  email: EmailSettings,
  cms: CmsSettings,
  development: DevelopmentSettings,
  about: AboutSettings,
}

onUnmounted(() => {
  errorHandlerProvider.popHandler()
})
</script>

<style lang="scss" scoped>
.personal-settings {
  display: flex;
  align-items: flex-start;

  .personal-settings-content {
    flex: 1 1 auto;
    min-width: 0;

    // Leave room for the app-navigation toggle which is rendered by the server.
    @media only screen and (width >= 1024px) {
      :deep(.settings-section) {
        margin-left: calc(var(--default-grid-baseline) * 10);
        width: min(900px, 100% - var(--default-grid-baseline) * 17);
      }
    }
  }
}
</style>
