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
 - Wrap an NcSelect into a coponent with submit button.
 -->
<template>
  <SelectWithSubmitButton ref="select"
                          v-model="inputValObject"
                          :tooltip="tooltip"
                          :options="groupsArray"
                          :options-limit="100"
                          :placeholder="label"
                          :input-label="label"
                          :loading="isLoading"
                          :hint="hint"
                          label="displayname"
                          :multiple="false"
                          :close-on-select="true"
                          :disabled="disabled"
                          :clearable="clearable"
                          @update="emitUpdate"
                          @search="findGroups"
  >
    <template #option="option">
      <NcEllipsisedOption v-tooltip="groupInfoPopup(option)"
                          :name="ncSelect ? String(option[ncSelect.localLabel]) : t(appName, 'undefined')"
                          :search="ncSelect ? ncSelect.search : t(appName, 'undefined')"
      />
    </template>
    <template #selected-option="option">
      <NcEllipsisedOption v-tooltip="groupInfoPopup(option)"
                          :name="ncSelect ? String(option[ncSelect.localLabel]) : t(appName, 'undefined')"
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
import NcEllipsisedOption from '@nextcloud/vue/dist/Components/NcEllipsisedOption.js'
import userInfoPopup from '../mixins/user-info-popup.js'

export default {
  name: 'SettingsSelectGroup',
  components: {
    SelectWithSubmitButton,
    NcEllipsisedOption,
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
      type: String,
      default: '',
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    // clearable allows deselection of the last item
    clearable: {
      type: Boolean,
      default: true,
    },
    // required blocks the final submit if no value is selected
    required: {
      type: Boolean,
      default: false,
    },
    tooltip: {
      type: [Object, String, Boolean],
      default: undefined,
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
  data() {
    return {
      inputValObject: null,
      groups: {},
      ajaxLoading: false,
      ncSelect: undefined,
    }
  },
  computed: {
    isLoading() {
      return (this.loading || this.ajaxLoading) && this.loadingIndicator
    },
    groupsArray() {
      return Object.values(this.groups)
    },
    groupId() {
      return this.empty ? '' : this.inputValObject.id
    },
    empty() {
      return !this.inputValObject || !this.inputValObject.id
    },
  },
  watch: {
    /**
     * This watcher catches changed property values and promotes the
     * changed value to the wrapped select.
     *
     * @param {string} newValue New GID set from outside
     */
    async value(newValue) {
      if (this.ajaxLoading) {
        return
      }
      if (!newValue) {
        this.inputValObject = null
        return
      }
      this.ajaxLoading = true
      if (!this.groups[newValue]) {
        await this.findGroups(newValue)
      }
      this.inputValObject = this.getGroupObject(newValue)
      this.ajaxLoading = false
    },
    inputValObject() {
      if (this.ajaxLoading) {
        return
      }
      this.emitInput()
    },
  },
  mounted() {
    this.ncSelect = this.$refs.select.ncSelect
  },
  methods: {
    info(...args) {
      console.info(this.$options.name, ...args)
    },
    select() {
      return this.$refs?.select?.$refs?.ncSelect
    },
    getGroupObject(gid) {
      return this.groups[gid] || { id: gid, displayname: gid }
    },
    emitInput() {
      if (this.clearable || !this.empty) {
        this.$emit('input', this.groupId)
        this.$emit('update:modelValue', this.groupId)
      }
    },
    emitUpdate() {
      if (this.required && this.empty) {
        this.$emit('error', t(appName, 'An empty value is not allowed, please make your choice!'))
      } else {
        this.$emit('update', this.groupId)
      }
    },
    async findGroups(query) {
      query = typeof query === 'string' ? encodeURI(query) : ''
      try {
        const limit = 10
        let count = 0
        let offset = 0
        while (count < 10) {
          const response = await axios.get(generateOcsUrl(`cloud/groups/details?search=${query}&limit=${limit}&offset=${offset}`, 2))
          for (const element of response.data.ocs.data.groups) {
            if (!element.id) {
              // if we were not a group admin, an empty entry is returned in order to enable paging
              continue
            }
            ++count
            const gid = element.id
            if (!this.groups[gid] || JSON.stringify(this.groups[gid]) !== JSON.stringify(element)) {
              this.$set(this.groups, gid, element)
            }
          }
          if (Object.keys(response.data.ocs.data.groups).length < limit) {
            break
          }
          offset += limit
        }
        return true
      } catch (error) {
        this.$emit('error', error)
      }
      return false
    },
  },
}
</script>
