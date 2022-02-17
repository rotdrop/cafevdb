<script>
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license GNU AGPL version 3 or any later version
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */
</script>
<template>
  <SettingsSection :title="t(appName, 'Camerata DB')">
    <div>
      <SettingsSelectGroup
        v-model="orchestraUserGroup"
        :label="t(appName, 'User Group')"
        :hint="t(appName, 'Access to the app is restricted to this group, only group-admins of this group are granted access to basic configuration options for the app.')"
        :multiple="false"
        @update="saveTextInput(...arguments, 'orchestraUserGroup')"
      />
    </div>
    <SettingsInputText
      v-model="wikiNameSpace"
      :label="t(appName, 'Wiki Name-Space')"
      :hint="t(appName, 'The name-space in the orchestra-wiki below which all documentation and notes will be placed.')"
      @update="saveTextInput(...arguments, 'wikiNameSpace')"
    />
    <button type="button"
            @click="autoconfigureUserBackend"
    >
      {{ t(appName, 'Autoconfigure "{userBackend}" app', { userBackend: 'user_sql' }) }}
    </button>
    <p class="hint">
      {{ t(appName, 'Configure the user backend which exports the orchestra members to the cloud as cloud-user-accounts.') }}
    </p>
  </SettingsSection>
</template>
<script>
 import { appName } from '../app/config.js'
 import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
 import SettingsInputText from './SettingsInputText'
 import SettingsSelectGroup from './SettingsSelectGroup'
 import { showError, /* showSuccess, showInfo, */ TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
 import escapeHtml from 'escape-html'
 export default {
   name: 'AdminSettings',
   components: {
     SettingsSection,
     SettingsInputText,
     SettingsSelectGroup,
   },
   props: {
     initial: {
       type: Object,
       required: true,
     }
   },
   data() {
     return {
       orchestraUserGroup: '',
       wikiNameSpace: '',
     }
   },
   created() {
     this.getData()
   },
   methods: {
     async getData() {},
     async saveTextInput(value, settingsKey, force) {
       showError(t(appName, 'UNIMPLEMENTED'), { timeout: TOAST_PERMANENT_TIMEOUT, });
     },
     async autoconfigureUserBackend() {
       showError(t(appName, 'UNIMPLEMENTED'), { timeout: TOAST_PERMANENT_TIMEOUT, });
     }
   },
 }
</script>
<style lang="scss" scoped>
 .settings-section {
   ::v-deep &__title {
     background-image:url('../../img/logo-greyf-large.svg');
     background-repeat:no-repeat;
     background-origin:padding-box;
     background-size:contain;
     padding-left:45px;
   }
 }
</style>
