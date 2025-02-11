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
                          v-model="inputValObjects"
                          v-bind="$attrs"
                          label="formalDisplayName"
                          :options="musiciansArray"
                          :selectable="isSelectable"
                          :options-limit="100"
                          :placeholder="props.placeholder || props.label"
                          :input-label="props.label"
                          :loading="isLoading"
                          :multiple="props.multiple"
                          :clearable="props.clearable"
                          :clear-action="(!props.clearable && props.clearAction) || (props.multiple && props.clearAction)"
                          :reset-action="props.resetAction"
                          :reset-state="initialValObjects"
                          :searchable="props.searchable"
                          v-on="$listeners"
                          @search="findMusicians"
  >
    <template #option="option">
      <NcEllipsisedOption v-tooltip="musicianAddressPopup(option)"
                          :name="ncSelect ? String(option[ncSelect.localLabel]) : t(appId, 'undefined')"
                          :search="ncSelect ? ncSelect.search : t(appId, 'undefined')"
      />
    </template>
    <template #selected-option="option">
      <NcEllipsisedOption v-tooltip="musicianAddressPopup(option)"
                          :name="ncSelect ? String(option[ncSelect.localLabel]) : t(appId, 'undefined')"
                          :search="ncSelect ? ncSelect.search : t(appId, 'undefined')"
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
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl as generateAppUrl } from '../toolkit/util/generate-url.js'
import { musicianAddressPopup } from '../util/address-popup.ts'
import { usePersistentDataStore } from '../stores/persistent-data.ts'
import SelectWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/SelectWithSubmitButton.vue'
import NcEllipsisedOption from '@nextcloud/vue/dist/Components/NcEllipsisedOption.js'
import type { AxiosResponse } from 'axios'
import type { NcSelect } from '@nextcloud/vue'
// import type { Musician } from '../types/address-book.d.ts' resurrect with Vue >= 3.3
// import Console from '../util/console.ts'

// const COMPONENT_NAME = 'SelectMusicians'
// const logger = new Console(COMPONENT_NAME)

type SearchParameters = {
  limit: null|number,
  scope: string,
  projectId?: number,
  ids?: number[],
}

interface MusicianIdObject {
  id: number,
  formalDisplayName: string,
}

// Pre Vue 3.3 cannot handle imported complex types here.
interface Musician {
  id: number,
  formalDisplayName: string,
  informalDisplayName?: string,
  userIdSlug?: string,
  email?: string,
  street?: string,
  city?: string,
  streetNumber?: string,
  postalCode?: string,
  countryName?: string,
  country?: string,
}

const props = withDefaults(
  defineProps<{
    value?: Musician|Musician[]|MusicianIdObject|MusicianIdObject[],
    searchable?: boolean,
    searchScope?: string,
    multiple: boolean,
    label: string,
    clearable?: boolean,
    clearAction?: boolean,
    resetAction?: boolean,
    projectId?: number,
    placeholder?: string,
    selectAllOption?: boolean,
    loading?: boolean,
    loadingIndicator?: boolean,
  }>(), {
    value: undefined,
    searchable: true,
    searchScope: 'musicians',
    multiple: true,
    clearable: true,
    clearAction: true,
    resetAction: false,
    projectId: 0,
    placeholder: undefined,
    selectAllOption: undefined,
    loading: false,
    loadingIndicator: true,
  },
)

const persistentData = usePersistentDataStore()

const inputValObjects = ref<undefined|Musician|Musician[]>([])
const initialValObjects = ref<Musician|Musician[]>([])
const musicians = ref<Record<number, Musician> >({})
const ajaxLoading = ref(false)

const isLoading = computed(() => (props.loading || ajaxLoading.value) && props.loadingIndicator)
const musiciansArray = computed(() => Object.values(musicians.value))
const provideSelectAll = computed(() =>
  props.selectAllOption === undefined ? props.multiple : props.selectAllOption,
)
const isSelectAllSelected = computed(() =>
  provideSelectAll.value
  && Array.isArray(inputValObjects.value)
  && inputValObjects.value.length === 1
  && inputValObjects.value[0].id === 0,
)

watch(() => props.value, async (newValue) => {
  if (ajaxLoading.value) {
    return
  }
  if (!newValue || (Array.isArray(newValue) && newValue.length === 0)) {
    return
  }
  if (!Array.isArray(newValue)) {
    newValue = [newValue]
  }
  ajaxLoading.value = true
  for (const musician of newValue as Musician[]) {
    const musicianId = musician.id
    if (musicianId !== 0 && !musicians.value[musicianId]) {
      await findMusicians('', [musicianId])
      if (musicians.value[musician.id]) {
        if (props.multiple) {
          const array = (inputValObjects.value || []) as Musician[]
          const index = array.findIndex((object) => object.id === musicianId)
          if (index >= 0) {
            array.splice(index, 1, musicians.value[musicianId])
          }
        } else {
          inputValObjects.value = musicians.value[musicianId]
        }
      }
    }
  }
  if (newValue.findIndex((item) => item.id === 0) !== -1) {
    const array = inputValObjects.value as Musician[]
    array.splice(0, array.length, musicians.value[0])
  }
  ajaxLoading.value = false
})

// setting the project id also resets the initial data.
watch(() => props.projectId, async (/* newVal, oldVal */) => {
  await getData()
})

const emit = defineEmits([
  'error',
])

const isSelectable = (option: Musician) => !isSelectAllSelected.value || option.id === 0
const getData = async () => {
  if (ajaxLoading.value) {
    return
  }
  ajaxLoading.value = true
  resetMusicians()
  if (!props.searchable) {
    try {
      musicians.value = persistentData.selectMusicians[props.searchScope][props.projectId] || {}
      inputValObjects.value = getValueObjects(false)
      if (props.resetAction) {
        initialValObjects.value = inputValObjects.value || []
      }
      ajaxLoading.value = false
      return
    } catch (ignoreMe) {
    }
  }
  await findMusicians('', getValueIds())
  inputValObjects.value = getValueObjects(true)
  if (props.resetAction) {
    initialValObjects.value = inputValObjects.value || []
  }
  if (!props.searchable) {
    persistentData.selectMusicians = {
      [props.searchScope]: {
        [props.projectId]: musicians.value,
      },
    }
  }
  ajaxLoading.value = false
}

const resetMusicians = () => {
  musicians.value = {}
  if (provideSelectAll.value) {
    vueSet(musicians.value, 0, { id: 0, formalDisplayName: t(appName, '** everybody **') })
  }
}

const getValueObjects = (noUndefined: boolean) => {
  const value = Array.isArray(props.value) ? props.value : (props.value || props.value === 0 ? [props.value] : [])
  let everybody = false
  let result = value.filter((musician) => musician?.id).map(
    (musician) => {
      const id = musician.id
      if (id === 0) {
        everybody = true
      }
      return musicians.value[id] || (noUndefined ? null : { id, formalDisplayName: id })
    },
  ).filter((musician) => musician !== null && musician !== undefined)
  if (provideSelectAll.value) {
    if (everybody) {
      result = [musicians.value[0]]
    }
  }
  return props.multiple ? result : (result.length > 0) ? result[0] : undefined
}

const getValueIds = () => {
  const value = Array.isArray(props.value) ? props.value : [props.value]
  const result = value.filter((musician) => musician?.id).map((musician) => +musician!.id)
  // logger.info('GET VALUE IDS', result)
  return result
}

const findMusicians = async (query: string, musicianIds: number[]) => {
  query = typeof query === 'string' ? encodeURI(query) : ''
  if (query !== '') {
    query = '/' + query
  }
  const params: SearchParameters = {
    limit: props.searchable ? 10 : null,
    scope: props.searchScope,
  }
  if (props.projectId > 0) {
    params.projectId = props.projectId
  }
  if (musicianIds !== undefined && musicianIds.length > 0) {
    params.ids = musicianIds
  }
  try {
    const response: AxiosResponse<Musician[]> = await axios.get(generateAppUrl(`musicians/search${query}`), { params })
    if (response.data.length > 0) {
      for (const musician of response.data) {
        vueSet(musicians.value, musician.id, musician)
      }
      return true
    }
  } catch (error) {
    emit('error', error)
  }
  return false
}

onBeforeMount(getData)

const select = ref<null|typeof SelectWithSubmitButton>(null)
const ncSelect = computed(() => select.value?.ncSelect as (typeof NcSelect|null))
</script>
<style lang="scss">
ul[id$="-projects-select__listbox"] {
  li.vs__dropdown-option.vs__dropdown-option--disabled {
    cursor: default; // var(--vs-state-disabled-cursor);
  }
}
</style>
