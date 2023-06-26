<!--
  - @copyright Copyright (c) 2019, 2022, 2023 Julius Härtl <jus@bitgrid.net>
  - @copyright Copyright (c) 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  <form class="settings-select-group" @submit.prevent="">
    <div :class="['input-wrapper', { empty, required }]">
      <div v-if="showLoadingIndicator" class="loading" />
      <label :for="id">{{ label }}</label>
      <Multiselect :id="id"
                   ref="multiselect"
                   v-model="inputValObject"
                   v-tooltip="tooltipToShow"
                   :value="inputValObject"
                   :options="groupsArray"
                   :options-limit="100"
                   :placeholder="label"
                   :hint="hint"
                   track-by="id"
                   label="displayname"
                   class="multiselect-vue"
                   :multiple="false"
                   :close-on-select="true"
                   :tag-width="60"
                   :disabled="disabled"
                   :show-labels="true"
                   :allow-empty="allowEmpty"
                   :deselect-label="t('Cannot deselect')"
                   @input="emitInput"
                   @search-change="asyncFindGroup"
                   @open="active = true"
                   @close="active = false"
      >
        <template #option="optionData">
          <EllipsisedCloudGroupOption :name="$refs.multiselect.getOptionLabel(optionData.option)"
                                      :option="optionData.option"
                                      :search="optionData.search"
                                      :label="$refs.multiselect.label"
          />
        </template>
        <template #singleLabel="singleLabelData">
          <span v-tooltip="groupInfoPopup(singleLabelData.option)">
            {{ $refs.multiselect.$refs.VueMultiselect.currentOptionLabel }}
          </span>
        </template>
      </Multiselect>
      <input type="submit"
             class="icon-confirm"
             value=""
             @click="emitUpdate"
      >
    </div>
    <p v-if="hint !== ''" class="hint">
      {{ hint }}
    </p>
  </form>
</template>

<script>
import { appName } from '../app/app-info.js'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import Multiselect from '@nextcloud/vue/dist/Components/NcMultiselect'
import userInfoPopup from '../mixins/user-info-popup.js'
import EllipsisedCloudGroupOption from './EllipsisedCloudGroupOption.vue'

let uuid = 0
export default {
  name: 'SettingsSelectGroup',
  components: {
    Multiselect,
    EllipsisedCloudGroupOption,
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
    // allowEmpty allows deselection of the last item
    allowEmpty: {
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
    loadingIndicator: {
      type: Boolean,
      default: true,
    },
  },
  data() {
    return {
      inputValObject: null,
      groups: {},
      loading: true,
      loadingPromise: Promise.resolve(),
      active: false,
    }
  },
  computed: {
    id() {
      return 'settings-select-group-' + this.uuid
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
    showLoadingIndicator() {
      return this.loadingIndicator && this.loading
    },
    tooltipToShow() {
      if (this.active) {
        return false
      }
      if (this.tooltip) {
        return this.tooltip
      }
      if (this.empty && this.required) {
        return t(appName, 'Please select a group!')
      }
      return false
    },
  },
  watch: {
    value(newVal) {
      this.loadingPromise.finally(() => {
        this.inputValObject = this.getValueObject()
      })
    },
  },
  created() {
    this.uuid = uuid.toString()
    uuid += 1
    this.loadingPromise.finally(() => {
      this.loadingPromise = new Promise((resolve, reject) => {
        this.loading = true
        this.asyncFindGroup('').then((result) => {
          this.inputValObject = this.getValueObject()
          this.loading = false
          resolve(this.loading)
        })
      })
    })
  },
  methods: {
    getValueObject() {
      const id = this.value
      if (!id) {
        return null
      }
      if (typeof this.groups[id] === 'undefined') {
        return {
          id,
          displayname: id,
        }
      }
      return this.groups[id]
    },
    emitInput() {
      if (this.allowEmpty || !this.empty) {
        this.$emit('input', this.groupId)
      }
    },
    emitUpdate() {
      if (this.required && this.empty) {
        this.$emit('error', t(appName, 'An empty value is not allowed, please make your choice!'))
      } else {
        this.$emit('update', this.groupId)
      }
    },
    asyncFindGroup(query) {
      query = typeof query === 'string' ? encodeURI(query) : ''
      return axios.get(generateOcsUrl(`cloud/groups/details?search=${query}&limit=10`, 2))
        .then((response) => {
          if (Object.keys(response.data.ocs.data.groups).length > 0) {
            response.data.ocs.data.groups.forEach((element) => {
              if (typeof this.groups[element.id] === 'undefined') {
                this.$set(this.groups, element.id, element)
              }
            })
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
.settings-select-group {
  .input-wrapper {
    position:relative;
    .loading {
      position:absolute;
      width:0;
      height:0;
      top:50%;
      left:50%;
    }
    &.empty.required {
      div.multiselect.multiselect-vue::v-deep .multiselect__tags {
        border-left: 1px solid red;
        border-top: 1px solid red;
        border-bottom: 1px solid red;
      }
      .icon-confirm {
        border-right: 1px solid red;
        border-top: 1px solid red;
        border-bottom: 1px solid red;
      }
    }
    display: flex;
    flex-wrap: wrap;
    width: 100%;
    max-width: 400px;
    align-items: center;
    div.multiselect.multiselect-vue.multiselect--single::v-deep {
      height:34px;
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
      + .icon-confirm {
        margin-left: -8px !important;
        border-left-color: transparent !important;
        border-radius: 0 var(--border-radius) var(--border-radius) 0 !important;
        background-clip: padding-box;
        background-color: var(--color-main-background) !important;
        opacity: 1;
        padding: 7px 6px;
        height:34px;
        width:34px;
        margin-right:0;
        z-index:2;
        &:hover, &:focus {
          border-color: var(--color-primary-element) !important;
          border-radius: var(--border-radius) !important;
        }
      }

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
