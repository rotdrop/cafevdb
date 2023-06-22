<script>
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022, 2023 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
</script>
<template>
  <form class="settings-select-users" @submit.prevent="">
    <div :class="['input-wrapper', { empty, required }]">
      <div v-if="showLoadingIndicator" class="loading" />
      <label :for="id">{{ label }}</label>
      <Multiselect :id="id"
                   ref="multiselect"
                   v-model="inputValObjects"
                   v-tooltip="active ? false : tooltip"
                   :options="usersArray"
                   :options-limit="100"
                   :placeholder="label"
                   :hint="hint"
                   track-by="id"
                   label="displayname"
                   class="multiselect-vue"
                   :multiple="true"
                   :close-on-select="false"
                   :tag-width="60"
                   :disabled="disabled"
                   @input="emitInput"
                   @search-change="asyncFindUser"
                   @open="active = true"
                   @close="active = false"
      >
        <template #option="optionData">
          <EllipsisedCloudUserOption :name="$refs.multiselect.getOptionLabel(optionData.option)"
                                     :option="optionData.option"
                                     :search="optionData.search"
                                     :label="$refs.multiselect.label"
          />
        </template>
        <template #tag="tagData">
          <span :key="tagData.option.id"
                v-tooltip="userInfoPopup(tagData.option)"
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
      <input type="submit"
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
import { appName } from '../app/app-info.js'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect.js'
import userInfoPopup from '../mixins/user-info-popup.js'
import EllipsisedCloudUserOption from './EllipsisedCloudUserOption.vue'

let uuid = 0
export default {
  name: 'SettingsSelectUsers',
  components: {
    Multiselect,
    EllipsisedCloudUserOption,
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
    allowEmpty: {
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
      loading: true,
      loadingPromise: Promise.resolve(),
      active: false,
    }
  },
  computed: {
    id() {
      return 'settings-select-user-' + this.uuid
    },
    usersArray() {
      return Object.values(this.users)
    },
    empty() {
      return !this.inputValObjects || (Array.isArray(this.inputValObjects) && this.inputValObjects.length === 0)
    },
    showLoadingIndicator() {
      return this.loadingIndicator && this.loading
    },
  },
  watch: {
    value(newVal) {
      this.loadingPromise.finally(() => {
        this.inputValObjects = this.getValueObject()
      })
    },
  },
  created() {
    this.uuid = uuid.toString()
    uuid += 1
    this.loadingPromise.finally(() => {
      this.loadingPromise = new Promise((resolve, reject) => {
        this.loading = true
        this.asyncFindUser('').then((result) => {
          this.inputValObjects = this.getValueObject()
          this.loading = false
          resolve(this.loading)
        })
      })
    })
  },
  methods: {
    getValueObject() {
      return this.value.filter((user) => user !== '' && typeof user !== 'undefined').map(
        (id) => {
          if (typeof this.users[id] === 'undefined') {
            return {
              id,
              displayname: id,
            }
          }
          return this.users[id]
        }
      )
    },
    emitInput() {
      if (Array.isArray(this.inputValObjects) && (this.allowEmpty || !this.empty)) {
        this.$emit('input', this.inputValObjects.map((element) => element.id))
      }
    },
    emitUpdate() {
      if (this.required && this.empty) {
        this.$emit('error', t(appName, 'An empty value is not allowed, please make your choice!'))
      } else {
        this.$emit('update', this.inputValObjects.map((element) => element.id))
      }
    },
    asyncFindUser(query) {
      query = typeof query === 'string' ? encodeURI(query) : ''
      return axios.get(generateOcsUrl(`cloud/users/details?search=${query}&limit=10`, 2))
        .then((response) => {
          if (Object.keys(response.data.ocs.data.users).length > 0) {
            Object.values(response.data.ocs.data.users).forEach((element) => {
              if (typeof this.users[element.id] === 'undefined') {
                this.$set(this.users, element.id, element)
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
.settings-select-users {
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
    div.multiselect.multiselect-vue.multiselect--multiple::v-deep {
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

      .multiselect__content-wrapper li > span {
        &:not(.multiselect__option--selected):hover::before {
          visibility:hidden;
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
.vue-tooltip-user-info-popup.vue-tooltip .tooltip-inner {
  text-align: left !important;
  *:not(h4) {
    color: var(--color-text-lighter);
    font-size:80%;
  }
  h4 {
    font-weight: bold;
  }
}
</style>
