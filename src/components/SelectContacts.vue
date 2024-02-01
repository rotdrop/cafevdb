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
                          label="label"
                          :options="contactsArray"
                          :selectable="(option) => !isSelectAllSelected || option.id === 0"
                          :options-limit="100"
                          :placeholder="placeholder || label"
                          :input-label="label"
                          :loading="isLoading"
                          :multiple="multiple"
                          :clearable="clearable"
                          :close-on-select="false"
                          :clear-action="(!clearable && clearAction) || (multiple && clearAction)"
                          :reset-action="resetAction"
                          :searchable="true"
                          v-on="$listeners"
                          @search="(query) => findContacts(query)"
  >
    <template #option="option">
      <NcEllipsisedOption v-tooltip="contactAddressPopup(option)"
                          :name="ncSelect ? String(option[ncSelect.localLabel]) : t(appName, 'undefined')"
                          :search="ncSelect ? ncSelect.search : t(appName, 'undefined')"
      />
    </template>
    <template #selected-option="option">
      <NcEllipsisedOption v-tooltip="contactAddressPopup(option)"
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
import SelectWithSubmitButton from './SelectWithSubmitButton.vue'
import NcEllipsisedOption from '@nextcloud/vue/dist/Components/NcEllipsisedOption.js'
import qs from 'qs'
import addressPopup from '../mixins/address-popup.js'

export default {
  name: 'SelectContacts',
  components: {
    SelectWithSubmitButton,
    NcEllipsisedOption,
  },
  mixins: [
    addressPopup,
  ],
  inheritAttrs: false,
  props: {
    multiple: {
      type: Boolean,
      default: true,
    },
    label: {
      type: String,
      required: true,
    },
    value: {
      type: [Array, String, Object, Number],
      default: () => [],
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
  },
  data() {
    return {
      inputValObjects: [],
      contacts: {},
      ajaxLoading: true,
      ncSelect: undefined,
      id: null,
      ajaxPromise: Promise.resolve(true),
    }
  },
  computed: {
    isLoading() {
      return (this.loading || this.ajaxLoading) && this.loadingIndicator
    },
    contactsArray() {
      return Object.values(this.contacts)
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
      this.ajaxLoading = true
      if (newValue.length > 1 && newValue.findIndex((object) => object.id === 0) !== -1) {
        this.inputValObjects.splice(0, this.inputValObjects.length, this.contacts[0])
      }
      this.ajaxLoading = false
    },
    async onlyAddressBooks() {
      this.info('ONLY ADDRESSBOOKS CHANGED', this.onlyAddressBooks)
      await this.ajaxPromise
      this.ajaxLoading = true
      this.resetContacts()
      this.ajaxPromise = this.findContacts('', this.getValueKeys())
      await this.ajaxPromise
      this.inputValObjects = this.getValueObject(true)
      this.ajaxLoading = false
    },
  },
  async created() {
    await this.ajaxPromise
    this.ajaxLoading = true
    this.resetContacts()
    this.ajaxPromise = this.findContacts('', this.getValueKeys())
    await this.ajaxPromise
    this.inputValObjects = this.getValueObject()
    this.ajaxLoading = false
  },
  mounted() {
    this.ncSelect = this.$refs.select.ncSelect
    this.id = this._uid
  },
  methods: {
    info(...args) {
      console.info(this.$options.name, ...args)
    },
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
    async findContacts(query, contactUids) {
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
      if (contactUids !== undefined) {
        if (contactUids.length === 0) {
          return true
        }
        params.contactUids = contactUids
      }
      try {
        const response = await axios.get(generateUrl(`/apps/${appName}/contacts/search${query}`), {
          params,
          paramsSerializer: params => {
            return qs.stringify(params, { arrayFormat: 'brackets' })
          },
        })
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
      } catch (error) {
        this.$emit('error', error)
      }
      return false
    },
  },
}
</script>
