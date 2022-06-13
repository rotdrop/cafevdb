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
      <label :for="id">{{ label }}</label>
      <Multiselect :id="id"
                   ref="multiselect"
                   v-model="inputValObjects"
                   v-bind="$attrs"
                   :options="musiciansArray"
                   :options-limit="100"
                   :placeholder="label"
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
      />
      <input v-if="submitButton"
             type="submit"
             class="icon-confirm"
             value=""
             :disabled="disabled"
             @click="emitUpdate"
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

let uuid = 0
export default {
  name: 'SelectMusicians',
  components: {
    Multiselect,
  },
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
      required: true,
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
    submitButton: {
      type: Boolean,
      default: true,
    },
    projectId: {
      type: Number,
      default: 0,
    },
  },
  data() {
    return {
      inputValObjects: [],
      musicians: {},
      loading: true,
    }
  },
  computed: {
    id() {
      return 'settings-musicians-' + this.uuid
    },
    musiciansArray() {
      return Object.values(this.musicians)
    },
  },
  watch: {
    value(newVal, oldVal) {
      this.inputValObjects = this.getValueObject()
      if (this.multiple && this.inputValObjects.length === 1 && this.inputValObjects[0].id === 0) {
        this.$refs.multiselect.$refs.VueMultiselect.deactivate()
      }
    },
    projectId(newVal) {
      this.loading = true
      this.resetMusicians()
      this.asyncFindMusicians('', this.getValueIds()).then((result) => {
        this.inputValObjects = this.getValueObject(true)
        this.loading = false
      })
    },
  },
  created() {
    this.uuid = uuid.toString()
    uuid += 1
    this.resetMusicians()
    this.asyncFindMusicians('', this.getValueIds()).then((result) => {
      this.inputValObjects = this.getValueObject()
      this.loading = false
    })
  },
  methods: {
    resetMusicians() {
      this.musicians = {}
      if (this.multiple) {
        Vue.set(this.musicians, 0, { id: 0, formalDisplayName: t(appName, '** everybody **') })
      }
    },
    getValueObject(noUndefined) {
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
      if (this.multiple) {
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
      return result
    },
    emitInput() {
      this.emit('input')
      if (!this.submitButton) {
        this.emitUpdate()
      }
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
        limit: 10,
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
      &:hover + .icon-confirm {
        border-color: var(--color-primary-element) !important;
        border-left-color: transparent !important;
        z-index: 2;
      }
      &.multiselect--active + .icon-confirm {
        display:none;
      }
      + .icon-confirm {
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
