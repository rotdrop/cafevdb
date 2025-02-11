<!--
 - Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2011-2016, 2020, 2021, 2022, 2023, 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
    <NcSettingsSection :class="['major']" :name="t(appId, 'Camerata DB')">
      <div v-if="config.isAdmin">
        <!-- eslint-disable-next-line vue/no-v-html -->
        <p class="info" v-html="forword" />
        <hr>
      </div>
      <div>
        <SelectWithSubmitButton v-model="settings.userAndGroupBackend"
                                input-id="user-and-group-backend-select"
                                :input-label="t(appId, 'User and group backend')"
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
              {{ t(appId, 'Sychronize User Backends') }}
            </NcActionButton>
          </template>
        </SelectWithSubmitButton>
      </div>
      <div v-if="config.isSubAdmin || config.isAdmin">
        <SettingsSelectGroup v-model="settings.orchestraUserGroup"
                             :label="t(appId, 'User Group')"
                             :hint="hints['settings:admin:user-group']"
                             :multiple="false"
                             :required="true"
                             :loading="loading.settings"
                             :disabled="loading.general || !config.isAdmin"
                             @update="saveSetting('orchestraUserGroup')"
                             @error="showErrorToast"
        />
        <SettingsSelectUsers v-model="settings.orchestraUserGroupAdmins"
                             :label="t(appId, 'User Group Admins')"
                             :hint="hints['settings:admin:user-group:admins']"
                             :loading="loading.settings"
                             :disabled="loading.general || groupAdminsDisabled"
                             :required="true"
                             @input="logger.info"
                             @update="saveSetting('orchestraUserGroupAdmins')"
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
              {{ group?.users?.join(', ') || '' }}
            </template>
            <template #indicator>
              <CheckboxBlankCircle v-if="group.status === 'inaccessible'" :size="16" fill-color="red" />
              <CheckboxBlankCircle v-else-if="group.disabled" :size="16" fill-color="yellow" />
              <CheckboxBlankCircle v-else-if="group.backends.indexOf(settings.userAndGroupBackend) == -1"
                                   :size="16"
                                   fill-color="purple"
              />
              <CheckboxBlankCircle v-else :size="16" fill-color="green" />
            </template>
            <template #actions>
              <NcActionButton :disabled="group.status !== 'inaccessible' || group.backends.length > 0"
                              @click="createGroup(group.id)"
              >
                {{ t(appId, 'Create Group') }}
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
      <div v-if="config.isSubAdmin || config.isAdmin">
        <label for="wiki-name-space">
          {{ t(appId, 'Wiki Name-Space') }}
        </label>
        <!-- Note: v-model does not work here -->
        <TextField id="wiki-name-space"
                   :value.sync="settings.wikiNameSpace"
                   type="text"
                   :label="t(appId, 'Wiki Name-Space')"
                   :hint="hints['settings:admin:wiki-name-space']"
                   @submit="saveSetting('wikiNameSpace', settings.wikiNameSpace)"
        />
      </div>
    </NcSettingsSection>
    <NcSettingsSection v-if="config.isSubAdmin" :name="t(appId, 'Configure User Backend')">
      <div>
        <button type="button"
                name="cloudUserBackendConfig"
                value="update"
                :disabled="!config.cloudUserBackendConfig"
                @click="saveSetting('cloudUserBackendConfig')"
        >
          {{ t(appId, 'Autoconfigure "{cloudUserBackend}" app', { cloudUserBackend: config.cloudUserBackend }) }}
        </button>
        <p class="hint">
          {{ hints['settings:admin:cloud-user-backend-conf'] }}
        </p>
      </div>
    </NcSettingsSection>
    <NcSettingsSection v-if="config.isSubAdmin"
                       :class="['sub-admin', { 'icon-loading': loading.recryption }]"
                       :name="t(appId, 'Recryption Requests')"
    >
      <div v-for="(request, userId) in recryption.requests" :key="request.id" class="recryption-request-container">
        <input :id="['mark',userId].join('-')"
               v-model="recryption.requests[userId].marked"
               type="checkbox"
               class="checkbox request-mark"
               @change="markRecryptionRequest"
        >
        <label :for="['mark', userId].join('-')" />
        <NcActions>
          <NcActionButton icon="icon-confirm" @click="handleRecryptionRequest(userId)">
            {{ t(appId, 'recrypt') }}
          </NcActionButton>
          <NcActionButton icon="icon-delete" @click="deleteRecryptionRequest(userId)">
            {{ t(appId, 'reject') }}
          </NcActionButton>
        </NcActions>
        <div :class="['recryption-request-data', { marked: request.marked }, 'flex-container', 'flex-justify-left', 'flex-align-center']">
          <span class="first visible display-name" :title="userId">{{ request.displayName }}</span>
          <span class="following visible time-stamp">{{ formatDate(request.timeStamp, 'LLL') }}</span>
          <span :class="['following', 'user-tag', 'organizer', { visible: request.isOrganizer, invisible: !request.isOrganizer }]">{{
            t(appId, 'organizer') }}</span>
          <span :class="['following', 'user-tag', 'group-admin', { visible: request.isGroupAdmin, invisible: !request.isGroupAdmin }]">{{
            t(appId, 'group-admin') }}</span>
        </div>
      </div>
      <div v-if="Object.keys(recryption.requests).length > 0" class="bulk-operations flex-container flex-align-center">
        <input id="mark-all"
               v-model="recryption.allRequestsMarked"
               type="checkbox"
               class="checkbox request-mark"
               @change="markAllRecryptionRequests"
        >
        <label class="bulk-operation-mark" for="mark-all">{{ t(appId, 'mark/unmark all.') }}</label>
        <span class="bulk-operation-title">
          {{ t(appId, 'With the marked requests perform the following action:') }}
        </span>
        <NcActions>
          <NcActionButton icon="icon-confirm" @click="handleMarkedRecrytpionRequests">
            {{ t(appId, 'recrypt') }}
          </NcActionButton>
          <NcActionButton icon="icon-delete" @click="deleteMarkedRecryptionRequests">
            {{ t(appId, 'reject') }}
          </NcActionButton>
        </NcActions>
      </div>
      <div v-else>
        <span class="hint">{{ t(appId, 'No recryption requests are pending.') }}</span>
      </div>
    </NcSettingsSection>
    <NcSettingsSection v-if="config.isSubAdmin"
                       class="sub-admin"
                       :name="t(appId, 'Access Control')"
    >
      <SelectMusicians v-model="access.musicians"
                       :tooltip="access.musicians.length ? false : hints['settings:admin:access-control:musicians']"
                       :label="t(appId, 'Musicians')"
                       :placeholder="t(appId, 'e.g. Jane Doe')"
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
                      :label="t(appId, 'Restrict User Selection to Project')"
                      :placeholder="t(appId, 'e.g. Auvergne2019')"
                      :multiple="false"
                      :submit-button="false"
                      @update="(...rest) => logger.info('Projects Update', ...rest)"
      />
      <input id="include-disabled"
             v-model="access.includeDeactivated"
             type="checkbox"
             class="checkbox access-flags"
             :disabled="!applyAccessToAll"
      >
      <label for="include-disabled" class="access-flags checkbox-label">
        {{ t(appId, 'include disabled accounts') }}
      </label>
      <input id="include-deactivated"
             v-model="access.includeDisabled"
             type="checkbox"
             class="checkbox access-flags"
             :disabled="!applyAccessToAll"
      >
      <label for="include-deactivated" class="access-flags checkbox-label">
        {{ t(appId, 'include deactivated accounts') }}
      </label>
      <span v-if="showAccessActionProgress">
        <div class="access-action-status">
          <span class="access-action-text">{{ accessActionLabel }}</span>
          <button v-if="accessActionFinished"
                  class="button primary access-action-clear"
                  :title="t(appId, 'Remove the status feedback from the last action.')"
                  @click="hideAccessActionFeedback()"
          >
            {{ t(appId, 'Ok') }}
          </button>
          <span class="flex-spacer" />
          <span class="access-action-counter">{{ accessActionCounter }}</span>
        </div>
        <NcProgressBar :value="accessActionPercentage" :error="accessActionError" size="medium" />
      </span>
      <span v-else class="flex-container flex-align-center flex-justify-start">
        <span class="bulk-operation-title">
          {{ t(appId, 'With the selected musicians perform the following action:') }}
        </span>
        <NcActions>
          <NcActionButton icon="icon-disabled-user" @click="handleAccessAction('deny')">
            {{ t(appId, 'deny access') }}
          </NcActionButton>
          <NcActionButton icon="icon-confirm" @click="handleAccessAction('grant')">
            {{ t(appId, 'grant access') }}
          </NcActionButton>
        </NcActions>
      </span>
    </NcSettingsSection>
    <NcSettingsSection v-if="config.isSubAdmin"
                       :class="['sub-admin', 'fonts-container']"
                       :name="t(appId, 'Configure Office Fonts for Office Exports')"
    >
      <div>
        <span class="file-name-label">{{ t(appId, 'Font Data Folder') }}</span>
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
            {{ t(appId, 'Default Font') }}
          </label>
        </template>
        <template #actions>
          <NcActionButton icon="icon-add" @click="updateFontData">
            {{ t(appId, 'Update Font Data') }}
          </NcActionButton>
          <NcActionButton icon="icon-play" @click="rescanFontData">
            {{ t(appId, 'Rescan Font Data') }}
          </NcActionButton>
          <NcActionButton icon="icon-delete" @click="purgeFontData">
            {{ t(appId, 'Purge Font Data') }}
          </NcActionButton>
        </template>
      </SelectWithSubmitButton>
    </NcSettingsSection>
    <NcSettingsSection v-if="config.isSubAdmin"
                       :class="['sub-admin', 'problem-reports']"
                       :name="t(appId, 'Configure Problem Report Handling')"
    >
      <div :class="['problem-report', 'email-recipient', 'verification-status-' + settings.problemReportEmailRecipientStatus]">
        <TextField :value.sync="settings.problemReportEmailRecipient"
                   :disabled="settings.problemReportEmailRecipientStatus === 'pending'"
                   type="text"
                   :label="t(appId, 'Problem Report Email Recipient')"
                   class="recipient-email-address"
                   :show-trailing-button="true"
                   trailing-button-icon="arrowRight"
                   :helper-text="hints['settings:admin:problem-report:email:recipient']"
                   :success="settings.problemReportEmailRecipientStatus === 'verified'"
                   :error="settings.problemReportEmailRecipientStatus === 'failed'"
                   @submit="saveProblemReportEmailRecipient"
        >
          <IconEmailVerified v-if="settings.problemReportEmailRecipientStatus === 'verified'"
                             v-tooltip="hints['settings:admin:problem-report:email:verification:status:verified']"
                             :size="props.leadingIconSize"
          />
          <IconEmailVerificationFailed v-else-if="settings.problemReportEmailRecipientStatus === 'failed'"
                                       v-tooltip="hints['settings:admin:problem-report:email:verification:status:failed']"
                                       :size="props.leadingIconSize"
          />
          <IconEmailVerificationPending v-else
                                        v-tooltip="hints['settings:admin:problem-report:email:verification:status:pending']"
                                        :size="props.leadingIconSize"
          />
        </TextField>
        <TextField v-if="settings.problemReportEmailRecipientStatus === 'pending' || settings.problemReportEmailRecipientStatus === 'failed'"
                   :value.sync="settings.problemReportEmailRecipientVerification"
                   type="text"
                   :label="t(appId, 'Email Verification Code')"
                   :helper-text="hints['settings:admin:problem-report:email:verification:code']"
                   :class="['verification-code', { 'new-input': problemReportRecipientVerificationInput }]"
                   :error="settings.problemReportEmailRecipientStatus === 'failed'"
                   @input="problemReportRecipientVerificationInput = true"
                   @submit="saveProblemReportEmailRecipientVerification"
        >
          <IconEmailVerificationFailed v-if="settings.problemReportEmailRecipientStatus === 'failed'"
                                       v-tooltip="hints['settings:admin:problem-report:email:verification:status:failed']"
                                       :size="props.leadingIconSize"
          />
          <IconEmailVerificationPending v-else
                                        v-tooltip="hints['settings:admin:problem-report:email:verification:status:pending']"
                                        :size="props.leadingIconSize"
          />
          <template #alignedAfter>
            <NcActions>
              <NcActionButton v-tooltip="hints['settings:admin:problem-report:email:verification:resend']"
                              :name="t(appId, 'resend verification code')"
                              @click="saveProblemReportEmailRecipient"
              >
                <template #icon>
                  <IconResendVerificationCode />
                </template>
              </NcActionButton>
              <NcActionButton v-tooltip="hints['settings:admin:problem-report:email:verification:cancel']"
                              :name="t(appId, 'cancel verification')"
                              @click="cancelProblemReportEmailRecipientVerification"
              >
                <template #icon>
                  <IconCancel />
                </template>
              </NcActionButton>
            </NcActions>
          </template>
        </TextField>
      </div>
    </NcSettingsSection>
    <NcSettingsSection v-if="config.isSubAdmin"
                       :class="['sub-admin', 'vue-devtools']"
                       :name="t(appId, 'Enable Vue DevTools Support')"
    >
      <NcCheckboxRadioSwitch v-model="vueDevTools">
        {{ t(appId, 'Check to enable Vue Devtools support (also needs unsafe-eval CSP)') }}
      </NcCheckboxRadioSwitch>
    </NcSettingsSection>
  </div>
</template>
<script setup lang="ts">
import {
  set as vueSet,
  del as vueDelete,
  nextTick as vueNextTick,
  ref,
  watch,
  computed,
  reactive,
  onBeforeMount,
  onUnmounted,
} from 'vue'
import {
  NcActions,
  NcActionButton,
  NcCheckboxRadioSwitch,
  NcProgressBar,
  NcSettingsSection,
  NcListItem,
} from '@nextcloud/vue'
import IconEmailVerificationPending from 'vue-material-design-icons/Help.vue'
import IconEmailVerified from 'vue-material-design-icons/EmailCheck.vue'
import IconEmailVerificationFailed from 'vue-material-design-icons/Cancel.vue'
// import IconCancel from 'vue-material-design-icons/Cancel.vue'
import IconResendVerificationCode from 'vue-material-design-icons/Sync.vue'
import TextField from '@rotdrop/nextcloud-vue-components/lib/components/TextFieldWithSubmitButton.vue'
import SelectWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/SelectWithSubmitButton.vue'

import CheckboxBlankCircle from 'vue-material-design-icons/CheckboxBlankCircle.vue'
import GroupIcon from 'vue-material-design-icons/AccountGroup.vue'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { generateUrl as generateAppUrl, generateOcsUrl as generateAppOcsUrl } from '../toolkit/util/generate-url.js'
import { loadState } from '@nextcloud/initial-state'
import { showError, showInfo, TOAST_DEFAULT_TIMEOUT, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'

import { appName as appId } from '../config.ts'
import cloudVersionClasses from '../toolkit/util/cloud-version-classes.js'

import SelectMusicians from './SelectMusicians.vue'

import SelectProjects from './SelectProjects.vue'

import SettingsSelectGroup from './SettingsSelectGroup.vue'
import SettingsSelectUsers from './SettingsSelectUsers.vue'
import { tooltips } from '../util/tooltips.ts'
import formatDate from '../util/formatDate.js'
import { showErrorToast } from '../util/toasts.ts'
import dialogConfirm from '../util/dialogs.ts'
import type { AxiosResponse } from 'axios'
// eslint-disable-next-line n/no-missing-import
import type { OCSResponse } from '@nextcloud/typings/ocs'
import type { CloudUser, CloudGroup } from '../stores/cloud-users-groups.ts'
import { translate as t } from '@nextcloud/l10n'
import { useCloudUsersGroupsStore } from '../stores/cloud-users-groups.ts'
import Console from '../util/console.ts'
import { joinLiterals } from '../util/string-literals.ts'
import { enableVueDevTools, disableVueDevTools } from '../util/vue-devtools.ts'

const IconCancel = IconEmailVerificationFailed

const COMPONENT_NAME = 'AdminSettings'

const logger = new Console(COMPONENT_NAME)

/******************************************************************************
 *
 * Type definitions.
 *
 */

type Project = {
  id: number,
}

type Musician = {
  id: number,
  userIdSlug: string,
  status?: string,
}

type FontFiles = {
  family: string,
  x?: string,
  xb?: string,
  xi?: string,
  xbi?: string,
}

type InitialState = {
  authorizationGroupSuffixes: string[],
  isAdmin: boolean,
  isSubAdmin: boolean,
  officeFonts: Record<string, FontFiles>
  officeFontsFolder: string,
  personalAppSettingsLink: string,
  userAndGroupBackends: string[],
  cloudUserBackendConfig: boolean,
  cloudUserBackend: string,
}

type AppAdminSettings = {
  userAndGroupBackend: string,
  orchestraUserGroup: string,
  orchestraUserGroupAdmins: string[],
  wikiNameSpace: string,
  cloudUserBackendConfig: string,
  defaultOfficeFont: string,
  problemReportEmailRecipient: string;
  problemReportEmailRecipientVerification: string;
  problemReportEmailRecipientStatus: string;
}

type CloudUserGetResponse = AxiosResponse<OCSResponse<CloudUser> >
type RecryptionGetResponse = AxiosResponse<OCSResponse<{ requests: Record<string, number>}> >

type BulkRecryptionCountResponse = AxiosResponse<OCSResponse<{ count: number }> >
type RecryptionStatus = 'granted' | 'revoked' | 'failure'
type BulkRecryptionResponse = AxiosResponse<OCSResponse<{ userId: string, status: RecryptionStatus}[]> >

type UserRecryptionResponse = AxiosResponse<OCSResponse<{
  keyStatus: string,
  userId: string,
  status: 'granted' | 'failure',
  message?: string,
}> >

type AdminSettingPostResponse = AxiosResponse<{
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  value?: any,
  messages?: {
    transient?: string[],
    permanent?: string[],
  }
  status?: string,
  feedback?: string,
}>

type RecryptionRequest = {
  id: string,
  timeStamp: number,
  displayName: string,
  groups: string[],
  enabled: boolean,
  isOrganizer: boolean,
  isGroupAdmin: boolean,
  marked: boolean,
}

type AccessActionGrant = 'grant'
type AccessActionDeny = 'deny'
type AccessActions = AccessActionGrant | AccessActionDeny

type FontCacheOperationUpdate = 'update'
type FontCacheOperationRescan = 'rescan'
type FontCacheOperationPurge = 'purge'
type FontCacheOperations = FontCacheOperationPurge | FontCacheOperationRescan | FontCacheOperationUpdate

type GroupType = (CloudGroup|Pick<CloudGroup, 'id'|'displayname'|'backends'|'users'|'usercount'|'disabled'>) & {
  l10nStatus?: string,
  status?: string,
}

/*
 * End type definitions
 *
 ******************************************************************************
 *
 * Props and data.
 *
 */

const props = withDefaults(defineProps<{
  recryptionPollTimeout?: number,
  leadingIconSize?: number,
}>(), {
  recryptionPollTimeout: 10 * 1000,
  leadingIconSize: 24,
})

const initialState: InitialState = loadState(appId, 'adminConfig')

const store = useCloudUsersGroupsStore()

const vueDevTools = ref(false)
watch(vueDevTools, (value) => {
  if (value) {
    enableVueDevTools()
  } else {
    disableVueDevTools()
  }
})

const defaultOfficeFont = ref<FontFiles|null>(null)
const loading = reactive({
  general: true,
  recryption: true,
  tooltips: true,
  fonts: true,
  settings: true,
  groups: true,
})

const settings: AppAdminSettings = reactive({
  userAndGroupBackend: '',
  orchestraUserGroup: '',
  orchestraUserGroupAdmins: [],
  wikiNameSpace: '',
  cloudUserBackendConfig: '',
  defaultOfficeFont: '',
  problemReportEmailRecipient: '',
  problemReportEmailRecipientVerification: '',
  problemReportEmailRecipientStatus: '',
})

const problemReportRecipientVerificationInput = ref(false)

const settingsBackup = reactive({} as AppAdminSettings)
const orchestraGroups = reactive({} as Record<string, GroupType>)
const config = reactive(initialState)
const hints = reactive({
  'settings:admin:cloud-user-backend-conf': '',
  'settings:admin:wiki-name-space': '',
  'settings:admin:user-group': '',
  'settings:admin:user-group:admins': '',
  'settings:admin:user-and-group-backend': '',
  'settings:admin:access-control:musicians': '',
  'settings:admin:true-type-fonts-folder': '',
  'settings:admin:user-backend:move-users': '',
  'settings:admin:problem-report:email:recipient': '',
  'settings:admin:problem-report:email:verification:code': '',
  'settings:admin:problem-report:email:verification:resend': '',
  'settings:admin:problem-report:email:verification:status:verified': '',
  'settings:admin:problem-report:email:verification:status:pending': '',
  'settings:admin:problem-report:email:verification:status:failed': '',
})
const forword = ref('')
const recryption = reactive({
  requests: {} as Record<string, RecryptionRequest>,
  allRequestsMarked: false,
})
let recryptionPollTimer = undefined as undefined | NodeJS.Timeout
const access = reactive({
  musicians: [] as Musician[],
  project: undefined as Project|undefined,
  includeDeactivated: false,
  includeDisabled: false,
  action: {
    failure: false,
    totals: 0,
    done: 0,
    active: false,
    label: '',
  },
})

/*
 * End props and data
 *
 ******************************************************************************
 *
 * computed data
 *
 */

const humanOfficeFontsFolder = computed(() =>
  '.../' + (config.officeFontsFolder + '/').replace(/\/+/, '/').split('/').splice(-4).join('/'),
)
const orchestraUserGroup = computed(() => settings.orchestraUserGroup)
const groupAdminsDisabled = computed(() => settings.orchestraUserGroup === '' || !config.isAdmin)
const projectId = computed(() => {
  try { return access.project!.id } catch (ignoreMe) { return 0 }
})
const applyAccessToAll = computed(() => access.musicians.length === 1 && access.musicians[0].id <= 0)
const showAccessActionProgress = computed(() => access.action.active)
const accessActionPercentage = computed(() => {
  const totals = access.action.totals
  const done = access.action.done
  return totals > 0 ? done * 100.0 / totals : 0
})
const accessActionFinished = computed(() => {
  const totals = access.action.totals
  const done = access.action.done
  return totals === 0 || (done > 0 && done >= totals) || access.action.failure
})
const accessActionLabel = computed(() => access.action.label)
const accessActionCounter = computed(() => {
  const totals = access.action.totals
  const current = access.action.done
  return t(appId, '{current} of {totals}', { current, totals })
})
const accessActionError = computed(() => access.action.failure)
const savedDefaultOfficeFont = computed(() => config.officeFonts?.[settingsBackup.defaultOfficeFont])

watch(defaultOfficeFont, (newValue) => {
  vueSet(settings, 'defaultOfficeFont', newValue?.family)
})

/*
 * End computed data
 *
 ******************************************************************************
 *
 * methods / functions
 *
 */

const getData = async () => {
  loading.general = true
  loading.recryption = true
  loading.fonts = true
  loading.settings = true
  loading.groups = true
  loadTooltips()
  if (config.isSubAdmin) {
    // fetch recryption requests
    getRecryptionRequests()
  }

  disableUnavailableFontOptions()

  await getSettingsData()

  loadOrchestraGroups()

  defaultOfficeFont.value = config.officeFonts[settings.defaultOfficeFont]
  await vueNextTick()
  loading.fonts = false
  loading.general = false
}
const loadOrchestraGroups = async () => {
  if (!orchestraUserGroup.value || orchestraUserGroup.value === '') {
    return []
  }
  loading.groups = true
  const gids = Object.values(config.authorizationGroupSuffixes).map((suffix) => orchestraUserGroup.value + suffix).sort()
  for (const id of gids) {
    let group = (await getGroup(id)) as GroupType | undefined
    if (group) {
      if (!group.users) {
        await (group as CloudGroup).getUsers(errorHandler)
      }
      group.l10nStatus = t(appId, group.status = 'accessible')
    } else {
      group = {
        id,
        displayname: id,
        backends: [],
        status: 'inaccessible',
        usercount: 0,
        disabled: false,
        l10nStatus: t(appId, 'inaccessible'),
      }
      console.info('GROUP INACCESSIBLE', group)
    }
    vueSet(orchestraGroups, id, group)
  }
  loading.groups = false
}
const loadTooltips = async () => {
  loading.tooltips = true
  const personalSettingsLink = '<a class="external settings" href="' + config.personalAppSettingsLink + '">' + appId + '</a>'
  forword.value = t(
    appId,
    'Further detailed configurations are necessary after configuring the user-group. Please configure a dedicated group-admin for the user-group and then log-in as this group-admin and head over to the {personalSettingsLink} settings.', {
      personalSettingsLink,
    }, undefined, { escape: false })
  Object.assign(hints, await tooltips(Object.keys(hints)))
  loading.tooltips = false
}
const getSettingsData = async (settingsKeys: (keyof AppAdminSettings)[] = []) => {
  loading.settings = true
  const requests: Promise<AxiosResponse<{ value: string }> >[] = []
  if (settingsKeys.length === 0) {
    settingsKeys = Object.keys(settings) as (keyof AppAdminSettings)[]
  }
  for (const key of settingsKeys) {
    requests.push(axios.get(generateAppUrl('settings/admin/{key}', { key })))
  }
  const responses = await Promise.allSettled(requests)
  for (const [i, promiseResult] of responses.entries()) {
    const key = settingsKeys[i]
    if (promiseResult.status === 'fulfilled') {
      const response = promiseResult.value
      vueSet(settings, key, response.data.value)
    } else {
      const e = promiseResult.reason
      let message = t(appId, 'reason unknown')
      if (e.response && e.response.data && e.response.data.message) {
        message = e.response.data.message
        console.error('RESPONSE', e.response)
      }
      showError(t(appId, 'Could not fetch the value for "{key}": {message}', { key, message }), { timeout: TOAST_PERMANENT_TIMEOUT })
      vueSet(settings, key, settingsBackup[key])
    }
  }
  Object.assign(settingsBackup, settings)
  loading.settings = false
}
const saveSetting = async (settingsKey: string, value?: string) => {
  try {
    if (value === undefined) {
      value = settings[settingsKey]
    } else {
      logger.info('VALUE vs VMODEL', value, settings[settingsKey])
    }
    const response: AdminSettingPostResponse = await axios.post(generateAppUrl('settings/admin/{settingsKey}', { settingsKey }), { value })
    const responseData = response.data
    if (responseData.status === 'unconfirmed') {
      await new Promise(resolve => {
        dialogConfirm(
          t(appId, 'Confirmation Required'),
          responseData.feedback as string,
          (answer) => {
            if (answer) {
              saveSetting(settingsKey, value)
            } else {
              showInfo(t(appId, 'Unconfirmed, reverting to old value.'))
              getSettingsData()
            }
            resolve(answer)
          },
        )
      })
      // OC.dialogs.confirm(
      //   responseData.feedback,
      //   t(appId, 'Confirmation Required'),
      //   (answer) => {
      //     if (answer) {
      //       saveSetting(settingsKey, value, true)
      //     } else {
      //       showInfo(t(appId, 'Unconfirmed, reverting to old value.'))
      //       getSettingsData()
      //     }
      //   },
      //   true)
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
          transient.push(t(appId, 'Successfully set value for "{settingsKey}" to "{value}".', { settingsKey, value }))
        } else {
          transient.push(t(appId, 'Value for "{settingsKey}" has been erased.', { settingsKey }))
        }
      }
      for (const message of transient) {
        showInfo(message, { timeout: TOAST_DEFAULT_TIMEOUT, isHTML: true })
      }
      for (const message of permanent) {
        showInfo(message, { timeout: TOAST_PERMANENT_TIMEOUT, isHTML: true })
      }
      settingsBackup[settingsKey] = value
    }
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
  } catch (e: any) {
    let message = t(appId, 'reason unknown')
    if (e.response && e.response.data && e.response.data.message) {
      message = e.response.data.message
      console.error('RESPONSE', e.response)
    }
    if (value !== undefined) {
      if (Array.isArray(value)) {
        value = value.join(', ')
      }
      showError(t(appId, 'Could not set "{settingsKey}" to "{value}": {message}', { settingsKey, value, message }), { timeout: TOAST_PERMANENT_TIMEOUT })
    } else {
      showError(t(appId, 'Could not set "{settingsKey}": {message}', { settingsKey, message }), { timeout: TOAST_PERMANENT_TIMEOUT })
    }
    getSettingsData()
  }
}

const saveProblemReportEmailRecipient = async () => {
  const key = 'problemReportEmailRecipient'
  const requestedValue = settings[key]
  await saveSetting(key)
  if (requestedValue === settings[key]) {
    getSettingsData([joinLiterals()(key, 'Verification'), joinLiterals()(key, 'Status')])
  }
  problemReportRecipientVerificationInput.value = false
}
const saveProblemReportEmailRecipientVerification = async () => {
  const baseKey = 'problemReportEmailRecipient'
  const key = joinLiterals()(baseKey, 'Verification')
  const requestedValue = settings[key]
  await saveSetting(key)
  if (requestedValue === settings[key]) {
    getSettingsData([joinLiterals()(baseKey, 'Status')])
  }
  problemReportRecipientVerificationInput.value = false
}
const cancelProblemReportEmailRecipientVerification = async () => {
  const keys = ['problemReportEmailRecipient', 'problemReportEmailRecipientVerification'] as const
  for (const key of keys) {
    settings[key] = ''
    await saveSetting(key)
    if (settings[key] !== '') {
      return
    }
  }
  getSettingsData([...keys, joinLiterals()(keys[0], 'Status')])
  problemReportRecipientVerificationInput.value = false
}
const getRecryptionRequests = async () => {
  loading.recryption = true
  vueSet(recryption, 'requests', {})
  vueSet(recryption, 'allRequestsMarked', '')
  await updateRecryptionRequests()
  recryptionPollTimer = setTimeout(() => pollRecryptionRequests(), props.recryptionPollTimeout)
}
const pollRecryptionRequests = async () => {
  await updateRecryptionRequests()
  recryptionPollTimer = setTimeout(() => pollRecryptionRequests(), props.recryptionPollTimeout)
}
/**
 * Update the recryption requests if needed. It is assumed that
 * the time-stamp is a unique key, so if there is already a
 * recryption request for a user with the same time-stamp then it
 * is not replaced.
 */
const updateRecryptionRequests = async () => {
  try {
    const url = generateAppOcsUrl('api/v1/maintenance/encryption/recrypt')
    const response = (await axios.get(url + '?format=json')) as RecryptionGetResponse
    const recryptionRequests = response.data.ocs.data.requests
    // remove requests which are no longer there
    for (const userId of Object.keys(recryption.requests)) {
      if (!recryptionRequests[userId]) {
        vueDelete(recryption.requests, userId)
      }
    }
    // update existing requests (time-stamp changed) and add new
    // ones. Initiate the AJAX calls in parallel, then serialize
    // later
    const cloudUserPromises = [] as { userId: string, timeStamp: number, promise: Promise<CloudUserGetResponse> }[]
    for (const [userId, timeStamp] of Object.entries(recryptionRequests)) {
      if (!recryption.requests[userId] || recryption.requests[userId].timeStamp !== timeStamp) {
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
        const isOrganizer = user.groups.indexOf(settings.orchestraUserGroup) >= 0
        const isGroupAdmin = settings.orchestraUserGroupAdmins.indexOf(userId) >= 0
        vueSet(recryption.requests, userId, {
          id: userId,
          timeStamp,
          displayName: user.displayname,
          groups: user.groups,
          enabled: user.enabled,
          isOrganizer,
          isGroupAdmin,
          marked: false,
        })
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
      } catch (e: any) {
        console.error('Unable to fetch data for user ' + userId, e)
      }
    }
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
  } catch (e: any) {
    // admin is maybe not authorized
    console.error('Unable to fetch recryption entries', e)
  }
  loading.recryption = false
}
const synchronizeUserBackends = () => {
  showError(t(appId, 'Synchronizing user backends not yet implemented.'), { timeout: TOAST_PERMANENT_TIMEOUT })
}
const markAllRecryptionRequests = (/* event */) => {
  const value = !!recryption.allRequestsMarked
  for (const request of Object.values(recryption.requests)) {
    request.marked = value
  }
}
const markRecryptionRequest = (/* userId, event */) => {
  const allRequests = Object.values(recryption.requests)
  const marked = allRequests.filter(request => request.marked)
  if (marked.length === allRequests.length) {
    recryption.allRequestsMarked = true
  } else {
    recryption.allRequestsMarked = false
  }
}
const doHandleRecryptionRequest = (userId: string, silent = false, allowFailure = false) => {
  const url = generateAppOcsUrl('api/v1/maintenance/encryption/recrypt/{userId}', {
    userId,
  })
  return axios.post(url + '?format=json', {
    notifyUser: silent !== true,
    allowFailure,
  }) as Promise<UserRecryptionResponse>
}
const handleRecryptionRequest = (userId: string, silent = false) =>
  awaitRecryptionRequestPromise(userId, doHandleRecryptionRequest(userId, silent))
const awaitRecryptionRequestPromise = async (userId: string, promise: Promise<UserRecryptionResponse>) => {
  try {
    await promise
    showInfo(t(appId, 'Successfully handled recryption request for {userId}.', { userId }))
    vueDelete(recryption.requests, userId)
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
  } catch (e: any) {
    if (e.response) {
      console.error('RESPONSE', e.response)
    }
    let message = t(appId, 'reason unknown')
    if (e.response && e.response.data && e.response.data.ocs) {
      message = e.response.data.ocs.meta.message
        + ' ('
        + e.response.data.ocs.meta.statuscode
        + ', ' + e.response.data.ocs.meta.status
        + ')'
    }
    showError(t(appId, 'Could not resolve the recryption request for {userId}: {message}', { userId, message }), { timeout: TOAST_PERMANENT_TIMEOUT })
    getRecryptionRequests()
  }
}
const deleteRecryptionRequest = async (userId: string) => {
  try {
    const url = generateAppOcsUrl('api/v1/maintenance/encryption/recrypt/{userId}', {
      userId,
    })
    await axios.delete(url + '?format=json')
    showInfo(t(appId, 'Successfully deleted recryption request for {userId}.', { userId }))
    vueDelete(recryption.requests, userId)
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
  } catch (e: any) {
    if (e.response) {
      console.error('RESPONSE', e.response)
    }
    let message = t(appId, 'reason unknown')
    if (e.response && e.response.data && e.response.data.ocs) {
      message = e.response.data.ocs.meta.message
        + ' ('
        + e.response.data.ocs.meta.statuscode
        + ', ' + e.response.data.ocs.meta.status
        + ')'
    }
    showError(t(appId, 'Could not delete the recryption request for {userId}: {message}', { userId, message }), { timeout: TOAST_PERMANENT_TIMEOUT })
    getRecryptionRequests()
  }
}
const doRevokeCloudAccess = (userId: string/*, allowFailure */) => {
  const url = generateAppOcsUrl(
    'api/v1/maintenance/encryption/revoke/{userId}', {
      userId,
    },
  )
  return axios.post(url + '?format=json')
}
const handleMarkedRecrytpionRequests = async () => {
  const allRequests = Object.values(recryption.requests)
  const marked = allRequests.filter(request => request.marked)
  const recryptionPromises: Record<string, Promise<UserRecryptionResponse>> = {}
  for (const request of marked) {
    const userId = request.id
    recryptionPromises[userId] = doHandleRecryptionRequest(userId)
  }
  for (const [userId, promise] of Object.entries(recryptionPromises)) {
    // @todo Promise.allSettled
    await awaitRecryptionRequestPromise(userId, promise)
  }
}
const deleteMarkedRecryptionRequests = async () => {
  const allRequests = Object.values(recryption.requests)
  const marked = allRequests.filter(request => request.marked)
  for (const request of marked) {
    // @todo Promise.allSettled
    await deleteRecryptionRequest(request.id)
  }
}
const handleAccessAction = async (action: AccessActions) => {
  if (access.musicians.length === 0) {
    showError(t(appId, 'No musicians selected, doing nothing.'), { timeout: TOAST_DEFAULT_TIMEOUT })
  }
  if (access.musicians.length === 1 && access.musicians[0].id <= 0) {
    handleBulkAccessAction(action)
    return
  }
  access.action.active = true
  access.action.totals = access.musicians.length
  let failedUsers = 0
  try {
    for (const musician of access.musicians) {
      const response = action === 'grant'
        ? await doHandleRecryptionRequest(musician.userIdSlug, true, true)
        : await doRevokeCloudAccess(musician.userIdSlug)
      const ocsData = response.data.ocs.data
      const lastUser = ocsData.userId
      ocsData.status === 'failure' && ++failedUsers
      access.action.done++
      access.action.label = t(appId, 'Processed user-id {userId}.', { userId: lastUser })
      if (failedUsers > 0) {
        access.action.label += ' ' + t(appId, '{failedUsers} users have failed.', { failedUsers })
      }
    }
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
  } catch (e: any) {
    logger.info('ERROR', e)
    let message = t(appId, 'reason unknown')
    if (e.response && e.response.data) {
      const data = e.response.data
      if (data.message) {
        message = data.message
      } else if (data.ocs && data.ocs.meta && data.ocs.meta.message) {
        message = data.ocs.meta.message
      }
    }
    showError(t(appId, 'Unable to handle access action: {message}', { message }), { timeout: TOAST_PERMANENT_TIMEOUT })
    access.action.failure = true
  }
  const numUsers = access.action.done - failedUsers
  const remainingUsers = access.action.totals - access.action.done

  if (access.action.failure) {
    access.action.label = t(appId, 'Failed after {numUsers} users have been processed successfully.', { numUsers })
    if (failedUsers > 0) {
      access.action.label += ' ' + t(appId, '{failedUsers} were processed unsuccessfully.', { failedUsers })
    }
    access.action.label += ' ' + t(appId, '{remainingUsers} remain unprocessed.', { remainingUsers })
  } else {
    access.action.label = t(appId, '{numUsers} users have been processed successfully.', { numUsers })
    if (failedUsers > 0) {
      access.action.label += ' ' + t(appId, '{failedUsers} were processed unsuccessfully.', { failedUsers })
    }
  }
}
const handleBulkAccessAction = async (action: AccessActions) => {
  access.action.active = true
  let failedUsers = 0
  try {
    const url = generateAppOcsUrl('api/v1/maintenance/encryption/bulk-recryption?format=json')
    const response: BulkRecryptionCountResponse = await axios.post(url, {
      grantAccess: action === 'grant',
      includeDisabled: access.includeDisabled,
      includeDeactivated: access.includeDeactivated,
      projectId: projectId.value,
      offset: 0,
      limit: 0,
    })
    access.action.totals = response.data.ocs.data.count
    const limit = access.action.totals > 100 ? access.action.totals / 100 : 1
    let count = 0
    let lastUser: string
    do {
      const url = generateAppOcsUrl('api/v1/maintenance/encryption/bulk-recryption?format=json')
      const response: BulkRecryptionResponse = await axios.post(url, {
        grantAccess: action === 'grant',
        includeDisabled: access.includeDisabled,
        includeDeactivated: access.includeDeactivated,
        projectId: projectId.value,
        offset: access.action.done,
        limit,
      })
      const musicians = response.data.ocs.data
      failedUsers = musicians.reduce((failedUsers, musician) => failedUsers + (musician.status === 'failure' ? 1 : 0), failedUsers)
      lastUser = musicians.slice(-1)[0].userId
      count = musicians.length
      access.action.done += count
      access.action.label = t(appId, 'Processed user-id {userId}.', { userId: lastUser })
      if (failedUsers > 0) {
        access.action.label += ' ' + t(appId, '{failedUsers} users have failed.', { failedUsers })
      }
      access.action.label += '.'
    } while (count > 0 && access.action.done < access.action.totals)
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
  } catch (e: any) {
    logger.info('ERROR', e)
    let message = t(appId, 'reason unknown')
    if (e.response && e.response.data) {
      const data = e.response.data
      if (data.message) {
        message = data.message
      } else if (data.ocs && data.ocs.meta && data.ocs.meta.message) {
        message = data.ocs.meta.message
      }
    }
    showError(t(appId, 'Unable to handle access action: {message}', { message }), { timeout: TOAST_PERMANENT_TIMEOUT })
    access.action.failure = true
  }
  const numUsers = access.action.done - failedUsers
  const remainingUsers = access.action.totals - access.action.done

  if (access.action.failure) {
    access.action.label = t(appId, 'Failed after {numUsers} users have been processed successfully.', { numUsers })
    if (failedUsers > 0) {
      access.action.label += ' ' + t(appId, '{failedUsers} were processed unsuccessfully.', { failedUsers })
    }
    access.action.label += ' ' + t(appId, '{remainingUsers} remain unprocessed.', { remainingUsers })
  } else {
    access.action.label = t(appId, '{numUsers} users have been processed successfully.', { numUsers })
    if (failedUsers > 0) {
      access.action.label += ' ' + t(appId, '{failedUsers} were processed unsuccessfully.', { failedUsers })
    }
  }
}
const hideAccessActionFeedback = () => {
  access.action.active = false
  access.action.failure = false
  access.action.done = 0
  access.action.totals = 0
}
const updateFontData = () => fontCacheOperaton('update')
const rescanFontData = () => fontCacheOperaton('rescan')
const purgeFontData = () => fontCacheOperaton('purge')
const fontCacheOperaton = async (operation: FontCacheOperations) => {
  loading.fonts = true
  try {
    const response = await axios.post(generateAppUrl('settings/admin/font-cache'), { operation })
    const responseData = response.data
    if (responseData.message) {
      showInfo(responseData.message)
    } else {
      showInfo(t(appId, 'Font cache operation {operation} completed successfully.', { operation }))
    }
    config.officeFonts = responseData.fonts
    settings.defaultOfficeFont = responseData.default
    defaultOfficeFont.value = config.officeFonts[settings.defaultOfficeFont]
    disableUnavailableFontOptions()
    logger.info('FONT DATA', responseData)
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
  } catch (e: any) {
    logger.info('ERROR', e)
    let message = t(appId, 'reason unknown')
    if (e.response && e.response.data && e.response.data.message) {
      message = e.response.data.message
      console.error('RESPONSE', e.response)
    }
    showError(t(appId, 'Could not perform the requested font-cache operation "{operation}": {message}', { operation, message }), { timeout: TOAST_PERMANENT_TIMEOUT })
  }
  loading.fonts = false
}
const disableUnavailableFontOptions = () => {
  for (const [fontName, fontFiles] of Object.entries(config.officeFonts)) {
    if (fontFiles.x && fontFiles.xb && fontFiles.xi && fontFiles.xbi) {
      vueSet(config.officeFonts[fontName], 'disabled', false)
    } else {
      vueSet(config.officeFonts[fontName], 'disabled', true)
      vueSet(config.officeFonts[fontName], '$isDisabled', true)
      logger.info('DISABLE FONT', fontName, config.officeFonts[fontName])
    }
  }
}
const getGroup = (gid: string) => store.getGroup(gid, errorHandler)
const createGroup = async (gid: string) => {
  const result = await store.createGroup(gid, gid, errorHandler)

  if (result && orchestraGroups[gid]) {
    if (!result.users) {
      await result.getUsers(errorHandler)
    }
    vueSet(orchestraGroups, gid, result)
  }

  return result
}

const emit = defineEmits(['error'])

// eslint-disable-next-line @typescript-eslint/no-explicit-any
const errorHandler = <T extends Error>(error: T | any) => {
  emit('error', error)
}

onBeforeMount(getData)
onUnmounted(() => {
  clearTimeout(recryptionPollTimer)
  recryptionPollTimer = undefined
})
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
  ::v-deep .problem-report {
    &.email-recipient {
      &.verification-status-failed {
        .verification-code:not(.new-input) input.input-field__input {
          text-decoration: line-through;
        }
      }
    }
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
