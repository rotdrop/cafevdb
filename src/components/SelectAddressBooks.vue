y<!--
 - Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2022-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
                          :optionsLimit="100"
                          :placeholder="placeholder || label"
                          :inputLabel="label"
                          :loading="isLoading"
                          :multiple="multiple"
                          :clearable="clearable"
                          :clearAction="(!clearable && clearAction) || (multiple && clearAction)"
                          :submitButton="submitButton"
                          :resetAction="resetAction"
                          :resetState="initialValObjects"
  />
</template>

<script setup lang="ts">
import type { FilesInitialState as InitialState } from '../../build/ts-types/php-modules/Controller/DTO.ts'
import type { AddressBook } from '../types/address-book.d.ts'

import axios from '@nextcloud/axios'
import {
  computed,
  onBeforeMount,
  ref,
  useTemplateRef,
} from 'vue'
import SelectWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/SelectWithSubmitButton.vue'
import {
  BASE_PATH as contactsBasePath,
  END_POINT_ADDRESS_BOOKS,
} from '../../build/ts-types/php-modules/Controller/ContactsController.ts'
import { generateUrl as generateAppUrl } from '../toolkit/util/generate-url.ts'
import getInitialState from '../toolkit/util/initial-state.ts'
import Console from '../util/console.ts'

const props = withDefaults(
  defineProps<{
    multiple?: boolean
    label: string
    value?: number|string|AddressBook|AddressBook[]
    placeholder?: string
    loading?: boolean
    loadingIndicator?: boolean
    clearable?: boolean
    clearAction?: boolean
    resetAction?: boolean
    submitButton?: boolean
    noUndefined?: boolean
  }>(),
  {
    // eslint-disable-next-line vue/no-boolean-default
    multiple: true,
    value: () => [],
    placeholder: undefined,
    loading: false,
    // eslint-disable-next-line vue/no-boolean-default
    loadingIndicator: true,
    // eslint-disable-next-line vue/no-boolean-default
    clearable: true,
    // eslint-disable-next-line vue/no-boolean-default
    clearAction: true,
    // eslint-disable-next-line vue/no-boolean-default
    resetAction: true,
    submitButton: false,
    noUndefined: false,
  },
)

const emit = defineEmits([
  'error',
  'input',
  'update:addressBooks',
])

const COMPONENT_NAME = 'SelectAddressBooks'
const logger = new Console(COMPONENT_NAME)

const inputValObjects = ref<undefined | AddressBook | AddressBook[]>(undefined)
const initialValObjects = ref<AddressBook | AddressBook[]>([])
const addressBooks = ref<Record<string, AddressBook>>({})
const ajaxLoading = ref(true)

const addressBooksArray = computed(() => Object.values(addressBooks.value))
const isLoading = computed(() => (props.loading || ajaxLoading.value) && props.loadingIndicator)

onBeforeMount(async () => {
  const initialState = getInitialState<InitialState>({ section: 'files' })
  if (initialState?.contacts && initialState.contacts.addressBooks) {
    addressBooks.value = initialState.contacts.addressBooks
    // logger.info('ADDRESSBOOKS FROM STATE', addressBooks.value)
  } else {
    await provideAddressBooks()
    // logger.info('ADDRESSBOOKS FROM AJAX', addressBooks.value)
  }
  emit('update:addressBooks', addressBooks.value)
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

const select = useTemplateRef('select')

/** @param value TBD. */
function emitInput(value: undefined|AddressBook|AddressBook[]) {
  logger.info('EMIT INPUT', value)
  emit('input', value)
}

/** @param noUndefined TBD. */
function getValueObject(noUndefined: boolean) {
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

/** TBD. */
async function provideAddressBooks() {
  try {
    const response = await axios.get(generateAppUrl(`${contactsBasePath}/${END_POINT_ADDRESS_BOOKS}`))
    for (const [key, book] of Object.entries(response.data) as [string, AddressBook][]) {
      addressBooks.value[key] = book
    }
    // logger.info('ADDRESSBOOKS', addressBooks.value)
  } catch (error) {
    emit('error', error)
  }
}
</script>
