<!--
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
                          v-model="inputValObjects"
                          v-bind="$attrs"
                          label="publicName"
                          :options="musiciansArray"
                          :selectable="isSelectable"
                          :optionsLimit="100"
                          :placeholder="props.placeholder || props.label"
                          :inputLabel="props.label"
                          :loading="isLoading"
                          :multiple="props.multiple"
                          :clearable="props.clearable"
                          :clearAction="(!props.clearable && props.clearAction) || (props.multiple && props.clearAction)"
                          :resetAction="props.resetAction"
                          :resetState="initialValObjects"
                          :searchable="props.searchable"
                          :filterBy="filterByProps"
                          @search="nextcloudSelectSearch"
  >
    <template #option="option">
      <NcEllipsisedOption v-tooltip="musicianAddressPopup(option)"
                          :name="ncSelect ? String(option[ncSelect.localLabel]) : t(appName, 'undefined')"
                          :search="ncSelect ? ncSelect.search : t(appName, 'undefined')"
      />
    </template>
    <template #selected-option="option">
      <NcEllipsisedOption v-tooltip="musicianAddressPopup(option)"
                          :name="ncSelect ? String(option[ncSelect.localLabel]) : t(appName, 'undefined')"
                          :search="ncSelect ? ncSelect.search : t(appName, 'undefined')"
      />
    </template>
  </SelectWithSubmitButton>
</template>

<script setup lang="ts">
import type { NcSelect } from '@nextcloud/vue'
import type { EnumMusiciansSearchScope } from '../../build/ts-types/php-modules/Controller.ts'
import type { FrontEndEntity } from '../toolkit/services/entity-factory.ts'

import { translate as t } from '@nextcloud/l10n'
import { NcEllipsisedOption } from '@nextcloud/vue'
import {
  computed,
  onBeforeMount,
  ref,
  watch,
} from 'vue'
import SelectWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/SelectWithSubmitButton.vue'
import { END_POINT as searchEndPoint } from '../../build/ts-types/php-modules/Controller/MusiciansController.ts'
import { appName } from '../config.ts'
import { usePersistentDataStore } from '../stores/persistent-data.ts'
import { loadEntities } from '../toolkit/services/entity-repository.ts'
import { generateOcsUrl as generateAppOcsUrl } from '../toolkit/util/generate-url.ts'
import { musicianAddressPopup } from '../util/address-popup.ts'
import Console from '../util/console.ts'

type SearchParameters = {
  limit: null|number
  scope: EnumMusiciansSearchScope
  projectId?: null|number
  ids?: number[]
}

export interface MusicianIdObject {
  id: number
  publicName: string
}

// Pre Vue 3.3 cannot handle imported complex types here.
// interface Musician {
//   city?: string|null,
//   country?: string|null,
//   countryName?: string|null,
//   email?: string,
//   publicName: string,
//   id: number,
//   personalPublicName?: string,
//   nickName?: string,
//   organization?: string,
//   postalCode?: string,
//   street?: string,
//   streetNumber?: string,
//   userIdSlug?: string,
// }
type Musician = FrontEndEntity<'Musician'>
type SelectObject = Musician|MusicianIdObject

const props = withDefaults(
  defineProps<{
    clearAction?: boolean
    clearable?: boolean
    label: string
    loading?: boolean
    loadingIndicator?: boolean
    multiple?: boolean
    placeholder?: string
    projectId?: number
    resetAction?: boolean
    searchScope?: EnumMusiciansSearchScope
    searchable?: boolean
    selectAllOption?: boolean
    value?: SelectObject|SelectObject[]
  }>(),
  {
    // eslint-disable-next-line vue/no-boolean-default
    clearAction: true,
    // eslint-disable-next-line vue/no-boolean-default
    clearable: true,
    loading: false,
    // eslint-disable-next-line vue/no-boolean-default
    loadingIndicator: true,
    // eslint-disable-next-line vue/no-boolean-default
    multiple: true,
    placeholder: undefined,
    projectId: 0,
    resetAction: false,
    searchScope: 'musicians',
    // eslint-disable-next-line vue/no-boolean-default
    searchable: true,
    // eslint-disable-next-line vue/no-boolean-default
    selectAllOption: undefined,
    value: undefined,
  },
)

const emit = defineEmits([
  'error',
])

const COMPONENT_NAME = 'SelectMusicians'
const logger = new Console(COMPONENT_NAME)

const persistentData = usePersistentDataStore()

const inputValObjects = ref<undefined|SelectObject|SelectObject[]>([])
const initialValObjects = ref<SelectObject|SelectObject[]>([])
const musicians = ref<Record<number, SelectObject>>({})
const ajaxLoading = ref(false)

const isLoading = computed(() => (props.loading || ajaxLoading.value) && props.loadingIndicator)
const musiciansArray = computed(() => {
  const result = Object.values(musicians.value)
  logger.info('MUS ARRAY', { result })
  return result
})
const provideSelectAll = computed(
  () => props.selectAllOption === undefined ? props.multiple : props.selectAllOption,
)
const isSelectAllSelected = computed(
  () =>
    provideSelectAll.value
    && Array.isArray(inputValObjects.value)
    && inputValObjects.value.length === 1
    && inputValObjects.value[0].id === 0,
)

const findMusicians = async (query: string, musicianIds?: number[]) => {
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
  if ((musicianIds ?? []).length > 0) {
    params.ids = musicianIds
  }
  try {
    const url = generateAppOcsUrl(`${searchEndPoint}${query}`)
    const data = await loadEntities<'Musician'>(url, params)
    for (const [id, musician] of Object.entries(data.Musician)) {
      musicians.value[id] = musician
    }
    return Object.entries(data.Musician).length > 0
  } catch (error) {
    emit('error', error)
  }
  return false
}

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
          const array = (inputValObjects.value || []) as SelectObject[]
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
    const array = inputValObjects.value as SelectObject[]
    array.splice(0, array.length, musicians.value[0])
  }
  ajaxLoading.value = false
})

const resetMusicians = () => {
  musicians.value = {}
  if (provideSelectAll.value) {
    musicians.value[0] = { id: 0, publicName: t(appName, '** everybody **') }
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
      return musicians.value[id] || (noUndefined ? null : { id, publicName: id })
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

const getData = async () => {
  if (ajaxLoading.value) {
    return
  }
  ajaxLoading.value = true
  resetMusicians()
  if (!props.searchable) {
    try {
      musicians.value = persistentData.selectMusicians[props.searchScope]?.[props.projectId] || {}
      inputValObjects.value = getValueObjects(false)
      if (props.resetAction) {
        initialValObjects.value = inputValObjects.value || []
      }
      ajaxLoading.value = false
      return
    } catch /* (ignoreMe) */ {
      // ignored
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
        [props.projectId]: musicians.value as Record<number, Musician>,
      },
    }
  }
  ajaxLoading.value = false
}

// setting the project id also resets the initial data.
watch(() => props.projectId, async (/* newVal, oldVal */) => {
  await getData()
})

const isSelectable = (option: Musician) => !isSelectAllSelected.value || option.id === 0

const nextcloudSelectSearch = (query: string) => findMusicians(query)

const filterByProps = (musician: Musician, _label: string, query: string) => {
  const lcQuery = query.toLocaleLowerCase()

  if (musician.publicName.toLocaleLowerCase().search(lcQuery) > -1) {
    return true
  }
  if ((musician.personalPublicName?.toLocaleLowerCase().search(lcQuery) ?? -1) > -1) {
    return true
  }
  if (lcQuery.search('@') > -1) {
    if ((musician.email?.toLocaleLowerCase().search(lcQuery) ?? -1) > -1) {
      return true
    }
  }
  if ((musician.userIdSlug?.toLocaleLowerCase().search(lcQuery) ?? -1) > -1) {
    return true
  }
  if ((musician.organization?.toLocaleLowerCase().search(lcQuery) ?? -1) > -1) {
    return true
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
