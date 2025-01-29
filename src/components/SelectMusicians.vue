<!--
 - Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2022, 2023, 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
                          v-model="inputValObjects"
                          v-bind="$attrs"
                          label="formalDisplayName"
                          :options="musiciansArray"
                          :selectable="isSelectable"
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
                          :name="ncSelect ? String(option[ncSelect.localLabel]) : t(appId, 'undefined')"
                          :search="ncSelect ? ncSelect.search : t(appId, 'undefined')"
      />
    </template>
    <template #selected-option="option">
      <NcEllipsisedOption v-tooltip="musicianAddressPopup(option)"
                          :name="ncSelect ? String(option[ncSelect.localLabel]) : t(appId, 'undefined')"
                          :search="ncSelect ? ncSelect.search : t(appId, 'undefined')"
      />
    </template>
  </SelectWithSubmitButton>
</template>
<script lang="ts">
import { set as vueSet } from 'vue'
import { appName } from '../config.ts'
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl as generateAppUrl } from '../toolkit/util/generate-url.js'
import musicianAddressPopup from '../mixins/address-popup.ts'
import consoleMixin from '../mixins/console.ts'
import l10nMixin from '../mixins/l10n.ts'
import { usePersistentDataStore } from '../stores/persistent-data.ts'
import SelectWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/SelectWithSubmitButton.vue'
import NcEllipsisedOption from '@nextcloud/vue/dist/Components/NcEllipsisedOption.js'
import type { AxiosResponse } from 'axios'
import type { Musician } from '../types/address-book.d.ts'
import type { PropType } from 'vue'

type SearchParameters = {
  limit: null|number,
  scope: string,
  projectId?: number,
  ids?: number[],
}

interface MusicianIdObject {
  id: number,
}

export default {
  name: 'SelectMusicians',
  components: {
    SelectWithSubmitButton,
    NcEllipsisedOption,
  },
  mixins: [
    musicianAddressPopup,
    consoleMixin,
    l10nMixin,
  ],
  inheritAttrs: false,
  props: {
    value: {
      type: [Array, Object] as PropType<null|Musician|Musician[]|MusicianIdObject|MusicianIdObject[]>,
      default: undefined,
    },
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
      inputValObjects: [] as undefined|Musician|Musician[],
      initialValObjects: [] as Musician|Musician[],
      musicians: {} as Record<number, Musician>,
      ajaxLoading: false,
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      ncSelect: undefined as any,
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
      return this.provideSelectAll
        && Array.isArray(this.inputValObjects)
        && this.inputValObjects.length === 1
        && this.inputValObjects[0].id === 0
    },
  },
  watch: {
    async value(newValue) {
      if (this.ajaxLoading) {
        return
      }
      if (this.multiple) {
        if (newValue.length === 0) {
          return
        }
      } else {
        if (!newValue) {
          return
        }
        newValue = [newValue]
      }
      this.ajaxLoading = true
      for (const musician of newValue as Musician[]) {
        const musicianId = musician.id
        if (musicianId !== 0 && !this.musicians[musicianId]) {
          await this.findMusicians('', [musicianId])
          if (this.musicians[musician.id]) {
            if (this.multiple) {
              const array = (this.inputValObjects || []) as Musician[]
              const index = array.findIndex((object) => object.id === musicianId)
              if (index >= 0) {
                array.splice(index, 1, this.musicians[musicianId])
              }
            } else {
              this.inputValObjects = this.musicians[musicianId]
            }
          }
        }
      }
      if (newValue.findIndex((object: Musician) => object.id === 0) !== -1) {
        const array = this.inputValObjects as Musician[]
        array.splice(0, array.length, this.musicians[0])
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
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    this.ncSelect = (this.$refs!.select! as any).ncSelect
    this.id = this._uid
  },
  methods: {
    isSelectable(option: Musician) {
      return !this.isSelectAllSelected || option.id === 0
    },
    async getData() {
      if (this.ajaxLoading) {
        return
      }
      this.ajaxLoading = true
      this.resetMusicians()
      if (!this.searchable) {
        try {
          this.musicians = this.persistentData.selectMusicians[this.searchScope][this.projectId] || {}
          this.inputValObjects = this.getValueObjects(false)
          if (this.resetButton) {
            this.initialValObjects = this.inputValObjects || []
          }
          this.ajaxLoading = false
          return
        } catch (ignoreMe) {
        }
      }
      await this.findMusicians('', this.getValueIds())
      this.inputValObjects = this.getValueObjects(true)
      if (this.resetButton) {
        this.initialValObjects = this.inputValObjects || []
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
    getValueObjects(noUndefined: boolean) {
      const value = Array.isArray(this.value) ? this.value : (this.value || this.value === 0 ? [this.value] : [])
      let everybody = false
      let result = value.filter((musician) => musician?.id).map(
        (musician) => {
          const id = musician.id
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
      const result = value.filter((musician) => musician?.id).map((musician) => +musician!.id)
      // console.info('GET VALUE IDS', result)
      return result
    },
    async findMusicians(query: string, musicianIds: number[]) {
      query = typeof query === 'string' ? encodeURI(query) : ''
      if (query !== '') {
        query = '/' + query
      }
      const params: SearchParameters = {
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
        const response: AxiosResponse<Musician[]> = await axios.get(generateAppUrl(`musicians/search${query}`), { params })
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
