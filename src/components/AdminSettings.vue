<script>
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
</script>
<template>
  <div class="templateroot">
    <SettingsSection class="major" :title="t(appName, 'Camerata DB')">
      <div v-if="config.isAdmin">
        <!-- eslint-disable-next-line vue/no-v-html -->
        <p class="info" v-html="forword">
          {{ forword }}
        </p>
        <hr>
      </div>
      <div v-if="config.isAdmin">
        <SettingsSelectGroup v-model="settings.orchestraUserGroup"
                             :label="t(appName, 'User Group')"
                             :hint="hints['settings:admin:user-group']"
                             :multiple="false"
                             @update="saveSetting('orchestraUserGroup', ...arguments)"
        />
        <SettingsSelectUsers v-model="settings.orchestraUserGroupAdmins"
                             :label="t(appName, 'User Group Admins')"
                             :hint="hints['settings:admin:user-group:admins']"
                             :disabled="groupAdminsDisabled"
                             @update="saveSetting('orchestraUserGroupAdmins', ...arguments)"
        />
      </div>
      <SettingsInputText v-if="config.isAdmin"
                         v-model="settings.wikiNameSpace"
                         :label="t(appName, 'Wiki Name-Space')"
                         :hint="hints['settings:admin:wiki-name-space']"
                         @update="saveSetting('wikiNameSpace', ...arguments)"
      />
      <div>
        <button type="button"
                name="cloudUserBackendConfig"
                value="update"
                :disabled="!config.cloudUserBackendConfig"
                @click="saveSetting('cloudUserBackendConfig')"
        >
          {{ t(appName, 'Autoconfigure "{cloudUserBackend}" app', { cloudUserBackend: config.cloudUserBackend }) }}
        </button>
        <p class="hint">
          {{ hints['settings:admin:cloud-user-backend-conf'] }}
        </p>
      </div>
    </SettingsSection>
    <SettingsSection v-if="config.isSubAdmin" class="sub-admin" :title="t(appName, 'Recryption Requests')">
      <div v-for="(request, userId) in recryption.requests" :key="request.id" class="recryption-request-container">
        <input :id="['mark',userId].join('-')"
               v-model="recryption.requests[userId].marked"
               type="checkbox"
               class="checkbox request-mark"
               @change="markRecryptionRequest(userId, ...arguments)"
        >
        <label :for="['mark', userId].join('-')" />
        <Actions>
          <ActionButton icon="icon-confirm" @click="handleRecryptionRequest(userId, ...arguments)">
            {{ t(appName, 'recrypt') }}
          </ActionButton>
          <ActionButton icon="icon-delete" @click="deleteRecryptionRequest(userId, ...arguments)">
            {{ t(appName, 'reject') }}
          </ActionButton>
        </Actions>
        <div :class="'recryption-request-data' + (request.marked ? ' marked' : '')">
          <span class="display-name" :title="userId">{{ request.displayName }}</span>
          <span :class="'user-tag' + ' ' + 'organizer' + ' ' + (request.isOrganizer ? 'set' : 'unset')">{{ t(appName, 'organizer') }}</span>
          <span :class="'user-tag' + ' ' + 'group-admin' + ' ' + (request.isGroupAdmin ? 'set' : 'unset')">{{ t(appName, 'group-admin') }}</span>
        </div>
      </div>
      <div v-if="Object.keys(recryption.requests).length > 0" class="bulk-operations">
        <input id="mark-all"
               v-model="recryption.allRequestsMarked"
               type="checkbox"
               class="checkbox request-mark"
               @change="markAllRecryptionRequests(...arguments)"
        >
        <label for="mark-all">{{ t(appName, 'Mark/unmark all.') }}</label>
        <span class="bulk-operation-title">{{ t(appName, 'With the marked requests perform the following action:') }}</span>
        <Actions>
          <ActionButton icon="icon-confirm" @click="handleMarkedRecrytpionRequests">
            {{ t(appName, 'recrypt') }}
          </ActionButton>
          <ActionButton icon="icon-delete" @click="deleteMarkedRecryptionRequests">
            {{ t(appName, 'reject') }}
          </ActionButton>
        </Actions>
      </div>
      <div v-else>
        <span class="hint">{{ t(appName, 'No recryption requests are pending.') }}</span>
      </div>
    </SettingsSection>
  </div>
</template>
<script>
 import Vue from 'vue'
 import { appName } from '../app/app-info.js'
 import Actions from '@nextcloud/vue/dist/Components/Actions'
 import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'
 import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
 import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
 import SettingsInputText from './SettingsInputText'
 import SettingsSelectGroup from './SettingsSelectGroup'
 import SettingsSelectUsers from './SettingsSelectUsers'
 import { showError, showSuccess, showInfo, TOAST_DEFAULT_TIMEOUT, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
 import axios from '@nextcloud/axios'
 import { generateUrl, generateOcsUrl } from '@nextcloud/router'
 export default {
   name: 'AdminSettings',
   components: {
     SettingsSection,
     SettingsInputText,
     SettingsSelectGroup,
     SettingsSelectUsers,
     Actions,
     ActionButton,
     ActionCheckbox,
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
         orchestraUserGroupAdmins: [],
         wikiNameSpace: '',
         cloudUserBackendConfig: '',
       },
       hints: {
         'settings:admin:cloud-user-backend-conf': '',
         'settings:admin:wiki-name-space': '',
         'settings:admin:user-group': '',
       },
       forword: '',
       recryption: {
         requests: {},
         allRequestsMarked: ''
       },
     }
   },
   created() {
     this.getData()
   },
   computed: {
     groupAdminsDisabled() {
       return this.settings.orchestraUserGroup == ''
     },
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
       {
         const response = await axios.get(generateOcsUrl('apps/cafevdb/api/v1/maintenance/encryption/recrypt'))
         Vue.set(this.recryption, 'requests', {})
         Vue.set(this.recryption, 'allRequestsMarked', '')
         if (Object.keys(response.data.ocs.data.requests).length > 0) {
           const recryptionRequests = response.data.ocs.data.requests
           for (const [userId, publicKey] of Object.entries(recryptionRequests)) {
             try {
               const response = await axios.get(generateOcsUrl('cloud/users/{userId}', { userId }))
               const user = response.data.ocs.data
               const isOrganizer = user.groups.indexOf(this.settings.orchestraUserGroup) >= 0
               const isGroupAdmin = this.settings.orchestraUserGroupAdmins.indexOf(userId) >= 0
               Vue.set(this.recryption.requests, userId, {
                 id: userId,
                 publicKey,
                 displayName: user.displayname,
                 groups: user.groups,
                 enabled: user.enabled,
                 isOrganizer,
                 isGroupAdmin,
                 marked: '',
               })
             } catch (e) {
               console.error('Unable to fetch data for user ' + userId, e)
             }
           }
         }
       }
       console.info('RECRYPTION', this.recryption)
     },
     async saveSetting(settingsKey, value, force) {
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
                 self.saveSetting(settingsKey, value, true)
               } else {
                 showInfo(t(appName, 'Unconfirmed, reverting to old value.'))
                 self.getData()
               }
             },
             true)
         } else {
           const messages = responseData.messages || {}
           const transient = messages.transient || []
           const permanent = messages.permanent || []
           if (permanent.length === 0 && transient.length === 0) {
             if (Array.isArray(value)) {
               value = value.join(', ')
             }
             transient.push(t(appName, 'Successfully set value for {settingsKey} to {value}', { settingsKey, value }))
           }
           for (const message of transient) {
             showInfo(message, { timeout: TOAST_DEFAULT_TIMEOUT, isHTML: true })
           }
           for (const message of permanent) {
             showInfo(message, { timeout: TOAST_PERMANENT_TIMEOUT, isHTML: true })
           }
         }
       } catch (e) {
         let message = t(appName, 'reason unknown')
         if (e.response && e.response.data && e.response.data.message) {
           message = e.response.data.message
           console.error('RESPONSE', e.response)
         }
         if (value !== undefined) {
           if (Array.isArray(value)) {
             value = value.join(', ')
           }
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
         console.debug('GOT TOOLTIP', response.data.tooltip || '')
         return response.data.tooltip
       } catch (e) {
         console.error('ERROR FETCHING TOOLTIP ' + key, e)
         return ''
       }
     },
     markAllRecryptionRequests(event) {
       const value = !!this.recryption.allRequestsMarked
       for (const request of Object.values(this.recryption.requests)) {
         request.marked = value
       }
     },
     markRecryptionRequest(userId, event) {
       const allRequests = Object.values(this.recryption.requests)
       const marked = allRequests.filter(request => request.marked)
       if (marked.length === allRequests.length) {
         this.recryption.allRequestsMarked = true
       } else {
         this.recryption.allRequestsMarked = false
       }
     },
     async handleRecryptionRequest(userId) {
       try {
         const response = await axios.post(
           generateOcsUrl('apps/cafevdb/api/v1/maintenance/encryption/recrypt/{userId}', {
             userId
         }))
         showInfo(t(appName, 'Successfully handled recryption request for {userId}.', { userId }))
         Vue.delete(this.recryption.requests, userId)
       } catch (e) {
         if (e.response) {
           console.error('RESPONSE', e.response)
         }
         let message = t(appName, 'reason unknown')
         if (e.response && e.response.data && e.response.data.ocs) {
           message = e.response.data.ocs.meta.message
                   + ' ('
                   + e.response.data.ocs.meta.statuscode
                   + ', ' + e.response.data.ocs.meta.status
                   + ')'
         }
         showError(t(appName, 'Could not resolve the recryption request for {userId}: {message}', { userId, message }), { timeout: TOAST_PERMANENT_TIMEOUT })
         this.getData()
       }
     },
     async deleteRecryptionRequest(userId) {
       try {
         const response = await axios.delete(
           generateOcsUrl('apps/cafevdb/api/v1/maintenance/encryption/recrypt/{userId}', {
             userId
         }))
         showInfo(t(appName, 'Successfully deleted recryption request for {userId}.', { userId }))
         Vue.delete(this.recryption.requests, userId)
       } catch (e) {
         if (e.response) {
           console.error('RESPONSE', e.response)
         }
         let message = t(appName, 'reason unknown')
         if (e.response && e.response.data && e.response.data.ocs) {
           message = e.response.data.ocs.meta.message
                   + ' ('
                   + e.response.data.ocs.meta.statuscode
                   + ', ' + e.response.data.ocs.meta.status
                   + ')'
         }
         showError(t(appName, 'Could not delete the recryption request for {userId}: {message}', { userId, message }), { timeout: TOAST_PERMANENT_TIMEOUT })
         this.getData()
       }
     },
     async handleMarkedRecrytpionRequests() {
       const allRequests = Object.values(this.recryption.requests)
       const marked = allRequests.filter(request => request.marked)
       for (const request of marked) {
         this.handleRecryptionRequest(request.id)
       }
     },
     async deleteMarkedRecryptionRequests() {
       const allRequests = Object.values(this.recryption.requests)
       const marked = allRequests.filter(request => request.marked)
       for (const request of marked) {
         this.deleteRecryptionRequest(request.id)
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
   &.major ::v-deep &__title {
     background-image:url('../../img/logo-greyf-large.svg');
     background-repeat:no-repeat;
     background-origin:padding-box;
     background-size:contain;
     padding-left:45px;
   }
   &.sub-admin ::v-deep .recryption-request-container {
     display:flex;
     align-items: center;
     width:100%;
     .recryption-request-data {
       display:inline-block;
       .user-tag {
         &.unset {
           display:none;
         }
         &.group-admin {
           color: red;
         }
         &.organizer {
           color: green;
         }
       }
     }
     .checkbox.request-mark + label {
       display:inline-block;
     }
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
