<!--
  - @copyright Copyright (c) 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
  - @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
  -
  - @author Julius Härtl <jus@bitgrid.net>
  - @author Claus-Justus Heine <himself@claus-justus-heine.de>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
  <form class="select-musicians" @submit.prevent="">
    <div v-if="loading" class="loading" />
    <div class="input-wrapper">
      <label v-if="label !== undefined" :for="id">{{ label }}</label>
      <Multiselect :id="id"
                   ref="multiselect"
                   v-model="inputValObjects"
                   v-tooltip="active ? false : tooltip"
                   v-bind="$attrs"
                   :options="musiciansArray"
                   :options-limit="100"
                   :placeholder="placeholder === undefined ? label : placeholder"
                   :hint="hint"
                   :show-labels="true"
                   :searchable="searchable"
                   track-by="id"
                   label="formalDisplayName"
                   class="multiselect-vue"
                   :multiple="multiple"
                   :tag-width="60"
                   :disabled="disabled"
                   @input="emitInput"
                   @search-change="(query, id) => asyncFindMusicians(query)"
                   @open="active = true"
                   @close="active = false"
      >
        <template #option="optionData">
          <EllipsisedMusicianOption :name="$refs.multiselect.getOptionLabel(optionData.option)"
                                    :option="optionData.option"
                                    :search="optionData.search"
                                    :label="$refs.multiselect.label"
          />
        </template>
        <template #singleLabel="singleLabelData">
          <span v-tooltip="musicianAddressPopup(singleLabelData.option)">
            {{ $refs.multiselect.$refs.VueMultiselect.currentOptionLabel }}
          </span>
        </template>
        <template #tag="tagData">
          <span :key="tagData.option.id"
                v-tooltip="musicianAddressPopup(tagData.option)"
                class="multiselect__tag"
          >
            <span v-text="$refs.multiselect.getOptionLabel(tagData.option)" />
            <i tabindex="1"
               class="multiselect__tag-icon"
               @keypress.enter.prevent="tagData.remove(tagData.option)"
               @mousedown.prevent="tagData.remove(tagData.option)"
            />
          </span>
        </template>
      </Multiselect>
      <input v-if="clearButton"
             v-tooltip="t(appName, 'Remove all options.')"
             type="submit"
             class="clear-button icon-delete"
             value=""
             :disabled="disabled"
             @click="clearSelection"
      >
      <input v-if="resetButton && !clearButton"
             v-tooltip="t(appName, 'Reset to initial selection.')"
             type="submit"
             class="clear-button icon-history"
             value=""
             :disabled="disabled"
             @click="resetSelection"
      >
    </div>
    <p v-if="hint !== ''" class="hint">
      {{ hint }}
    </p>
  </form>
</template>

<script>

import Vue from 'vue'
import { appName } from '../app/app-info.js'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import EllipsisedMusicianOption from './EllipsisedMusicianOption'
import addressPopup from '../mixins/address-popup'
import { usePersistentDataStore } from '../stores/persistentData'

let uuid = 0
export default {
  name: 'SelectMusicians',
  components: {
    Multiselect,
    EllipsisedMusicianOption,
  },
  mixins: [
    addressPopup,
  ],
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
    hint: {
      type: String,
      default: '',
    },
    value: {
      type: [Array, String, Object, Number],
      default: () => [],
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    clearButton: {
      type: Boolean,
      default: true,
    },
    resetButton: {
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
    tooltip: {
      type: [Object, String, Boolean],
      default: undefined,
    },
    selectAllOption: {
      type: Boolean,
      default: undefined,
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
      loading: true,
      loadingPromise: Promise.resolve(),
      active: false,
    }
  },
  computed: {
    id() {
      return 'settings-musicians-' + this.uuid
    },
    musiciansArray() {
      return Object.values(this.musicians)
    },
    provideSelectAll() {
      return this.selectAllOption === undefined ? this.multiple : this.selectAllOption
    },
  },
  watch: {
    value(newVal, oldVal) {
      this.loadingPromise.finally(() => {
        this.inputValObjects = this.getValueObject()
        if (this.provideSelectAll && this.inputValObjects.length === 1 && this.inputValObjects[0].id === 0) {
          this.$refs.multiselect.$refs.VueMultiselect.deactivate()
        }
      })
    },
    // setting the project id also resets the initial data.
    projectId(newVal, oldVal) {
      this.getData()
    },
  },
  created() {
    this.uuid = uuid.toString()
    uuid += 1
    this.getData()
  },
  methods: {
    getData() {
      this.loadingPromise.finally(() => {
        this.loadingPromise = new Promise((resolve, reject) => {
          this.loading = true
          this.resetMusicians()
          if (!this.searchable) {
            try {
              // console.info('PERSISTENT DATA', this.persistentData)
              this.musicians = this.persistentData.selectMusicians[this.searchScope][this.projectId]
              this.inputValObjects = this.getValueObject()
              if (this.resetButton) {
                this.initialValObjects = this.inputValObjects
              }
              this.loading = false
              resolve(this.loading)
              // console.info('INIT FROM STORE', this.searchScope, this.projectId)
              return
            } catch (ignoreMe) {}
          }
          this.asyncFindMusicians('', this.getValueIds()).then((result) => {
            this.inputValObjects = this.getValueObject(true)
            if (this.resetButton) {
              this.initialValObjects = this.inputValObjects
            }
            if (!this.searchable) {
              this.persistentData.selectMusicians = {
                [this.searchScope]: {
                  [this.projectId]: this.musicians,
                },
              }
            }
            this.loading = false
            resolve(this.loading)
            // console.info('INIT FROM AJAX', this.searchScope, this.projectId)
          })
        })
      })
    },
    resetMusicians() {
      this.musicians = {}
      if (this.provideSelectAll) {
        Vue.set(this.musicians, 0, { id: 0, formalDisplayName: t(appName, '** everybody **') })
      }
    },
    getValueObject(noUndefined) {
      // console.info('VALUE', this.value)
      const value = Array.isArray(this.value) ? this.value : (this.value || this.value === 0 ? [this.value] : [])
      let everybody = false
      let result = value.filter((musician) => musician !== '' && typeof musician !== 'undefined').map(
        (musician) => {
          const id = musician.id !== undefined ? musician.id : musician
          if (id === 0) {
            everybody = true
          }
          if (typeof this.musicians[id] === 'undefined') {
            return noUndefined ? null : { id, formalDisplayName: id }
          }
          return this.musicians[id]
        }
      ).filter((musician) => musician !== null && musician !== undefined)
      if (this.provideSelectAll) {
        if (everybody) {
          result = [this.musicians[0]]
        }
        for (const [musicianId, musician] of Object.entries(this.musicians)) {
          if (musicianId !== 0 && musicianId !== '0') {
            musician.$isDisabled = everybody
          }
        }
      }
      return this.multiple ? result : (result.length > 0) ? result[0] : undefined
    },
    getValueIds() {
      const value = Array.isArray(this.value) ? this.value : [this.value]
      const result = value.filter((musician) => musician !== '' && typeof musician !== 'undefined').map(
        (musician) => {
          return musician.id !== undefined ? musician.id : musician
        }
      )
      // console.info('GET VALUE IDS', result)
      return result
    },
    clearSelection() {
      this.inputValObjects = []
      this.emitInput() // why is this needed?
    },
    resetSelection() {
      this.inputValObjects = this.initialValObjects
      this.emitInput() // why is this needed?
    },
    emitInput() {
      this.emit('input')
      this.emitUpdate()
    },
    emitUpdate() {
      this.emit('update')
    },
    emit(event) {
      this.$emit(event, this.inputValObjects)
    },
    asyncFindMusicians(query, musicianIds) {
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
      return axios
        .get(generateUrl(`/apps/${appName}/musicians/search${query}`), { params })
        .then((response) => {
          if (response.data.length > 0) {
            for (const musician of response.data) {
              Vue.set(this.musicians, musician.id, musician)
            }
            return true
          }
          return false
        }).catch((error) => {
          this.$emit('error', error)
        })
    },
  },
}
</script>
<style lang="scss" scoped>
.select-musicians {
  position:relative;
  .loading {
    position:absolute;
    width:0;
    height:0;
    top:50%;
    left:50%;
  }
  .input-wrapper {
    display: flex;
    flex-wrap: wrap;
    width: 100%;
    max-width: 400px;
    align-items: center;
    div.multiselect.multiselect-vue::v-deep {
      &:not(.multiselect--active) {
        height:35.2px;
      }
      flex-grow:1;
      &:hover .multiselect__tags {
        border-color: var(--color-primary-element);
        outline: none;
      }
      &:hover + .clear-button {
        border-color: var(--color-primary-element) !important;
        border-left-color: transparent !important;
        z-index: 2;
      }
      &.multiselect--active + .clear-button {
        display:none;
      }
      + .clear-button {
        &:disabled {
          background-color: var(--color-background-dark) !important;
        }
        margin-left: -8px !important;
        border-left-color: transparent !important;
        border-radius: 0 var(--border-radius) var(--border-radius) 0 !important;
        background-clip: padding-box;
        background-color: var(--color-main-background) !important;
        opacity: 1;
        padding: 7px 6px;
        height:35.2px;
        width:35.2px;
        margin-right:0;
        z-index:2;
        &:hover, &:focus {
          border-color: var(--color-primary-element) !important;
          border-radius: var(--border-radius) !important;
        }
      }

      &.multiselect--single {
        .multiselect__content-wrapper li > span {
          &::before {
            background-image: var(--icon-checkmark-000);
            display:block;
          }
          &:not(.multiselect__option--selected):hover::before {
            visibility:hidden;
          }
        }
      }
      &.multiselect--multiple {
        .multiselect__content-wrapper li > span {
          &:not(.multiselect__option--selected):hover::before {
            visibility:hidden;
          }
        }
      }

      .multiselect__tag {
        position: relative;
        padding-right: 18px;
        .multiselect__tag-icon {
          cursor: pointer;
          margin-left: 7px;
          position: absolute;
          right: 0;
          top: 0;
          bottom: 0;
          font-weight: 700;
          font-style: initial;
          width: 22px;
          text-align: center;
          line-height: 22px;
          transition: all 0.2s ease;
          border-radius: 5px;
        }

        .multiselect__tag-icon:after {
          content: "×";
          color: #266d4d;
          font-size: 14px;
        }
      }
    }

    label {
      width: 100%;
    }
  }

  .hint {
    color: var(--color-text-lighter);
    font-size:80%;
  }
}
</style>
<style>
.vue-tooltip-address-popup.vue-tooltip .tooltip-inner {
  text-align: left !important;
}
</style>
