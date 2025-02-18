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
<script setup lang="ts">
import {
  computed,
  onBeforeMount,
  ref,
  set as vueSet,
} from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl as generateAppUrl } from '../toolkit/util/generate-url.js'
import { getInitialState } from '../toolkit/services/InitialStateService.js'
import SelectWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/SelectWithSubmitButton.vue'
import type { AddressBook } from '../types/address-book.d.ts'
import Console from '../util/console.ts'

interface InitialState {
  contacts: {
    addressBooks: Record<string|number, AddressBook>,
  },
}

const COMPONENT_NAME = 'SelectAddressBooks'
const logger = new Console(COMPONENT_NAME)

const props = withDefaults(
  defineProps<{
    multiple: boolean,
    label: string,
    value?: number|string|AddressBook|AddressBook[],
    placeholder?: string,
    loading?: boolean,
    loadingIndicator?: boolean,
    clearable?: boolean,
    clearAction?: boolean,
    resetAction?: boolean,
    submitButton?: boolean,
    noUndefined?: boolean,
  }>(), {
    multiple: true,
    value: () => [],
    placeholder: undefined,
    loading: false,
    loadingIndicator: true,
    clearable: true,
    clearAction: true,
    resetAction: true,
    submitButton: false,
    noUndefined: false,
  },
)

const inputValObjects = ref<undefined | AddressBook | AddressBook[]>(undefined)
const initialValObjects = ref<AddressBook | AddressBook[]>([])
const addressBooks = ref<Record<string | number, AddressBook> >({})
const ajaxLoading = ref(true)

const addressBooksArray = computed(() => Object.values(addressBooks.value))
const isLoading = computed(() => (props.loading || ajaxLoading.value) && props.loadingIndicator)

const emit = defineEmits([
  'error',
  'input',
  'update:address-books',
])

onBeforeMount(async () => {
  const initialState: InitialState = getInitialState('files')
  if (initialState.contacts && initialState.contacts.addressBooks) {
    addressBooks.value = initialState.contacts.addressBooks
    // logger.info('ADDRESSBOOKS FROM STATE', addressBooks.value)
  } else {
    await provideAddressBooks()
    // logger.info('ADDRESSBOOKS FROM AJAX', addressBooks.value)
  }
  emit('update:address-books', addressBooks.value)
  if (Array.isArray(props.value) && props.value.length === 0) {
    // pre-select all non-system address-books if no initial value is provided
    inputValObjects.value = Object.values(addressBooks.value).filter((book) => !book.isSystemAddressBook)
    // this is needed as the wrapped select only emits input events
    // when it is changed through user interaction (in general)
    emitInput(inputValObjects.value)
  } else {
    inputValObjects.value = getValueObject(props.noUndefined)
  }
  if (props.resetAction) {
    initialValObjects.value = inputValObjects.value || []
  }
  ajaxLoading.value = false
})

const select = ref(null)

const emitInput = (value: undefined|AddressBook|AddressBook[]) => {
  logger.info('EMIT INPUT', value)
  emit('input', value)
}

const getValueObject = (noUndefined: boolean) => {
  const value = Array.isArray(props.value) ? props.value : (props.value || props.value === 0 ? [props.value] : [])
  let everybody = false
  let result = value.filter((addressBook) => !!addressBook).map(
    (addressBook) => {
      const key = typeof addressBook === 'string' || typeof addressBook === 'number'
        ? addressBook
        : addressBook.key || addressBook.uid || addressBook.uri!
      if (key === 0) {
        everybody = true
      }
      if (typeof addressBooks.value[key] === 'undefined') {
        return noUndefined ? null : { key, uid: key as string, displayName: key as string }
      }
      return addressBooks.value[key]
    },
  ).filter((addressBook) => addressBook !== null && addressBook !== undefined)
  if (props.multiple) {
    if (everybody) {
      result = [addressBooks.value[0]]
    }
    for (const [addressBookKey, addressBook] of Object.entries(addressBooks.value)) {
      if (addressBookKey !== '0') {
        addressBook.$isDisabled = everybody
      }
    }
  }
  return props.multiple ? result : (result.length > 0) ? result[0] : undefined
}

const provideAddressBooks = async () => {
  try {
    const response = await axios.get(generateAppUrl('contacts/address-books'))
    for (const [key, book] of Object.entries(response.data)) {
      vueSet(addressBooks.value, key, book)
    }
    // logger.info('ADDRESSBOOKS', addressBooks.value)
  } catch (error) {
    emit('error', error)
  }
}
</script>
