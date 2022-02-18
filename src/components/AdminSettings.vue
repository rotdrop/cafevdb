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
  <div class="templateroot">
    <SettingsSection class="major" :title="t(appName, 'Camerata DB')">
      <div v-if="config.isAdmin">
        <p class="info" v-html="forword">
          {{ forword }}
        </p>
        <hr/>
      </div>
      <div v-if="config.isAdmin">
        <SettingsSelectGroup
          v-model="settings.orchestraUserGroup"
          :label="t(appName, 'User Group')"
          :hint="hints['settings:admin:user-group']"
          :multiple="false"
          @update="saveSetting(...arguments, 'orchestraUserGroup')"
        />
      </div>
      <SettingsInputText
        v-if="config.isAdmin"
        v-model="settings.wikiNameSpace"
        :label="t(appName, 'Wiki Name-Space')"
        :hint="hints['settings:admin:wiki-name-space']"
        @update="saveSetting(...arguments, 'wikiNameSpace')"
      />
      <div>
        <button type="button"
                name="cloudUserBackendConfig"
                value="update"
                @click="saveSetting(undefined, 'cloudUserBackendConfig')"
                :disabled="!config.cloudUserBackendConfig"
        >
          {{ t(appName, 'Autoconfigure "{cloudUserBackend}" app', { cloudUserBackend: config.cloudUserBackend }) }}
        </button>
        <p class="hint">
          {{ hints['settings:admin:cloud-user-backend-conf'] }}
        </p>
      </div>
    </SettingsSection>
    <SettingsSection v-if="config.isSubAdmin" :title="t(appName, 'Recryption Requests')">
      <p class="info">Hello</p>
    </SettingsSection>
  </div>
</template>
<script>
 import { appName } from '../app/app-info.js'
 import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
 import SettingsInputText from './SettingsInputText'
 import SettingsSelectGroup from './SettingsSelectGroup'
 import { showError, showSuccess, showInfo, TOAST_DEFAULT_TIMEOUT, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
 import axios from '@nextcloud/axios'
 import { generateUrl } from '@nextcloud/router'
 export default {
   name: 'AdminSettings',
   components: {
     SettingsSection,
     SettingsInputText,
     SettingsSelectGroup,
   },
   props: {
     config: {
       type: Object,
       required: true,
     }
   },
   data() {
     return {
       settings: {
         orchestraUserGroup: '',
         wikiNameSpace: '',
         cloudUserBackendConfig: '',
       },
       hints: {
         'settings:admin:cloud-user-backend-conf': '',
         'settings:admin:wiki-name-space': '',
         'settings:admin:user-group': '',
       },
       forword: '',
     }
   },
   created() {
     this.getData()
   },
   methods: {
     async getData() {
       for (const [key, value] of Object.entries(this.settings)) {
         const response = await axios.get(generateUrl('apps/' + appName + '/settings/admin/{key}', { key }));
         this.settings[key] = response.data.value;
       }
       for (const [key, value] of Object.entries(this.hints)) {
         this.hints[key] = await this.tooltip(key);
       }
       const personalSettingsLink = '<a class="external settings" href="' + this.config.personalAppSettingsLink + '">' + appName + '</a>'
       this.forword = t(
         appName,
         'Further detailed configurations are necessary after configuring the user-group. Please configure a dedicated group-admin for the user-group and then log-in as this group-admin and head over to the {personalSettingsLink} settings.', {
           personalSettingsLink
         }, undefined, { escape: false });
       // curl -u $(cat ./APITEST-TOKEN) -X GET 'https://anaxagoras.home.claus-justus-heine.de/nextcloud-git/ocs/v2.php/apps/cafevdb/api/v1/maintenance/encryption/recrypt' -H "OCS-APIRequest: true"
       // fetch recryption requests
     },
     async saveSetting(value, settingsKey, force) {
       console.info('ARGS', arguments)
       const self = this
       try {
         const response = await axios.post(generateUrl('apps/' + appName + '/settings/admin/{settingsKey}', { settingsKey }), { value })
         const responseData = response.data
         if (responseData.status === 'unconfirmed') {
           OC.dialogs.confirm(
             responseData.feedback,
             t(appName, 'Confirmation Required'),
             function(answer) {
               if (answer) {
                 self.saveTextInput(value, settingsKey, true);
               } else {
                 showInfo(t(appName, 'Unconfirmed, reverting to old value.'))
                 self.getData()
               }
             },
             true)
         } else {
           const messages = responseData.messages || {};
           const transient = messages.transient || [];
           const permanent = messages.permanent || [];
           if (permanent.length === 0 && transient.length === null) {
             transient.push(t(appName, 'Successfully set value for {settingsKey} to {value}', { settingsKey, value }));
           }
           for (const message of transient) {
             showInfo(message, { timeout: TOAST_DEFAULT_TIMEOUT, isHTML: true });
           }
           for (const message of permanent) {
             showInfo(message, { timeout: TOAST_PERMANENT_TIMEOUT, isHTML: true });
           }
         }
       } catch (e) {
         let message = t(appName, 'reason unknown')
         if (e.response && e.response.data && e.response.data.message) {
           message = e.response.data.message
           console.error('RESPONSE', e.response)
         }
         if (value !== undefined) {
           showError(t(appName, 'Could not set "{settingsKey}" to "{value}": {message}', { settingsKey, value, message }), { timeout: TOAST_PERMANENT_TIMEOUT })
         } else {
           showError(t(appName, 'Could not set "{settingsKey}": {message}', { settingsKey, message }), { timeout: TOAST_PERMANENT_TIMEOUT })
         }
         self.getData()
       }
     },
     async tooltip(key) {
       try {
         const response = await axios.get(generateUrl('apps/' + appName + '/tooltips/{key}', { key }), { params: { unescaped: true } })
         console.debug('GOT TOOLTIP', response.data.tooltip || '');
         return response.data.tooltip;
       } catch (e) {
         console.error('ERROR FETCHING TOOLTIP ' + key, e);
         return '';
       }
     },
   },
 }
</script>
<style lang="scss" scoped>
 .settings-section {
   ::v-deep hr {
     opacity: 0.2;
   }
   ::v-deep a.external.settings {
     background-image:url('../../../../core/img/actions/settings.svg');
     background-repeat:no-repeat;
     background-position:right center;
     background-size:16px 16px;
     padding-right:20px;
   }
   &.major::v-deep &__title {
     background-image:url('../../img/logo-greyf-large.svg');
     background-repeat:no-repeat;
     background-origin:padding-box;
     background-size:contain;
     padding-left:45px;
   }
 }
</style>
<style lang="scss">
 .toastify.dialogs {
   a.external.settings {
     background-image:url('../../../../core/img/actions/settings.svg');
     background-repeat:no-repeat;
     background-position:right center;
     background-size:16px 16px;
     padding-right:20px;
   }
 }
</style>
