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
<script setup lang="ts">
import {
  computed,
  onBeforeMount,
  ref,
  set as vueSet,
  watch,
} from 'vue'
import { appName } from '../config.ts'
import axios from '@nextcloud/axios'
import type { AxiosResponse } from 'axios'
import { generateUrl as generateAppUrl } from '../toolkit/util/generate-url.js'
import SelectWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/SelectWithSubmitButton.vue'
import NcEllipsisedOption from '@nextcloud/vue/dist/Components/NcEllipsisedOption.js'
import type { NcSelect } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import qs from 'qs'
import { contactAddressPopup, contactNameFromContact } from '../util/address-popup.ts'
import type { AddressBook, Contact } from '../types/address-book.d.ts'
import Console from '../util/console.ts'

const COMPONENT_NAME = 'SelectContacts'
const logger = new Console(COMPONENT_NAME)

const props = withDefaults(
  defineProps<{
    multiple?: boolean,
    label: string,
    value?: number|string|Contact|Contact[],
    placeholder?: string,
    allAddressBooks?: Record<string, AddressBook>,
    onlyAddressBooks?: AddressBook[],
    selectAllOption?: boolean,
    clearable?: boolean,
    clearAction?: boolean,
    resetAction?: boolean,
    loading?: boolean,
    loadingIndicator?: boolean,
  }>(), {
    multiple: true,
    value: () => [],
    placeholder: undefined,
    allAddressBooks: undefined,
    onlyAddressBooks: undefined,
    selectAllOption: undefined,
    clearable: true,
    clearAction: true,
    resetAction: false,
    loading: false,
    loadingIndicator: true,
  },
)

const inputValObjects = ref<undefined|Contact|Contact[]>(undefined)
const contacts = ref<Record<string | number, Contact>>({})
const ajaxLoading = ref(true)
// eslint-disable-next-line @typescript-eslint/no-explicit-any
let ajaxPromise: Promise<any> = Promise.resolve(true)

const isLoading = computed(() => (props.loading || ajaxLoading.value) && props.loadingIndicator)
const contactsArray = computed(() => Object.values(contacts.value))
const provideSelectAll = computed(() => props.selectAllOption === undefined ? props.multiple : props.selectAllOption)
const isSelectAllSelected = computed(() => provideSelectAll.value
  && Array.isArray(inputValObjects.value)
  && inputValObjects.value.length === 1
  && inputValObjects.value[0].key === 0,
)

watch(() => props.value, async (newValue) => {
  ajaxLoading.value = true
  if (Array.isArray(newValue) && newValue.findIndex((object: Contact) => object.key === 0) !== -1) {
    const array = inputValObjects.value as Contact[]
    array.splice(0, array.length, contacts.value[0])
  }
  ajaxLoading.value = false
})
watch(() => props.onlyAddressBooks, async () => {
  logger.info('ONLY ADDRESSBOOKS CHANGED', props.onlyAddressBooks)
  await ajaxPromise
  ajaxLoading.value = true
  resetContacts()
  ajaxPromise = findContacts('', getValueKeys())
  await ajaxPromise

  inputValObjects.value = getValueObject(true)
  ajaxLoading.value = false
})

const emit = defineEmits([
  'error',
])

onBeforeMount(async () => {
  await ajaxPromise
  ajaxLoading.value = true
  resetContacts()
  ajaxPromise = findContacts('', getValueKeys())
  await ajaxPromise
  inputValObjects.value = getValueObject(false)
  ajaxLoading.value = false
})

const select = ref<null|typeof SelectWithSubmitButton>(null)
const ncSelect = computed(() => select.value?.ncSelect as (typeof NcSelect|null))

const isSelectable = (option: Contact) => !isSelectAllSelected.value || option.key === 0
const resetContacts = () => {
  contacts.value = {}
  if (provideSelectAll.value) {
    vueSet(contacts.value, 0, { key: 0, UID: 0, label: t(appName, '** everybody **') })
  }
}

const getValueObject = (noUndefined: boolean) => {
  const value = Array.isArray(props.value) ? props.value : (props.value || props.value === 0 ? [props.value] : [])
  let everybody = false
  let result = value.filter((contact) => contact !== '' && typeof contact !== 'undefined').map(
    (contact) => {
      const key = typeof contact === 'string' || typeof contact === 'number'
        ? contact
        : contact.key || contact.UID || contact.URI!
      if (key === 0) {
        everybody = true
      }
      if (typeof contacts.value[key] === 'undefined') {
        return noUndefined ? null : { key, UID: key, label: key } as Contact
      }
      return contacts.value[key]
    },
  ).filter((contact) => contact !== null && contact !== undefined)
  if (provideSelectAll.value) {
    if (everybody) {
      result = [contacts.value[0]]
    }
    for (const [contactKey, contact] of Object.entries(contacts.value)) {
      if (contactKey !== '0') {
        contact.$isDisabled = everybody
      }
    }
  }
  return props.multiple ? result : (result.length > 0) ? result[0] : undefined
}

const getValueKeys = () => {
  const value = Array.isArray(props.value) ? props.value : [props.value]
  const result = value.filter(contact => !!contact).map(
    contact => {
      return typeof contact === 'string' || typeof contact === 'number'
        ? ''
        : (contact.key || contact.UID || contact.URI) + ''
    },
  )
  return result
}

const findContacts = async (query: string, contactUids: string[]) => {
  query = typeof query === 'string' ? encodeURI(query) : ''
  if (query !== '') {
    query = '/' + query
  }
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const params: any = {
    limit: 10,
  }
  if (Array.isArray(props.onlyAddressBooks) && props.onlyAddressBooks.length > 0) {
    params.onlyAddressBooks = {}
    for (const book of props.onlyAddressBooks) {
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
          contact.label = contactNameFromContact(contact)
          const addressBookKey = contact['addressbook-key']
          if (addressBookKey && props.allAddressBooks?.[addressBookKey]) {
            contact.addressBookName = props.allAddressBooks[addressBookKey].displayName
            contact.label += ' [' + contact.addressBookName + ']'
          }
          vueSet(contacts.value, key, contact)
        }
      }
      return true
    }
  } catch (error) {
    emit('error', error)
  }
  return false
}
</script>
