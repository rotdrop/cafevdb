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
                          v-model="inputValObjects"
                          :tooltip="tooltip"
                          :options="usersArray"
                          :options-limit="100"
                          :placeholder="label"
                          :input-label="label"
                          :clearable="clearable"
                          label="displayname"
                          :multiple="true"
                          :close-on-select="false"
                          :disabled="disabled"
                          :user-select="true"
                          @update="emitUpdate"
                          @input="emitInput"
                          @search="findUsers"
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
import { appName } from '../app/app-info.js'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import SelectWithSubmitButton from './SelectWithSubmitButton.vue'
import { NcListItemIcon } from '@nextcloud/vue'
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
  props: {
    label: {
      type: String,
      required: true,
    },
    hint: {
      type: String,
      default: '',
    },
    value: {
      type: Array,
      default: () => [],
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    clearable: {
      type: Boolean,
      default: true,
    },
    required: {
      type: Boolean,
      default: false,
    },
    tooltip: {
      type: [Object, String, Boolean],
      default: undefined,
    },
    loadingIndicator: {
      type: Boolean,
      default: true,
    },
  },
  data() {
    return {
      inputValObjects: [],
      users: {},
      loading: false,
      ncSelect: undefined,
    }
  },
  computed: {
    isLoading() {
      return this.loading && this.loadingIndicator
    },
    usersArray() {
      return Object.values(this.users)
    },
    userIds() {
      return this.inputValObjects.map((element) => element.id)
    },
    empty() {
      return !this.inputValObjects || (Array.isArray(this.inputValObjects) && this.inputValObjects.length === 0)
    },
  },
  watch: {
    async value(newValue, oldValue) {
      if (this.loading) {
        this.info('Skipping watch action during load')
        return
      }
      if (newValue.length === 0) {
        this.inputValObjects = []
        return
      }
      this.info('VALUE HAS CHANGED', newValue, oldValue)
      this.loading = true
      for (const userId of newValue) {
        if (!this.users[userId]) {
          await this.findUsers(userId)
        }
      }
      this.inputValObjects = this.getValueObject()
      this.loading = false
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
      return this.users[userId] || { id: userId, displayname: userId }
    },
    getValueObject() {
      return this.value.filter((user) => user !== '' && typeof user !== 'undefined').map((userId) => this.getUserObject(userId))
    },
    emitInput() {
      if (Array.isArray(this.inputValObjects) && (this.clearable || !this.empty)) {
        this.$emit('input', this.userIds)
      }
    },
    emitUpdate() {
      if (this.required && this.empty) {
        this.$emit('error', t(appName, 'An empty value is not allowed, please make your choice!'))
      } else {
        this.$emit('update', this.userIds)
      }
    },
    async findUsers(query) {
      query = typeof query === 'string' ? encodeURI(query) : ''
      try {
        const response = await axios.get(generateOcsUrl(`cloud/users/details?search=${query}&limit=10`, 2))
        if (Object.keys(response.data.ocs.data.users).length > 0) {
          for (const element of Object.values(response.data.ocs.data.users)) {
            const uid = element.id
            if (!this.users[uid] || JSON.stringify(this.users[uid]) !== JSON.stringify(element)) {
              this.$set(this.users, uid, element)
            }
          }
          return true
        }
      } catch (error) {
        this.$emit('error', error)
      }
      return false
    },
  },
}
</script>
