<!--
 - Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2022, 2023, 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 -
 - @file
 - This file wraps an NcSelect with userSelect option but as input
 - only a flat array of user-ids is provided, and the output is then
 - also just a flat array of selected user ids, while the fancy
 - wrapped user-select uses full-fledged user instances.
 -
 - Finally: the core distribution of NcSelect fails to set the "user:
 - USER_ID" property which is needed to actually fetch the
 - avatar. This should go to a pull-request ...
 -->
<template>
  <SelectWithSubmitButton ref="select"
                          v-bind="$attrs"
                          v-model="inputValObjects"
                          :reduce="(user) => user.id"
                          label="displayname"
                          :options="usersArray"
                          :options-limit="100"
                          :placeholder="label"
                          :input-label="label"
                          :loading="isLoading"
                          :multiple="true"
                          :close-on-select="false"
                          :disabled="disabled"
                          :user-select="true"
                          v-on="$listeners"
                          @search="(query) => findUsers(query)"
  >
    <!-- Unfortunately, the stock NcSelect seems to be somewhat borken and does not set the "user" property. -->
    <template #option="option">
      <NcListItemIcon v-tooltip="userInfoPopup(option)"
                      v-bind="option"
                      :user="option.id"
                      :avatar-size="24"
                      :name="ncSelect ? option[ncSelect.localLabel] : t(appName, 'undefined')"
                      :search="ncSelect ? ncSelect.search : t(appName, 'undefined')"
      />
    </template>
    <template #selected-option="option">
      <NcListItemIcon v-tooltip="userInfoPopup(option)"
                      v-bind="option"
                      :user="option.id"
                      :avatar-size="24"
                      :name="ncSelect ? option[ncSelect.localLabel] : t(appName, 'undefined')"
                      :search="ncSelect ? ncSelect.search : t(appName, 'undefined')"
      />
    </template>
  </SelectWithSubmitButton>
</template>

<script>
import SelectWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/SelectWithSubmitButton.vue'
import { NcListItemIcon } from '@nextcloud/vue'
import { useCloudUsersGroupsStore } from '../stores/cloud-users-groups.js'
import { mapWritableState } from 'pinia'
import userInfoPopup from '../mixins/user-info-popup.js'

export default {
  name: 'SettingsSelectUsers',
  components: {
    SelectWithSubmitButton,
    NcListItemIcon,
  },
  mixins: [
    userInfoPopup,
  ],
  inheritAttrs: false,
  props: {
    label: {
      type: String,
      required: true,
    },
    value: {
      type: Array,
      default: () => [],
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    loading: {
      type: Boolean,
      default: false,
    },
    loadingIndicator: {
      type: Boolean,
      default: true,
    },
  },
  setup() {
    const store = useCloudUsersGroupsStore()
    return { store }
  },
  data() {
    return {
      inputValObjects: [],
      ajaxLoading: false,
      ncSelect: undefined,
    }
  },
  computed: {
    ...mapWritableState(useCloudUsersGroupsStore, ['users']),
    isLoading() {
      return (this.loading || this.ajaxLoading) && this.loadingIndicator
    },
    usersArray() {
      return Object.values(this.users)
    },
  },
  watch: {
    async value(newValue) {
      if (this.ajaxLoading) {
        return
      }
      if (newValue.length === 0) {
        this.inputValObjects = []
        return
      }
      this.ajaxLoading = true
      for (const userId of newValue) {
        if (!this.users[userId]) {
          await this.findUsers(userId)
        }
      }
      this.inputValObjects = await this.getValueObjects()
      this.ajaxLoading = false
    },
  },
  mounted() {
    this.ncSelect = this.$refs.select.ncSelect
  },
  methods: {
    info(...args) {
      console.info(this.$options.name, ...args)
    },
    getUserObject(userId) {
      return this.getUser(userId) || { id: userId, displayname: userId }
    },
    /**
     * Take the current value, fetch the users and again return the
     * same value (array of uids) in most cases. The idea is to fetch
     * the meta-info for each selected user in order to have a nice
     * display in the UI, including meta-info.
     */
    async getValueObjects() {
      const validValues = this.value.filter((userId) => userId !== '' && typeof userId !== 'undefined')
      const result = []
      for (const userId of validValues) {
        result.push(await this.getUserObject(userId))
      }
      return result.map((user) => user.id)
    },
    getUser(userId) {
      return this.store.getUser(userId, this.errorHandler)
    },
    findUsers(query) {
      const result = this.store.findUsers(query, this.errorHandler)
      return result
    },
    errorHandler(error) {
      this.$emit('error', error)
    },
  },
}
</script>
