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
  <form class="select-contacts" @submit.prevent="">
    <div v-if="loading" class="loading" />
    <div class="input-wrapper">
      <label :for="id">{{ label }}</label>
      <Multiselect :id="id"
                   ref="multiselect"
                   v-model="inputValObjects"
                   v-tooltip="active ? false : tooltip"
                   v-bind="$attrs"
                   :options="contactsArray"
                   :options-limit="100"
                   :placeholder="placeholder === undefined ? label : placeholder"
                   :hint="hint"
                   :show-labels="true"
                   :searchable="searchable"
                   track-by="key"
                   label="label"
                   class="multiselect-vue"
                   :multiple="multiple"
                   :tag-width="60"
                   :disabled="disabled"
                   @input="emitInput"
                   @search-change="(query, id) => asyncFindContacts(query)"
                   @open="active = true"
                   @close="active = false"
      >
        <template #option="optionData">
          <EllipsisedContactOption :name="$refs.multiselect.getOptionLabel(optionData.option)"
                                   :option="optionData.option"
                                   :search="optionData.search"
                                   :label="$refs.multiselect.label"
          />
        </template>
        <template #tag="tagData">
          <span :key="tagData.option.id"
                v-tooltip="contactAddressPopup(tagData.option)"
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
    </div>
    <p v-if="hint !== ''" class="hint">
      {{ hint }}
    </p>
  </form>
</template>

<script>

import { set as vueSet } from 'vue'
import { appName } from '../app/app-info.js'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { NcMultiselect as Multiselect } from '@nextcloud/vue'
import EllipsisedContactOption from './EllipsisedContactOption.vue'
import qs from 'qs'
import addressPopup from '../mixins/address-popup.js'

let uuid = 0
export default {
  name: 'SelectContacts',
  components: {
    Multiselect,
    EllipsisedContactOption,
  },
  mixins: [
    addressPopup,
  ],
  props: {
    searchable: {
      type: Boolean,
      default: true,
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
    clearButton: {
      type: Boolean,
      default: true,
    },
    placeholder: {
      type: String,
      default: undefined,
    },
    allAddressBooks: {
      type: Object,
      default: undefined,
    },
    onlyAddressBooks: {
      type: Array,
      default: undefined,
    },
    tooltip: {
      type: [Object, String],
      default: undefined,
    },
    selectAllOption: {
      type: Boolean,
      default: undefined,
    },
  },
  data() {
    return {
      inputValObjects: [],
      contacts: {},
      loading: true,
      loadingPromise: Promise.resolve(true),
      active: false,
    }
  },
  computed: {
    id() {
      return 'settings-contacts-' + this.uuid
    },
    contactsArray() {
      return Object.values(this.contacts)
    },
    provideSelectAll() {
      return this.selectAllOption === undefined ? this.multiple : this.selectAllOption
    },
  },
  watch: {
    value(/* newVal, oldVal */) {
      this.loadingPromise.finally(() => {
        this.inputValObjects = this.getValueObject()
        if (this.provideSelectAll && this.inputValObjects.length === 1 && this.inputValObjects[0].UID === 0) {
          this.$refs.multiselect.$refs.VueMultiselect.deactivate()
        }
      })
    },
    onlyAddressBooks(/* newVal, oldVal */) {
      this.loadingPromise.finally(() => {
        // console.info('WATCH ONLY ADDRESSBOOKOS', newVal, oldVal)
        this.loadingPromise = new Promise((resolve/* , reject */) => {
          this.loading = true
          this.resetContacts()
          this.asyncFindContacts('', this.getValueKeys()).then((/* result */) => {
            this.inputValObjects = this.getValueObject(true)
            this.loading = false
            resolve(this.loading)
          })
        })
      })
    },
  },
  created() {
    this.uuid = uuid.toString()
    uuid += 1
    this.loadingPromise.finally(() => {
      this.loadingPromise = new Promise((resolve/* , reject */) => {
        // console.info('CREATED CONTACTS')
        this.loading = true
        this.resetContacts()
        this.asyncFindContacts('', this.getValueKeys()).then((/* result */) => {
          this.inputValObjects = this.getValueObject()
          this.loading = false
          resolve(this.loading)
        })
      })
    })
  },
  methods: {
    resetContacts() {
      this.contacts = {}
      if (this.provideSelectAll) {
        vueSet(this.contacts, 0, { key: 0, uid: 0, label: t(appName, '** everybody **') })
      }
    },
    getValueObject(noUndefined) {
      const value = Array.isArray(this.value) ? this.value : (this.value || this.value === 0 ? [this.value] : [])
      let everybody = false
      let result = value.filter((contact) => contact !== '' && typeof contact !== 'undefined').map(
        (contact) => {
          const key = contact.key !== undefined ? contact.key : (contact.UID || contact.URI || contact)
          if (key === 0) {
            everybody = true
          }
          if (typeof this.contacts[key] === 'undefined') {
            return noUndefined ? null : { key, uid: key, label: key }
          }
          return this.contacts[key]
        },
      ).filter((contact) => contact !== null && contact !== undefined)
      if (this.provideSelectAll) {
        if (everybody) {
          result = [this.contacts[0]]
        }
        for (const [contactKey, contact] of Object.entries(this.contacts)) {
          if (contactKey !== 0 && contactKey !== '0') {
            contact.$isDisabled = everybody
          }
        }
      }
      return this.multiple ? result : (result.length > 0) ? result[0] : undefined
    },
    getValueKeys() {
      const value = Array.isArray(this.value) ? this.value : [this.value]
      const result = value.filter((contact) => contact !== '' && typeof contact !== 'undefined').map(
        (contact) => {
          return contact.key !== undefined ? contact.key : (contact.UID || contact.URI || contact)
        },
      )
      return result
    },
    clearSelection() {
      this.inputValObjects = []
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
    asyncFindContacts(query, contactUids) {
      query = typeof query === 'string' ? encodeURI(query) : ''
      if (query !== '') {
        query = '/' + query
      }
      const params = {
        limit: 10,
      }
      if (this.onlyAddressBooks.length > 0) {
        params.onlyAddressBooks = {}
        for (const book of this.onlyAddressBooks) {
          params.onlyAddressBooks[book.key] = book.uri
        }
      }
      if (contactUids !== undefined && contactUids.length > 0) {
        params.contactUids = contactUids
      }
      return axios
        .get(
          generateUrl(`/apps/${appName}/contacts/search${query}`), {
            params,
            paramsSerializer: params => {
              return qs.stringify(params, { arrayFormat: 'brackets' })
            },
          })
        .then((response) => {
          if (response.data.length > 0) {
            for (const contact of response.data) {
              const key = contact.UID || contact.URI
              if (key) {
                contact.key = key
                if (contact.FN) {
                  contact.name = contact.FN
                } else {
                  if (Array.isArray(contact.EMAIL) && contact.EMAIL.length > 0) {
                    contact.name = contact.EMAIL[0]
                    if (contact.name.value) {
                      contact.name = contact.name.value
                    }
                  } else {
                    contact.name = contact.key
                  }
                }
                contact.label = this.contactNameFromContact(contact)
                const addressBookKey = contact['addressbook-key']
                if (addressBookKey && this.allAddressBooks[addressBookKey]) {
                  contact.addressBookName = this.allAddressBooks[addressBookKey].displayName
                  contact.label += ' [' + contact.addressBookName + ']'
                }
                vueSet(this.contacts, key, contact)
              }
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
.cloud-version {
  --cloud-icon-checkmark: var(--icon-checkmark-dark);
  &.cloud-version-major-24 {
    --cloud-icon-checkmark: var(--icon-checkmark-000);
  }
}
.select-contacts {
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
            background-image: var(--cloud-icon-checkmark);
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
