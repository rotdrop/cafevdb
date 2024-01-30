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
  <form v-tooltip="tooltip" class="settings-select-users" @submit.prevent="">
    <div :class="['input-wrapper', { empty, required, loading }]">
      <label :for="id">{{ label }}</label>
      <div class="select-combo-wrapper">
        <NcSelect :id="id"
                  ref="ncselect"
                  v-model="inputValObjects"
                  :options="usersArray"
                  :options-limit="100"
                  :placeholder="label"
                  :label-outside="true"
                  label="displayname"
                  class="multiselect-vue"
                  :multiple="true"
                  :close-on-select="false"
                  :disabled="disabled"
                  :user-select="true"
                  @input="emitInput"
                  @search-change="asyncFindUser"
                  @open="active = true"
                  @close="active = false"
        >
          <!-- Unfortunately, the stock NcSelect seems to be somewhat borken. -->
          <template #option="option">
            <NcListItemIcon v-tooltip="userInfoPopup(option)"
                            v-bind="option"
                            :user="option.id"
                            :avatar-size="24"
                            :name="option[$refs.ncselect.localLabel]"
                            :search="$refs.ncselect.search"
            />
          </template>
          <template #selected-option="selectedOption">
            <NcListItemIcon v-tooltip="userInfoPopup(selectedOption)"
                            v-bind="selectedOption"
                            :user="selectedOption.id"
                            :avatar-size="24"
                            :name="selectedOption[$refs.ncselect.localLabel]"
                            :search="$refs.ncselect.search"
            />
          </template>
        </NcSelect>
        <input v-tooltip="t(appName, 'Click to submit your changes.')"
               type="submit"
               class="icon-confirm"
               value=""
               :disabled="disabled"
               @click="emitUpdate"
        >
      </div>
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
import { NcSelect, NcListItemIcon/*, Tooltip */ } from '@nextcloud/vue'
import userInfoPopup from '../mixins/user-info-popup.js'

let uuid = 0
export default {
  name: 'SettingsSelectUsers',
  components: {
    NcSelect,
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
    value(/* newVal, oldVal */) {
      this.loadingPromise.finally(() => {
        this.inputValObjects = this.getValueObject()
      })
    },
  },
  created() {
    this.uuid = uuid.toString()
    uuid += 1
    this.loadingPromise.finally(() => {
      this.loadingPromise = new Promise((resolve/* , reject */) => {
        this.loading = true
        this.asyncFindUser('').then((/* result */) => {
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
        },
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
    display: flex;
    flex-wrap: wrap;
    width: 100%;
    // max-width: 400px;
    align-items: center;
    .loading-indicator.loading {
      position:absolute;
      width:0;
      height:0;
      top:50%;
      left:50%;
    }
    label {
      width: 100%;
    }
    .select-combo-wrapper {
      display: flex;
      align-items: stretch;
      flex-grow: 1;
      flex-wrap: nowrap;
      .v-select.select::v-deep {
        flex-grow:1;
        max-width:100%;
        .vs__dropdown-toggle {
          // substract the round borders for the overlay
          padding-right: calc(var(--default-clickable-area) - var(--vs-border-radius));
        }
        + .icon-confirm {
          flex-shrink: 0;
          width:var(--default-clickable-area);
          align-self: stretch;
          // align-self: stretch should do what we want here :)
          // height:var(--default-clickable-area);
          margin: 0 0 0 calc(0px - var(--default-clickable-area));
          z-index: 2;
          border-radius: var(--vs-border-radius) var(--vs-border-radius);
          border-style: none;
          background-color: rgba(0, 0, 0, 0);
          background-clip: padding-box;
          opacity: 1;
          &:hover, &:focus {
            border: var(--vs-border-width) var(--vs-border-style) var(--color-primary-element);
            border-radius: var(--vs-border-radius);
            outline: 2px solid var(--color-main-background);
            background-color: var(--vs-search-input-bg);
          }
        }
      }
      &.empty.required:not(.loading) {
        .v-select.select::v-deep .vs__dropdown-toggle {
          border-color: red;
        }
      }
    }
  }

  .hint {
    color: var(--color-text-lighter);
    font-size:80%;
  }
}
</style>
<style lang="scss">
// in vue-select anything starting from
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
