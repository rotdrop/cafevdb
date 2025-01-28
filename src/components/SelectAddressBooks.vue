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
                          :options="addressBooksArray"
                          label="displayName"
                          :options-limit="100"
                          :placeholder="placeholder || label"
                          :input-label="label"
                          :loading="isLoading"
                          :multiple="multiple"
                          :clearable="clearable"
                          :clear-action="(!clearable && clearAction) || (multiple && clearAction)"
                          :submit-button="submitButton"
                          :reset-action="resetAction"
                          :reset-state="initialValObjects"
                          v-on="$listeners"
  />
</template>
<script lang="ts">
import { set as vueSet } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl as generateAppUrl } from '../toolkit/util/generate-url.js'
import { getInitialState } from '../services/initial-state-service.ts'
import SelectWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/SelectWithSubmitButton.vue'
import consoleMixin from '../mixins/console.ts'
import l10nMixin from '../mixins/l10n.ts'
import type { AddressBook } from './types/address-book.d.ts'

interface InitialState {
  contacts: {
    addressBooks: Record<string|number, AddressBook>,
  },
}

export default {
  name: 'SelectAddressBooks',
  components: {
    SelectWithSubmitButton,
  },
  mixins: [
    consoleMixin,
    l10nMixin,
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
    loading: {
      type: Boolean,
      default: false,
    },
    loadingIndicator: {
      type: Boolean,
      default: true,
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
      default: true,
    },
    submitButton: {
      type: Boolean,
      default: false,
    },
    noUndefined: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      inputValObjects: undefined as undefined|AddressBook|AddressBook[],
      initialValObjects: [] as AddressBook|AddressBook[],
      addressBooks: {} as Record<string|number, AddressBook>,
      ajaxLoading: true,
      active: false,
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      ncSelect: null as any,
      id: null,
    }
  },
  computed: {
    addressBooksArray() {
      return Object.values(this.addressBooks)
    },
    isLoading() {
      return (this.loading || this.ajaxLoading) && this.loadingIndicator
    },
  },
  async created() {
    const initialState: InitialState = getInitialState()
    if (initialState.contacts && initialState.contacts.addressBooks) {
      this.addressBooks = initialState.contacts.addressBooks
      // console.info('ADDRESSBOOKS FROM STATE', this.addressBooks)
    } else {
      await this.provideAddressBooks()
      // console.info('ADDRESSBOOKS FROM AJAX', this.addressBooks)
    }
    this.$emit('update:address-books', this.addressBooks)
    if (Array.isArray(this.value) && this.value.length === 0) {
      // pre-select all non-system address-books if no initial value is provided
      this.inputValObjects = Object.values(this.addressBooks).filter((book) => !book.isSystemAddressBook)
      // this is needed as the wrapped select only emits input events
      // when it is changed through user interaction (in general)
      this.emitInput(this.inputValObjects)
    } else {
      this.inputValObjects = this.getValueObject(this.noUndefined)
    }
    if (this.resetAction) {
      this.initialValObjects = this.inputValObjects || []
    }
    this.ajaxLoading = false
  },
  mounted() {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    this.ncSelect = (this.$refs!.select! as any).ncSelect
    this.id = this._uid
  },
  methods: {
    emitInput(value: undefined|AddressBook|AddressBook[]) {
      this.info('EMIT INPUT', value)
      this.$emit('input', value)
    },
    getValueObject(noUndefined: boolean) {
      const value = Array.isArray(this.value) ? this.value : (this.value || this.value === 0 ? [this.value] : [])
      let everybody = false
      let result = value.filter((addressBook) => addressBook !== '' && typeof addressBook !== 'undefined').map(
        (addressBook) => {
          const key: string|number = addressBook.key !== undefined ? addressBook.key : (addressBook.UID || addressBook.URI || addressBook)
          if (key === 0) {
            everybody = true
          }
          if (typeof this.addressBooks[key] === 'undefined') {
            return noUndefined ? null : { key, uid: key as string, displayName: key as string }
          }
          return this.addressBooks[key]
        },
      ).filter((addressBook) => addressBook !== null && addressBook !== undefined)
      if (this.multiple) {
        if (everybody) {
          result = [this.addressBooks[0]]
        }
        for (const [addressBookKey, addressBook] of Object.entries(this.addressBooks)) {
          if (addressBookKey !== '0') {
            addressBook.$isDisabled = everybody
          }
        }
      }
      return this.multiple ? result : (result.length > 0) ? result[0] : undefined
    },
    async provideAddressBooks() {
      try {
        const response = await axios.get(generateAppUrl('contacts/address-books'))
        for (const [key, book] of Object.entries(response.data)) {
          vueSet(this.addressBooks, key, book)
        }
        // console.info('ADDRESSBOOKS', this.addressBooks)
      } catch (error) {
        this.$emit('error', error)
      }
    },
  },
}
</script>
