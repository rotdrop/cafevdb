<!--
  - @copyright Copyright (c) 2019, 2022 Julius Härtl <jus@bitgrid.net>
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
    <div class="input-wrapper">
      <label :for="id">{{ label }}</label>
      <Multiselect :id="id"
                   v-model="inputValObject"
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
                   :allow-empty="false"
                   :deselect-label="t('Cannot deselect')"
                   @input="emitInput"
                   @search-change="asyncFindGroup"
      />
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
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'

let uuid = 0
export default {
  name: 'SettingsSelectGroup',
  components: {
    Multiselect,
  },
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
  },
  data() {
    return {
      inputValObject: {},
      currentInputValObject: {},
      groups: {},
    }
  },
  computed: {
    id() {
      return 'settings-select-group-' + this.uuid
    },
    groupsArray() {
      return Object.values(this.groups)
    },
  },
  watch: {
    value(newVal) {
      this.inputValObject = this.getValueObject()
    },
  },
  created() {
    this.uuid = uuid.toString()
    uuid += 1
    this.asyncFindGroup('').then((result) => {
      this.inputValObject = this.getValueObject()
    })
  },
  methods: {
    getValueObject() {
      const id = this.value
      if (typeof this.groups[id] === 'undefined') {
        return {
          id,
          displayname: id,
        }
      }
      return this.groups[id]
    },
    emitInput() {
      console.info('INPUT INPUTVAL', this)
      if (this.inputValObject) {
        this.$emit('input', this.inputValObject.id)
      }
    },
    emitUpdate() {
      console.info('UPDATE INPUTVAL', this.inputValObject)
      this.$emit('update', this.inputValObject.id)
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
<style lang="scss">
  .settings-select-group {
    .input-wrapper {
      display: flex;
      flex-wrap: wrap;
      width: 100%;
      max-width: 400px;
      align-items: center;
      div.multiselect.multiselect-vue.multiselect--single {
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
