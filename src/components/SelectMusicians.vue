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
 -->
<template>
  <SelectWithSubmitButton ref="select"
                          v-bind="$attrs"
                          v-model="inputValObjects"
                          label="formalDisplayName"
                          :options="musiciansArray"
                          :selectable="(option) => !isSelectAllSelected || option.id === 0"
                          :options-limit="100"
                          :placeholder="placeholder || label"
                          :input-label="label"
                          :loading="isLoading"
                          :multiple="multiple"
                          :clearable="clearable"
                          :clear-action="(!clearable && clearAction) || (multiple && clearAction)"
                          :reset-action="resetAction"
                          :reset-state="initialValObjects"
                          :searchable="searchable"
                          v-on="$listeners"
                          @search="findMusicians"
  >
    <template #option="option">
      <NcEllipsisedOption v-tooltip="musicianAddressPopup(option)"
                          :name="ncSelect ? String(option[ncSelect.localLabel]) : t(appName, 'undefined')"
                          :search="ncSelect ? ncSelect.search : t(appName, 'undefined')"
      />
    </template>
    <template #selected-option="option">
      <NcEllipsisedOption v-tooltip="musicianAddressPopup(option)"
                          :name="ncSelect ? String(option[ncSelect.localLabel]) : t(appName, 'undefined')"
                          :search="ncSelect ? ncSelect.search : t(appName, 'undefined')"
      />
    </template>
  </SelectWithSubmitButton>
</template>
<script>

import { set as vueSet } from 'vue'
import { appName } from '../app/app-info.js'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import musicianAddressPopup from '../mixins/address-popup.js'
import { usePersistentDataStore } from '../stores/persistentData.js'
import SelectWithSubmitButton from './SelectWithSubmitButton.vue'
import NcEllipsisedOption from '@nextcloud/vue/dist/Components/NcEllipsisedOption.js'

export default {
  name: 'SelectMusicians',
  components: {
    SelectWithSubmitButton,
    NcEllipsisedOption,
  },
  mixins: [
    musicianAddressPopup,
  ],
  inheritAttrs: false,
  props: {
    searchable: {
      type: Boolean,
      default: true,
    },
    searchScope: {
      type: String,
      default: 'musicians',
    },
    multiple: {
      type: Boolean,
      default: true,
    },
    label: {
      type: String,
      default: undefined,
    },
    value: {
      type: [Array, String, Object, Number],
      default: () => [],
    },
    clearable: {
      type: Boolean,
      default: true,
    },
    clearAction: {
      type: Boolean,
      default: true,
    },
    resetAction: {
      type: Boolean,
      default: false,
    },
    projectId: {
      type: Number,
      default: 0,
    },
    placeholder: {
      type: String,
      default: undefined,
    },
    selectAllOption: {
      type: Boolean,
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
  setup() {
    const persistentData = usePersistentDataStore()
    return { persistentData }
  },
  data() {
    return {
      inputValObjects: [],
      initialValObjects: [],
      musicians: {},
      ajaxLoading: false,
      ncSelect: undefined,
      id: null,
    }
  },
  computed: {
    isLoading() {
      return (this.loading || this.ajaxLoading) && this.loadingIndicator
    },
    musiciansArray() {
      return Object.values(this.musicians)
    },
    provideSelectAll() {
      return this.selectAllOption === undefined ? this.multiple : this.selectAllOption
    },
    isSelectAllSelected() {
      return this.provideSelectAll && this.inputValObjects.length === 1 && this.inputValObjects[0].id === 0
    },
  },
  watch: {
    async value(newValue) {
      if (this.ajaxLoading) {
        return
      }
      if (this.multiple) {
        if (newValue.length === 0) {
          this.inputValObjects = []
          return
        }
      } else {
        if (!newValue) {
          this.inputValObjects = null
          return
        }
        newValue = [newValue]
      }
      this.ajaxLoading = true
      for (const musician of newValue) {
        const musicianId = musician.id
        if (musicianId !== 0 && !this.musicians[musicianId]) {
          await this.findMusicians('', [musicianId])
          if (this.musicians[musician.id]) {
            const index = this.inputValObjects.findIndex((object) => object.id === musicianId)
            if (index >= 0) {
              this.inputValObjects.splice(index, 1, this.musicians[musicianId])
            }
          }
        }
      }
      if (newValue.length > 1 && newValue.findIndex((object) => object.id === 0) !== -1) {
        this.inputValObjects.splice(0, this.inputValObjects.length, this.musicians[0])
      }
      this.ajaxLoading = false
    },
    // setting the project id also resets the initial data.
    async projectId(/* newVal, oldVal */) {
      await this.getData()
    },
  },
  async created() {
    await this.getData()
  },
  mounted() {
    this.ncSelect = this.$refs.select.ncSelect
    this.id = this._uid
  },
  methods: {
    info(...args) {
      console.info(this.$options.name, ...args)
    },
    async getData() {
      if (this.ajaxLoading) {
        return
      }
      this.ajaxLoading = true
      this.resetMusicians()
      if (!this.searchable) {
        try {
          // console.info('PERSISTENT DATA', this.persistentData)
          this.musicians = this.persistentData.selectMusicians[this.searchScope][this.projectId]
          this.inputValObjects = this.getValueObject()
          if (this.resetButton) {
            this.initialValObjects = this.inputValObjects
          }
          this.ajaxLoading = false
          return
        } catch (ignoreMe) {}
      }
      await this.findMusicians('', this.getValueIds())
      this.inputValObjects = this.getValueObjects(true)
      if (this.resetButton) {
        this.initialValObjects = this.inputValObjects
      }
      if (!this.searchable) {
        this.persistentData.selectMusicians = {
          [this.searchScope]: {
            [this.projectId]: this.musicianIs,
          },
        }
      }
      this.ajaxLoading = false
    },
    resetMusicians() {
      this.musicians = {}
      if (this.provideSelectAll) {
        vueSet(this.musicians, 0, { id: 0, formalDisplayName: t(appName, '** everybody **') })
      }
    },
    getValueObjects(noUndefined) {
      const value = Array.isArray(this.value) ? this.value : (this.value || this.value === 0 ? [this.value] : [])
      let everybody = false
      let result = value.filter((musician) => musician !== '' && typeof musician !== 'undefined').map(
        (musician) => {
          const id = musician.id !== undefined ? musician.id : musician
          if (id === 0) {
            everybody = true
          }
          return this.musicians[id] || (noUndefined ? null : { id, formalDisplayName: id })
        },
      ).filter((musician) => musician !== null && musician !== undefined)
      if (this.provideSelectAll) {
        if (everybody) {
          result = [this.musicians[0]]
        }
      }
      return this.multiple ? result : (result.length > 0) ? result[0] : undefined
    },
    getValueIds() {
      const value = Array.isArray(this.value) ? this.value : [this.value]
      const result = value.filter((musician) => musician !== '' && typeof musician !== 'undefined').map(
        (musician) => {
          return musician.id !== undefined ? musician.id : musician
        },
      )
      // console.info('GET VALUE IDS', result)
      return result
    },
    async findMusicians(query, musicianIds) {
      query = typeof query === 'string' ? encodeURI(query) : ''
      if (query !== '') {
        query = '/' + query
      }
      const params = {
        limit: this.searchable ? 10 : null,
        scope: this.searchScope,
      }
      if (this.projectId > 0) {
        params.projectId = this.projectId
      }
      if (musicianIds !== undefined && musicianIds.length > 0) {
        params.ids = musicianIds
      }
      try {
        const response = await axios.get(generateUrl(`/apps/${appName}/musicians/search${query}`), { params })
        if (response.data.length > 0) {
          for (const musician of response.data) {
            vueSet(this.musicians, musician.id, musician)
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
<style lang="scss">
ul[id$="-projects-select__listbox"] {
  li.vs__dropdown-option.vs__dropdown-option--disabled {
    cursor: default; // var(--vs-state-disabled-cursor);
  }
}
</style>
