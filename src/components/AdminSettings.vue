<!--
 - Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2011-2016, 2020, 2021, 2022, 2023, 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  <div :class="['templateroot', ...cloudVersionClasses]">
    <NcSettingsSection :class="['major']"
                       :name="t(appName, 'Camerata DB')"
    >
      <div v-if="config.isAdmin">
        <!-- eslint-disable-next-line vue/no-v-html -->
        <p class="info" v-html="forword" />
        <hr>
      </div>
      <div>
        <SelectWithSubmitButton v-model="settings.userAndGroupBackend"
                                intput-id="user-and-group-backend-select"
                                :input-label="t(appName, 'User and group backend')"
                                :hint="hints['settings:admin:user-and-group-backend']"
                                :required="true"
                                :clearable="false"
                                :options="config.userAndGroupBackends"
                                :multiple="false"
                                :loading="loading.settings"
                                :disabled="loading.general || !config.isAdmin"
                                @update="saveSetting('userAndGroupBackend')"
                                @error="showErrorToast"
        >
          <template #actions>
            <NcActionButton v-tooltip="hints['settings:admin:user-backend:move-users']"
                            icon="icon-play"
                            @click="synchronizeUserBackends"
            >
              {{ t(appName, 'Sychronize User Backends') }}
            </NcActionButton>
          </template>(
        </SelectWithSubmitButton>
      </div>
      <div v-if="config.isSubAdmin || config.isAdmin">
        <SettingsSelectGroup v-model="settings.orchestraUserGroup"
                             :label="t(appName, 'User Group')"
                             :hint="hints['settings:admin:user-group']"
                             :multiple="false"
                             :required="true"
                             :loading="loading.settings"
                             :disabled="loading.general || !config.isAdmin"
                             @update="saveSetting('orchestraUserGroup', ...arguments)"
                             @error="showErrorToast"
        />
        <SettingsSelectUsers v-model="settings.orchestraUserGroupAdmins"
                             :label="t(appName, 'User Group Admins')"
                             :hint="hints['settings:admin:user-group:admins']"
                             :loading="loading.settings"
                             :disabled="loading.general || groupAdminsDisabled"
                             :required="true"
                             @input="info(...arguments)"
                             @update="saveSetting('orchestraUserGroupAdmins', ...arguments)"
                             @error="showErrorToast"
        />
      </div>
      <div v-if="settings.orchestraUserGroup && (config.isSubAdmin || config.isAdmin)">
        <ul class="orchestra-groups">
          <NcListItem v-for="(group, gid) in orchestraGroups"
                      :key="gid"
                      :name="group.displayname + (gid !== group.displayname ? ' (' + gid + ')' : '')"
                      :bold="true"
                      :force-display-actions="true"
                      :details="group.backends.join(', ')"
                      :counter-number="group.usercount"
                      counter-type="highlighted"
          >
            <template #icon>
              <GroupIcon :size="24" />
            </template>
            <template #subname>
              {{ group.users.join(', ') }}
            </template>
            <template #indicator>
              <CheckboxBlankCircle v-if="group.status === 'inaccessible'" :size="16" fill-color="red" />
              <CheckboxBlankCircle v-else-if="group.disabled" :size="16" fill-color="yellow" />
              <CheckboxBlankCircle v-else-if="group.backends.indexOf(settings.userAndGroupBackend) == -1" :size="16" fill-color="purple" />
              <CheckboxBlankCircle v-else :size="16" fill-color="green" />
            </template>
            <template #actions>
              <NcActionButton :disabled="group.status !== 'inaccessible' || group.backends.length > 0"
                              @click="createGroup(group.id)"
              >
                Create Group
              </NcActionButton>
              <NcActionButton>
                Button two
              </NcActionButton>
              <NcActionButton>
                Button three
              </NcActionButton>
            </template>
          </NcListItem>
        </ul>
      </div>
      <label for="wiki-name-space">
        {{ t(appName, 'Wiki Name-Space') }}
      </label>
      <!-- Note: v-model does not work here -->
      <NcTextField v-if="config.isSubAdmin || config.isAdmin"
                   id="wiki-name-space"
                   :value.sync="settings.wikiNameSpace"
                   type="text"
                   :label="t(appName, 'Wiki Name-Space')"
                   :show-trailing-button="true"
                   trailing-button-icon="arrowRight"
                   @trailing-button-click="saveSetting('wikiNameSpace', settings.wikiNameSpace)"
                   @update="info(settings.wikiNameSpace, ...arguments)"
                   @update:value="info(settings.wikiNameSpace, ...arguments)"
      />
      <!-- @trailing-button-click="saveSetting('wikiNameSpace', ...arguments)" -->
      <p class="hint">
        {{ hints['settings:admin:wiki-name-space'] }}
      </p>
    </NcSettingsSection>
    <NcSettingsSection v-if="config.isSubAdmin"
                       :name="t(appName, 'Configure User Backend')"
    >
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
    </NcSettingsSection>
    <NcSettingsSection v-if="config.isSubAdmin"
                       :class="['sub-admin', { 'icon-loading': loading.recryption }]"
                       :name="t(appName, 'Recryption Requests')"
    >
      <div v-for="(request, userId) in recryption.requests" :key="request.id" class="recryption-request-container">
        <input :id="['mark',userId].join('-')"
               v-model="recryption.requests[userId].marked"
               type="checkbox"
               class="checkbox request-mark"
               @change="markRecryptionRequest(userId, ...arguments)"
        >
        <label :for="['mark', userId].join('-')" />
        <NcActions>
          <NcActionButton icon="icon-confirm" @click="handleRecryptionRequest(userId, ...arguments)">
            {{ t(appName, 'recrypt') }}
          </NcActionButton>
          <NcActionButton icon="icon-delete" @click="deleteRecryptionRequest(userId, ...arguments)">
            {{ t(appName, 'reject') }}
          </NcActionButton>
        </NcActions>
        <div :class="['recryption-request-data', { marked: request.marked }, 'flex-container', 'flex-justify-left', 'flex-align-center']">
          <span class="first visible display-name" :title="userId">{{ request.displayName }}</span>
          <span class="following visible time-stamp">{{ formatDate(request.timeStamp, 'LLL') }}</span>
          <span :class="['following', 'user-tag', 'organizer', { visible: request.isOrganizer, invisible: !request.isOrganizer }]">{{ t(appName, 'organizer') }}</span>
          <span :class="['following', 'user-tag', 'group-admin', { visible: request.isGroupAdmin, invisible: !request.isGroupadmin }]">{{ t(appName, 'group-admin') }}</span>
        </div>
      </div>
      <div v-if="Object.keys(recryption.requests).length > 0" class="bulk-operations flex-container flex-align-center">
        <input id="mark-all"
               v-model="recryption.allRequestsMarked"
               type="checkbox"
               class="checkbox request-mark"
               @change="markAllRecryptionRequests(...arguments)"
        >
        <label class="bulk-operation-mark" for="mark-all">{{ t(appName, 'mark/unmark all.') }}</label>
        <span class="bulk-operation-title">{{ t(appName, 'With the marked requests perform the following action:') }}</span>
        <NcActions>
          <NcActionButton icon="icon-confirm" @click="handleMarkedRecrytpionRequests">
            {{ t(appName, 'recrypt') }}
          </NcActionButton>
          <NcActionButton icon="icon-delete" @click="deleteMarkedRecryptionRequests">
            {{ t(appName, 'reject') }}
          </NcActionButton>
        </NcActions>
      </div>
      <div v-else>
        <span class="hint">{{ t(appName, 'No recryption requests are pending.') }}</span>
      </div>
    </NcSettingsSection>
    <NcSettingsSection v-if="config.isSubAdmin"
                       class="sub-admin"
                       :name="t(appName, 'Access Control')"
    >
      <SelectMusicians v-model="access.musicians"
                       :tooltip="access.musicians.length ? false : hints['settings:admin:access-control:musicians']"
                       :label="t(appName, 'Musicians')"
                       :placeholder="t(appName, 'e.g. Jane Doe')"
                       :multiple="true"
                       :deselect-from-dropdown="true"
                       :close-on-select="false"
                       :submit-button="false"
                       :clear-button="true"
                       :project-id="projectId"
                       search-scope="musicians"
      />
      <SelectProjects v-model="access.project"
                      :tooltip="hints['settings:admin:access-control:project-restriction']"
                      :label="t(appName, 'Restrict User Selection to Project')"
                      :placeholder="t(appName, 'e.g. Auvergne2019')"
                      :multiple="false"
                      :submit-button="false"
                      @update="info('Projects Update', ...arguments)"
      />
      <input id="include-disabled"
             v-model="access.includeDeactivated"
             type="checkbox"
             class="checkbox access-flags"
             :disabled="!applyAccessToAll"
      >
      <label for="include-disabled" class="access-flags checkbox-label">
        {{ t(appName, 'include disabled accounts') }}
      </label>
      <input id="include-deactivated"
             v-model="access.includeDisabled"
             type="checkbox"
             class="checkbox access-flags"
             :disabled="!applyAccessToAll"
      >
      <label for="include-deactivated" class="access-flags checkbox-label">
        {{ t(appName, 'include deactivated accounts') }}
      </label>
      <span v-if="showAccessActionProgress">
        <div class="access-action-status">
          <span class="access-action-text">{{ accessActionLabel }}</span>
          <button v-if="accessActionFinished"
                  class="button primary access-action-clear"
                  :title="t(appName, 'Remove the status feedback from the last action.')"
                  @click="hideAccessActionFeedback()"
          >
            {{ t(appName, 'Ok') }}
          </button>
          <span class="flex-spacer" />
          <span class="access-action-counter">{{ accessActionCounter }}</span>
        </div>
        <NcProgressBar :value="accessActionPercentage"
                       :error="accessActionError"
                       size="medium"
        />
      </span>
      <span v-else class="flex-container flex-align-center flex-justify-start">
        <span class="bulk-operation-title">{{ t(appName, 'With the selected musicians perform the following action:') }}</span>
        <NcActions>
          <NcActionButton icon="icon-disabled-user" @click="handleAccessAction('deny')">
            {{ t(appName, 'deny access') }}
          </NcActionButton>
          <NcActionButton icon="icon-confirm" @click="handleAccessAction('grant')">
            {{ t(appName, 'grant access') }}
          </NcActionButton>
        </NcActions>
      </span>
    </NcSettingsSection>
    <NcSettingsSection v-if="config.isSubAdmin"
                       :class="['sub-admin', 'fonts-container']"
                       :name="t(appName, 'Configure Office Fonts for Office Exports')"
    >
      <div>
        <span class="file-name-label">{{ t(appName, 'Font Data Folder') }}</span>
        <span class="file-name">{{ humanOfficeFontsFolder }}</span>
      </div>
      <SelectWithSubmitButton v-model="defaultOfficeFont"
                              input-id="default-font"
                              :label-outside="true"
                              :options="Object.values(config.officeFonts)"
                              :loading="loading.fonts"
                              label="family"
                              :clearable="false"
                              :multiple="false"
                              :disabled="loading.general || loading.fonts"
                              :submit-button="true"
                              :clear-action="false"
                              :reset-action="false"
                              :reset-state="savedDefaultOfficeFont"
                              @update="saveSetting('defaultOfficeFont')"
                              @error="showErrorToast"
      >
        <template #alignedBefore>
          <label for="default-font" class="default-font">
            {{ t(appName, 'Default Font') }}
          </label>
        </template>
        <template #actions>
          <NcActionButton icon="icon-add"
                          @click="updateFontData"
          >
            {{ t(appName, 'Update Font Data') }}
          </NcActionButton>
          <NcActionButton icon="icon-play"
                          @click="rescanFontData"
          >
            {{ t(appName, 'Rescan Font Data') }}
          </NcActionButton>
          <NcActionButton icon="icon-delete"
                          @click="purgeFontData"
          >
            {{ t(appName, 'Purge Font Data') }}
          </NcActionButton>
        </template>
      </SelectWithSubmitButton>
    </NcSettingsSection>
  </div>
</template>
<script>
import { set as vueSet, del as vueDelete, nextTick as vueNextTick } from 'vue'

import {
  NcActions,
  NcActionButton,
  NcProgressBar,
  NcSettingsSection,
  NcTextField,
  NcListItem,
} from '@nextcloud/vue'

import CheckboxBlankCircle from 'vue-material-design-icons/CheckboxBlankCircle.vue'
import GroupIcon from 'vue-material-design-icons/AccountGroup.vue'

import axios from '@nextcloud/axios'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import { showError, showInfo, TOAST_DEFAULT_TIMEOUT, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'

import { appName } from '../app/app-info.js'
import cloudVersionClasses from '../toolkit/util/cloud-version-classes.js'

import SelectMusicians from './SelectMusicians.vue'

import SelectProjects from './SelectProjects.vue'

import SettingsSelectGroup from './SettingsSelectGroup.vue'
import SettingsSelectUsers from './SettingsSelectUsers.vue'
import SelectWithSubmitButton from './SelectWithSubmitButton.vue'
import tooltip from '../mixins/tooltips.js'
import formatDate from '../mixins/formatDate.js'

import { useCloudUsersGroupsStore } from '../stores/cloud-users-groups.js'

const initialState = loadState(appName, 'adminConfig')

export default {
  name: 'AdminSettings',
  components: {
    NcActions,
    NcActionButton,
    NcProgressBar,
    SelectMusicians,
    SelectProjects,
    SelectWithSubmitButton,
    NcSettingsSection,
    SettingsSelectGroup,
    SettingsSelectUsers,
    NcTextField,
    NcListItem,
    CheckboxBlankCircle,
    GroupIcon,
  },
  mixins: [
    tooltip,
    formatDate,
  ],
  setup() {
    const store = useCloudUsersGroupsStore()
    return { store }
  },
  data() {
    return {
      cloudVersionClasses,
      defaultOfficeFont: null,
      loading: {
        general: true,
        recryption: true,
        tooltips: true,
        fonts: true,
        settings: true,
        groups: true,
      },
      settings: {
        userAndGroupBackend: '',
        orchestraUserGroup: '',
        orchestraUserGroupAdmins: [],
        wikiNameSpace: '',
        cloudUserBackendConfig: '',
        defaultOfficeFont: '',
      },
      settingsBackup: {},
      orchestraGroups: {},
      config: initialState,
      hints: {
        'settings:admin:cloud-user-backend-conf': '',
        'settings:admin:wiki-name-space': '',
        'settings:admin:user-group': '',
        'settings:admin:user-group:admins': '',
        'settings:admin:user-and-group-backend': '',
        'settings:admin:access-control:musicians': '',
        'settings:admin:true-type-fonts-folder': '',
        'settings:admin:user-backend:move-users': '',
      },
      forword: '',
      recryption: {
        requests: {},
        allRequestsMarked: '',
      },
      recryptionPollTimer: null,
      recryptionPollTimeout: 10 * 1000,
      access: {
        musicians: [],
        project: '',
        includeDeactivated: false,
        includeDisabled: false,
        action: {
          failure: false,
          totals: 0,
          done: 0,
          active: false,
          label: '',
        },
      },
    }
  },
  computed: {
    humanOfficeFontsFolder() {
      return '.../' + (this.config.officeFontsFolder + '/').replace(/\/+/, '/').split('/').splice(-4).join('/')
    },
    orchestraUserGroup() {
      return this.settings.orchestraUserGroup
    },
    groupAdminsDisabled() {
      return this.settings.orchestraUserGroup === '' || !this.config.isAdmin
    },
    projectId() {
      try { return this.access.project.id } catch (ignoreMe) { return 0 }
    },
    applyAccessToAll() {
      return this.access.musicians.length === 1 && this.access.musicians[0].id <= 0
    },
    showAccessActionProgress() {
      return this.access.action.active
    },
    accessActionPercentage() {
      const totals = this.access.action.totals
      const done = this.access.action.done
      return totals > 0 ? done * 100.0 / totals : 0
    },
    accessActionTest() {
      return this.access.action.label
    },
    accessActionFinished() {
      const totals = this.access.action.totals
      const done = this.access.action.done
      return totals === 0 || (done > 0 && done >= totals) || this.access.action.failure
    },
    accessActionLabel() {
      return this.access.action.label
    },
    accessActionCounter() {
      const totals = this.access.action.totals
      const current = this.access.action.done
      return t(appName, '{current} of {totals}', { current, totals })
    },
    accessActionError() {
      return this.access.action.failure
    },
    isLoading() {
      return this.loading.general
        || this.loading.tooltips
        || this.loading.recryption
        || this.loading.fonts
        || this.loading.groups
    },
    savedDefaultOfficeFont() {
      const result = this.config.officeFonts?.[this.settingsBackup.defaultOfficeFont]
      return result
    },
  },
  watch: {
    defaultOfficeFont(newValue) {
      vueSet(this.settings, 'defaultOfficeFont', newValue?.family)
    },
  },
  created() {
    this.getData()
  },
  beforeDestroy() {
    this.clearTimeout(this.recryptionPollTimer)
    this.recryptionPollTimer = null
  },
  methods: {
    info(...args) {
      console.info(this.$options.name, ...args)
    },
    showErrorToast(message) {
      showError(message, { timeout: TOAST_DEFAULT_TIMEOUT })
    },
    async getData() {
      this.loading.general = true
      this.loading.recryption = true
      this.loading.fonts = true
      this.loading.settings = true
      this.loading.groups = true
      this.loadTooltips()
      if (this.config.isSubAdmin) {
        // fetch recryption requests
        this.getRecryptionRequests()
      }

      this.disableUnavailableFontOptions()

      await this.getSettingsData()

      this.loadOrchestraGroups()

      this.defaultOfficeFont = this.config.officeFonts[this.settings.defaultOfficeFont]
      await vueNextTick()
      this.loading.fonts = false
      this.loading.general = false
    },
    async loadOrchestraGroups() {
      if (!this.orchestraUserGroup || this.orchestraUserGroup === '') {
        return []
      }
      this.loading.groups = true
      const gids = Object.values(this.config.authorizationGroupSuffixes).map((suffix) => this.orchestraUserGroup + suffix).sort()
      for (const id of gids) {
        const group = await this.getGroup(id) || {}
        if (group.id) {
          group.l10nStatus = t(appName, group.status = 'accessible')
          if (!group.users) {
            await group.getUsers(this.errorHandler)
          }
        } else {
          group.id =
            group.displayname = id
          group.l10nStatus = t(appName, group.status = 'inaccessible')
          group.users = []
          group.backends = []
          console.info('GROUP INACCESSIBLE', group)
        }
        vueSet(this.orchestraGroups, id, group)
      }
      this.loading.groups = false
    },
    async loadTooltips() {
      this.loading.tooltips = true
      const personalSettingsLink = '<a class="external settings" href="' + this.config.personalAppSettingsLink + '">' + appName + '</a>'
      this.forword = t(
        appName,
        'Further detailed configurations are necessary after configuring the user-group. Please configure a dedicated group-admin for the user-group and then log-in as this group-admin and head over to the {personalSettingsLink} settings.', {
          personalSettingsLink,
        }, undefined, { escape: false })
      this.hints = await this.tooltips(Object.keys(this.hints))
      this.loading.tooltips = false
    },
    async getSettingsData() {
      this.loading.settings = true
      const requests = {}
      for (const key of Object.keys(this.settings)) {
        requests[key] = axios.get(generateUrl('apps/' + appName + '/settings/admin/{key}', { key }))
      }
      for (const [key, request] of Object.entries(requests)) {
        const response = await request
        vueSet(this.settings, key, response.data.value)
      }
      this.settingsBackup = { ...this.settings }
      this.loading.settings = false
    },
    async getRecryptionRequests() {
      this.loading.recryption = true
      vueSet(this.recryption, 'requests', {})
      vueSet(this.recryption, 'allRequestsMarked', '')
      await this.updateRecryptionRequests()
      this.recryptionPollTimer = setTimeout(() => this.pollRecryptionRequests(), this.recryptionPollTimeout)
    },
    async pollRecryptionRequests() {
      await this.updateRecryptionRequests()
      this.recryptionPollTimer = setTimeout(() => this.pollRecryptionRequests(), this.recryptionPollTimeout)
    },
    /**
     * Update the recryption requests if needed. It is assumed that
     * the time-stamp is a unique key, so if there is already a
     * recryption request for a user with the same time-stamp then it
     * is not replaced.
     */
    async updateRecryptionRequests() {
      try {
        const url = generateOcsUrl('apps/cafevdb/api/v1/maintenance/encryption/recrypt')
        const response = await axios.get(url + '?format=json')
        const recryptionRequests = response.data.ocs.data.requests
        // remove requests which are no longer there
        for (const userId of Object.keys(this.recryption.requests)) {
          if (!recryptionRequests[userId]) {
            vueDelete(this.recryption.requests, userId)
          }
        }
        // update existing requests (time-stamp changed) and add new
        // ones. Initiate the AJAX calls in parallel, then serialize
        // later
        const cloudUserPromises = []
        for (const [userId, timeStamp] of Object.entries(recryptionRequests)) {
          if (!this.recryption.requests[userId] || this.recryption.requests[userId].timeStamp !== timeStamp) {
            cloudUserPromises.push({
              userId,
              timeStamp,
              promise: axios.get(generateOcsUrl('cloud/users/{userId}', { userId })),
            })
          }
        }
        for (const cloudUserPromise of cloudUserPromises) {
          const { userId, timeStamp, promise } = cloudUserPromise
          try {
            const response = await promise
            const user = response.data.ocs.data
            const isOrganizer = user.groups.indexOf(this.settings.orchestraUserGroup) >= 0
            const isGroupAdmin = this.settings.orchestraUserGroupAdmins.indexOf(userId) >= 0
            vueSet(this.recryption.requests, userId, {
              id: userId,
              timeStamp,
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
      } catch (e) {
        // admin is maybe not authorized
        console.error('Unable to fetch recryption entries', e)
      }
      this.loading.recryption = false
    },
    async saveSetting(settingsKey, value) {
      try {
        if (value === undefined) {
          value = this.settings[settingsKey]
        } else {
          this.info('VALUE vs VMODEL', value, this.settings[settingsKey])
        }
        const response = await axios.post(generateUrl('apps/' + appName + '/settings/admin/{settingsKey}', { settingsKey }), { value })
        const responseData = response.data
        if (responseData.status === 'unconfirmed') {
          OC.dialogs.confirm(
            responseData.feedback,
            t(appName, 'Confirmation Required'),
            (answer) => {
              if (answer) {
                this.saveSetting(settingsKey, value, true)
              } else {
                showInfo(t(appName, 'Unconfirmed, reverting to old value.'))
                this.getSettingsData()
              }
            },
            true)
        } else {
          const messages = responseData.messages || {}
          const transient = messages.transient || []
          const permanent = messages.permanent || []
          if (responseData.value) {
            value = responseData.value
          }
          if (permanent.length === 0 && transient.length === 0) {
            if (Array.isArray(value)) {
              value = value.join(', ')
            }
            if (value) {
              transient.push(t(appName, 'Successfully set value for "{settingsKey}" to "{value}".', { settingsKey, value }))
            } else {
              transient.push(t(appName, 'Value for "{settingsKey}" has been erased.', { settingsKey }))
            }
          }
          for (const message of transient) {
            showInfo(message, { timeout: TOAST_DEFAULT_TIMEOUT, isHTML: true })
          }
          for (const message of permanent) {
            showInfo(message, { timeout: TOAST_PERMANENT_TIMEOUT, isHTML: true })
          }
          this.settingsBackup[settingsKey] = value
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
        this.getSettingsData()
      }
    },
    synchronizeUserBackends() {
      showError(t(appName, 'Synchronizing user backends not yet implemented.'), { timeout: TOAST_PERMANENT_TIMEOUT })
    },
    markAllRecryptionRequests(/* event */) {
      const value = !!this.recryption.allRequestsMarked
      for (const request of Object.values(this.recryption.requests)) {
        request.marked = value
      }
    },
    markRecryptionRequest(/* userId, event */) {
      const allRequests = Object.values(this.recryption.requests)
      const marked = allRequests.filter(request => request.marked)
      if (marked.length === allRequests.length) {
        this.recryption.allRequestsMarked = true
      } else {
        this.recryption.allRequestsMarked = false
      }
    },
    /**
     * @param {string} userId TBD.
     *
     * @param {boolean} silent TBD.
     *
     * @param {boolean} allowFailure TBD.
     *
     * @returns {Promise}
     */
    async doHandleRecryptionRequest(userId, silent, allowFailure) {
      const url = generateOcsUrl('apps/cafevdb/api/v1/maintenance/encryption/recrypt/{userId}', {
        userId,
      })
      return axios.post(url + '?format=json', {
        notifyUser: silent !== true,
        allowFailure,
      })
    },
    async handleRecryptionRequest(userId, silent) {
      this.awaitRecryptionRequestPromise(userId, this.doHandleRecryptionRequest(userId, silent))
    },
    async awaitRecryptionRequestPromise(userId, promise) {
      try {
        await promise
        showInfo(t(appName, 'Successfully handled recryption request for {userId}.', { userId }))
        vueDelete(this.recryption.requests, userId)
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
        this.getRecryptionRequests()
      }
    },
    async deleteRecryptionRequest(userId) {
      try {
        const url = generateOcsUrl('apps/cafevdb/api/v1/maintenance/encryption/recrypt/{userId}', {
          userId,
        })
        await axios.delete(url + '?format=json')
        showInfo(t(appName, 'Successfully deleted recryption request for {userId}.', { userId }))
        vueDelete(this.recryption.requests, userId)
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
        this.getRecryptionRequests()
      }
    },
    async doRevokeCloudAccess(userId/*, allowFailure */) {
      const url = generateOcsUrl(
        'apps/cafevdb/api/v1/maintenance/encryption/revoke/{userId}', {
          userId,
        },
      )
      return await axios.post(url + '?format=json')
    },
    async handleMarkedRecrytpionRequests() {
      const allRequests = Object.values(this.recryption.requests)
      const marked = allRequests.filter(request => request.marked)
      const recryptionPromises = {}
      for (const request of marked) {
        const userId = request.id
        recryptionPromises[userId] = this.doHandleRecryptionRequest(userId)
      }
      for (const [userId, promise] of Object.entries(recryptionPromises)) {
        this.awaitRecryptionRequestPromise(userId, promise)
      }
    },
    async deleteMarkedRecryptionRequests() {
      const allRequests = Object.values(this.recryption.requests)
      const marked = allRequests.filter(request => request.marked)
      for (const request of marked) {
        this.deleteRecryptionRequest(request.id)
      }
    },
    async handleAccessAction(action) {
      if (this.access.musicians.length === 0) {
        showError(t(appName, 'No musicians selected, doing nothing.'), { timeout: TOAST_DEFAULT_TIMEOUT })
      }
      if (this.access.musicians.length === 1 && this.access.musicians[0].id <= 0) {
        this.handleBulkAccessAction(action)
        return
      }
      this.access.action.active = true
      this.access.action.totals = this.access.musicians.length
      let failedUsers = 0
      try {
        for (const musician of this.access.musicians) {
          const response = action === 'grant'
            ? await this.doHandleRecryptionRequest(musician.userIdSlug, true, true)
            : await this.doRevokeCloudAccess(musician.userIdSlug, true)
          const ocsData = response.data.ocs.data
          const lastUser = ocsData.userId
          failedUsers += ocsData.status === 'failure'
          this.access.action.done++
          this.access.action.label = t(appName, 'Processed user-id {userId}.', { userId: lastUser })
          if (failedUsers > 0) {
            this.access.action.label += ' ' + t(appName, '{failedUsers} users have failed.', { failedUsers })
          }
        }
      } catch (e) {
        this.info('ERROR', e)
        let message = t(appName, 'reason unknown')
        if (e.response && e.response.data) {
          const data = e.response.data
          if (data.message) {
            message = data.message
          } else if (data.ocs && data.ocs.meta && data.ocs.meta.message) {
            message = data.ocs.meta.message
          }
        }
        showError(t(appName, 'Unable to handle access action: {message}', { message }), { timeout: TOAST_PERMANENT_TIMEOUT })
        this.access.action.failure = true
      }
      const numUsers = this.access.action.done - failedUsers
      const remainingUsers = this.access.action.totals - this.access.action.done

      if (this.access.action.failure) {
        this.access.action.label = t(appName, 'Failed after {numUsers} users have been processed successfully.', { numUsers })
        if (failedUsers > 0) {
          this.access.action.label += ' ' + t(appName, '{failedUsers} were processed unsuccessfully.', { failedUsers })
        }
        this.access.action.label += ' ' + t(appName, '{remainingUsers} remain unprocessed.', { remainingUsers })
      } else {
        this.access.action.label = t(appName, '{numUsers} users have been processed successfully.', { numUsers })
        if (failedUsers > 0) {
          this.access.action.label += ' ' + t(appName, '{failedUsers} were processed unsuccessfully.', { failedUsers })
        }
      }
    },
    async handleBulkAccessAction(action) {
      this.access.action.active = true
      let failedUsers = 0
      try {
        const url = generateOcsUrl('apps/cafevdb/api/v1/maintenance/encryption/bulk-recryption?format=json')
        const response = await axios.post(url, {
          grantAccess: action === 'grant',
          includeDisabled: this.access.includeDisabled,
          includeDeactivated: this.access.includeDeactivated,
          projectId: this.projectId,
          offset: 0,
          limit: 0,
        })
        this.access.action.totals = response.data.ocs.data.count
        const limit = this.access.action.totals > 100 ? this.access.action.totals / 100 : 1
        let count = 0
        let lastUser
        do {
          const url = generateOcsUrl('apps/cafevdb/api/v1/maintenance/encryption/bulk-recryption')
          const response = await axios.post(url + '?format=json', {
            grantAccess: action === 'grant',
            includeDisabled: this.access.includeDisabled,
            includeDeactivated: this.access.includeDeactivated,
            projectId: this.projectId,
            offset: this.access.action.done,
            limit,
          })
          const musicians = response.data.ocs.data
          failedUsers = musicians.reduce((failedUsers, musician) => failedUsers + (musician.status === 'failure'), failedUsers)
          lastUser = musicians.slice(-1).userId
          count = musicians.length
          this.access.action.done += count
          this.access.action.label = t(appName, 'Processed user-id {userId}.', { userId: lastUser })
          if (failedUsers > 0) {
            this.access.action.label += ' ' + t(appName, '{failedUsers} users have failed.', { failedUsers })
          }
          this.access.action.label += '.'
        } while (count > 0 && this.access.action.done < this.access.action.totals)
      } catch (e) {
        this.info('ERROR', e)
        let message = t(appName, 'reason unknown')
        if (e.response && e.response.data) {
          const data = e.response.data
          if (data.message) {
            message = data.message
          } else if (data.ocs && data.ocs.meta && data.ocs.meta.message) {
            message = data.ocs.meta.message
          }
        }
        showError(t(appName, 'Unable to handle access action: {message}', { message }), { timeout: TOAST_PERMANENT_TIMEOUT })
        this.access.action.failure = true
      }
      const numUsers = this.access.action.done - failedUsers
      const remainingUsers = this.access.action.totals - this.access.action.done

      if (this.access.action.failure) {
        this.access.action.label = t(appName, 'Failed after {numUsers} users have been processed successfully.', { numUsers })
        if (failedUsers > 0) {
          this.access.action.label += ' ' + t(appName, '{failedUsers} were processed unsuccessfully.', { failedUsers })
        }
        this.access.action.label += ' ' + t(appName, '{remainingUsers} remain unprocessed.', { remainingUsers })
      } else {
        this.access.action.label = t(appName, '{numUsers} users have been processed successfully.', { numUsers })
        if (failedUsers > 0) {
          this.access.action.label += ' ' + t(appName, '{failedUsers} were processed unsuccessfully.', { failedUsers })
        }
      }
    },
    hideAccessActionFeedback() {
      this.access.action.active = false
      this.access.action.failure = false
      this.access.action.done = 0
      this.access.action.totals = 0
    },
    async updateFontData() {
      return this.fontCacheOperaton('update')
    },
    async rescanFontData() {
      return this.fontCacheOperaton('rescan')
    },
    async purgeFontData() {
      return this.fontCacheOperaton('purge')
    },
    async fontCacheOperaton(operation) {
      this.loading.fonts = true
      try {
        const response = await axios.post(generateUrl('apps/' + appName + '/settings/admin/font-cache'), { operation })
        const responseData = response.data
        if (responseData.message) {
          showInfo(responseData.message)
        } else {
          showInfo(t(appName, 'Font cache operation {operation} completed successfully.', { operation }))
        }
        this.config.officeFonts = responseData.fonts
        this.settings.defaultOfficeFont = responseData.default
        this.defaultOfficeFont = this.config.officeFonts[this.settings.defaultOfficeFont]
        this.disableUnavailableFontOptions()
        this.info('FONT DATA', responseData)
      } catch (e) {
        this.info('ERROR', e)
        let message = t(appName, 'reason unknown')
        if (e.response && e.response.data && e.response.data.message) {
          message = e.response.data.message
          console.error('RESPONSE', e.response)
        }
        showError(t(appName, 'Could not perform the requested font-cache operation "{operation}": {message}', { operation, message }), { timeout: TOAST_PERMANENT_TIMEOUT })
      }
      this.loading.fonts = false
    },
    disableUnavailableFontOptions() {
      for (const [fontName, fontFiles] of Object.entries(this.config.officeFonts)) {
        if (fontFiles.x && fontFiles.xb && fontFiles.xi && fontFiles.xbi) {
          vueSet(this.config.officeFonts[fontName], 'disabled', false)
        } else {
          vueSet(this.config.officeFonts[fontName], 'disabled', true)
          vueSet(this.config.officeFonts[fontName], '$isDisabled', true)
          this.info('DISABLE FONT', fontName, this.config.officeFonts[fontName])
        }
      }
    },
    async getGroup(gid) {
      const result = await this.store.getGroup(gid, this.errorHandler)

      return result
    },
    async createGroup(gid) {
      const result = await this.store.createGroup(gid, gid, this.errorHandler)

      if (result && this.orchestraGroups[gid]) {
        if (!result.users) {
          await result.getUsers(this.errorHandler)
        }
        vueSet(this.orchestraGroups, gid, result)
      }

      return result
    },
    errorHandler(error) {
      this.$emit('error', error)
    },
  },
}
</script>
<style lang="scss" scoped>
.cloud-version {
  --cloud-icon-checkmark: var(--icon-checkmark-dark);
  &.cloud-version-major-24 {
    --cloud-icon-checkmark: var(--icon-checkmark-000);
  }
}
.settings-section {
  label.nc-select-outside-label {
    display: block;
     margin-bottom: 2px;
  }
  &::v-deep .flex-container {
    display: flex;
    &.flex- {
      &align- {
        &center {
          align-items: center;
        }
        &baseline {
          align-items: baseline;
        }
      }
      &justify- {
        &center {
          justify-content: center;
        }
        &start {
          justify-content: flex-start;
        }
        &left {
          justify-content: left;
        }
      }
    }
  }
  ::v-deep hr {
    opacity: 0.2;
  }
  label.bulk-operation-mark {
    &::after {
      content: "|";
      margin-left: 1ex;
      margin-right: 1ex;
    }
  }
  .flex-spacer {
    flex-grow:4;
    height:34px
  }
  .access-action-status {
    display:flex;
    flex-direction:row;
    align-items:center;
    width:100%;
    button.sync-clear {
      margin-left:1ex;
    }
    button.access-action-clear {
      margin-left:1ex;
    }
  }
  ::v-deep a.external.settings {
    background-image:var(--icon-settings-dark);
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
      &:not(.flex-container) {
        display:inline-block;
      }
      .visible {
        &.following {
          &::before {
            content: "|";
            margin-left: 1ex;
            margin-right: 1ex;
          }
        }
        &.user-tag {
          &.group-admin {
            color: red;
          }
          &.organizer {
            color: green;
          }
        }
      }
      .invisible {
        display:none;
      }
    }
    .checkbox.request-mark + label {
      display:inline-block;
    }
  }
  &.sub-admin {
    &.fonts-container {
      /* .file-name-label {
      } */
      .file-name {
        font-family: monospace;
      }
      label.default-font {
        padding-right: 0.5em;
      }
    }
  }
  // Tweak the submit button of the NcTextField
  .input-field::v-deep  .input-field__trailing-button.button-vue--vue-tertiary-no-background {
    max-height: var(--default-clickable-area);
    max-width: var(--default-clickable-area);
    // FIXME: instead we probably should switch to material design icons for everything else ...
    background-image: var(--icon-confirm-dark);
    background-position: center;
    background-repeat: no-repeat;
    .button-vue__icon {
      opacity: 0;
    }
    &:hover, &:focus {
      &:not(:disabled) {
        border: 2px solid var(--color-primary-element);
        border-radius: var(--border-radius-large);
        outline: 2px solid var(--color-main-background);
      }
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
.hint {
  color: var(--color-text-lighter);
  font-size:80%;
  line-height:100%;
}
</style>
