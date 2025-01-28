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
                          v-bind="$attrs"
                          v-model="inputValObjects"
                          label="label"
                          :options="contactsArray"
                          :selectable="isSelectable"
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
                          @search="findContacts"
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
<script lang="ts">
import { set as vueSet } from 'vue'
import type { PropType } from 'vue'
import { appName } from '../config.ts'
import axios from '@nextcloud/axios'
import type { AxiosResponse } from 'axios'
import { generateUrl as generateAppUrl } from '../toolkit/util/generate-url.js'
import SelectWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/SelectWithSubmitButton.vue'
import NcEllipsisedOption from '@nextcloud/vue/dist/Components/NcEllipsisedOption.js'
import { translate as t } from '@nextcloud/l10n'
import qs from 'qs'
import addressPopup from '../mixins/address-popup.ts'
import consoleMixin from '../mixins/console.ts'
import l10nMixin from '../mixins/l10n.ts'
import type { AddressBook, Contact } from './types/address-book.d.ts'

export default {
  name: 'SelectContacts',
  components: {
    SelectWithSubmitButton,
    NcEllipsisedOption,
  },
  mixins: [
    addressPopup,
    l10nMixin,
    consoleMixin,
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
      type: Array as PropType<AddressBook[]>,
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
      inputValObjects: undefined as undefined|Contact|Contact[],
      contacts: {} as Record<string|number, Contact>,
      ajaxLoading: true,
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      ncSelect: null as any,
      id: null,
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      ajaxPromise: Promise.resolve(true) as Promise<any>,
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
      return this.provideSelectAll
        && Array.isArray(this.inputValObjects)
        && this.inputValObjects.length === 1
        && this.inputValObjects[0].key === 0
    },
  },
  watch: {
    async value(newValue: Contact|Contact[]) {
      this.ajaxLoading = true
      if (Array.isArray(newValue) && newValue.findIndex((object: Contact) => object.key === 0) !== -1) {
        const array = this.inputValObjects as Contact[]
        array.splice(0, array.length, this.contacts[0])
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
    this.inputValObjects = this.getValueObject(false)
    this.ajaxLoading = false
  },
  mounted() {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    this.ncSelect = (this.$refs!.select! as any).ncSelect
    this.id = this._uid
  },
  methods: {
    isSelectable(option: Contact) {
      return !this.isSelectAllSelected || option.key === 0
    },
    resetContacts() {
      this.contacts = {}
      if (this.provideSelectAll) {
        vueSet(this.contacts, 0, { key: 0, UID: 0, label: t(appName, '** everybody **') })
      }
    },
    getValueObject(noUndefined: boolean) {
      const value = Array.isArray(this.value) ? this.value : (this.value || this.value === 0 ? [this.value] : [])
      let everybody = false
      let result = value.filter((contact) => contact !== '' && typeof contact !== 'undefined').map(
        (contact) => {
          const key = contact.key !== undefined ? contact.key : (contact.UID || contact.URI || contact)
          if (key === 0) {
            everybody = true
          }
          if (typeof this.contacts[key] === 'undefined') {
            return noUndefined ? null : { key, UID: key, label: key }
          }
          return this.contacts[key]
        },
      ).filter((contact) => contact !== null && contact !== undefined)
      if (this.provideSelectAll) {
        if (everybody) {
          result = [this.contacts[0]]
        }
        for (const [contactKey, contact] of Object.entries(this.contacts)) {
          if (contactKey !== '0') {
            contact.$isDisabled = everybody
          }
        }
      }
      return this.multiple ? result : (result.length > 0) ? result[0] : undefined
    },
    getValueKeys() {
      const value = Array.isArray(this.value) ? this.value : [this.value]
      const result = value.filter((contact: Contact) => contact).map(
        (contact: Contact) => {
          return (contact.key || contact.UID || contact.URI) + ''
        },
      )
      return result
    },
    async findContacts(query: string, contactUids: string[]) {
      query = typeof query === 'string' ? encodeURI(query) : ''
      if (query !== '') {
        query = '/' + query
      }
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const params: any = {
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
        const response: AxiosResponse<Contact[]> = await axios.get(generateAppUrl(`contacts/search${query}`), {
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
                  // eslint-disable-next-line @typescript-eslint/no-explicit-any
                  contact.name = (contact.EMAIL[0] as any)?.value || contact.EMAIL[0]
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
