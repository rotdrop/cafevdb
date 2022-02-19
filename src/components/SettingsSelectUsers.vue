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
  <form class="settings-select-users" @submit.prevent="">
    <div class="input-wrapper">
      <label :for="id">{{ label }}</label>
      <Multiselect v-model="inputValObjects"
                   :id="id"
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
                   @search-change="asyncFindUser">
      </Multiselect>
      <input type="submit"
             class="icon-confirm"
             value=""
             :disabled="disabled"
             @click="emitUpdate">
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
   name: 'SettingsSelectUsers',
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
       type: Array,
       default: () => [],
     },
     disabled: {
       type: Boolean,
       default: false,
     },
   },
   data() {
     return {
       inputValObjects: [],
       users: {},
     }
   },
   computed: {
     id() {
       return 'settings-select-user-' + this.uuid
     },
     usersArray() {
       return Object.values(this.users)
     },
   },
   watch: {
     value(newVal) {
       this.inputValObjects = this.getValueObject()
     },
   },
   created() {
     this.uuid = uuid.toString()
     uuid += 1
     this.asyncFindUser('').then((result) => {
       this.inputValObjects = this.getValueObject()
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
       if (this.inputValObject) {
         this.$emit('input', this.inputValObjects.map((element) => element.id))
       }
     },
     emitUpdate() {
       this.$emit('update', this.inputValObjects.map((element) => element.id))
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
<style lang="scss">
  .settings-select-users {
    .input-wrapper {
      display: flex;
      flex-wrap: wrap;
      width: 100%;
      max-width: 400px;
      align-items: center;
      div.multiselect.multiselect-vue.multiselect--multiple {
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
