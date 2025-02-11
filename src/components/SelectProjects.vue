<!--
 - Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2022, 2023, 2024, 2025, 2025, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
                          :input-id="id + '-projects-select-input'"
                          :options="projectsArray"
                          :selectable="isSelectable"
                          :uid="id + '-projects-select'"
                          :group-select="false"
                          :options-limit="100"
                          :placeholder="placeholder || label"
                          :input-label="label"
                          :loading="isLoading"
                          label="name"
                          :multiple="multiple"
                          :clear-action="(!clearable && clearAction) || (multiple && clearAction)"
                          v-on="$listeners"
                          @search="(query) => findProjects(query)"
  />
</template>
<script setup lang="ts">
import { appName } from '../config.ts'
import { translate as t } from '@nextcloud/l10n'
import SelectWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/SelectWithSubmitButton.vue'
import useAppDataStore from '../stores/app-data.ts'
import { storeToRefs } from 'pinia'
import {
  computed,
  getCurrentInstance,
  onMounted,
  ref,
  watch,
} from 'vue'
import type { Project, ProjectTemporalType } from '../stores/app-data.ts'
import { AppError } from '../types/errors.ts'
import Console from '../util/console.ts'

type OnlyIdType = { id: number }
type ProjectItemType = Project | (OnlyIdType & { name: string, year: number, type: ProjectTemporalType })
type IdType = OnlyIdType | ProjectItemType
type InputObjectType = IdType | number
type ValueType = InputObjectType[]|InputObjectType|undefined

const isIdType = (arg: ValueType): arg is IdType => !!arg && !Array.isArray(arg) && (typeof arg !== 'number')
const isIdTypeArray = (arg: ValueType): arg is IdType[] => !!arg && Array.isArray(arg) && (arg.length === 0 || (typeof arg[0] !== 'number'))

const COMPONENT_NAME = 'SelectProjects'
const logger = new Console(COMPONENT_NAME)

const props = withDefaults(
  defineProps<{
    multiple?: boolean,
    value?: ValueType,
    clearable?: boolean,
    // clear all options, only makes sense if multiple == true
    clearAction?: boolean,
    label: string,
    placeholder?: string,
    loading?: boolean,
    loadingIndicator?: boolean,
  }>(), {
    multiple: true,
    value: undefined,
    clearable: true,
    clearAction: true,
    placeholder: undefined,
    loading: false,
    loadingIndicator:
    true,
  },
)

const appData = useAppDataStore()
const { projects } = storeToRefs(appData)

const inputValObjects = ref<undefined | Project | Project[]>([])
const ajaxLoading = ref(false)
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const ncSelect = ref<any>(undefined)
const id = ref<null|string>(null)

const isLoading = computed(() => (props.loading || ajaxLoading.value) && props.loadingIndicator)
const projectsArray = computed(() => {
  logger.info('RECOMPUTE PROJECTS ARRAY')
  // const groupedValues = {}
  // for (const project of Object.values(this.projects)) {
  //   const year = project.year
  //   if (groupedValues[year] === undefined) {
  //     groupedValues[year] = {
  //       year,
  //       projects: [project],
  //     }
  //   } else {
  //     groupedValues[year].projects.push(project)
  //   }
  // }
  // return Object.values(groupedValues).sort((p1, p2) => -(p1.year - p2.year))
  logger.info('PROJECTS', projects.value)
  const result: ProjectItemType[] = Object.values(projects.value).sort((a, b) => {
    const p1 = a as Project
    const p2 = b as Project
    const p1year = p1?.year || -1
    const p2year = p2?.year || -1
    return p1year === p2year ? p1.name.localeCompare(p2.name) : -(p1year - p2year)
  })
  if (result.length === 0) {
    return []
  }
  let index = 0
  let fakeId = -1
  while (index < result.length) {
    const project = result[index]
    const year = project.year
    const yearName = project.type === 'permanent' ? t(appName, 'Permanent') : '' + year
    result.splice(index, 0, { id: fakeId--, name: yearName, year, type: '' })
    ++index
    while (++index < result.length && result[index].year === year) { /* nothing */ }
  }
  return result
})

const valueIds = computed(() => {
  if (!props.value || (Array.isArray(props.value) && props.value.length === 0)) {
    return []
  }
  const value = props.value
  if (!Array.isArray(value)) {
    return [isIdType(value) ? value.id : value]
  }
  if (isIdTypeArray(value)) {
    return value.map((project) => project?.id).filter(id => !!id)
  } else {
    return (value as number[] /* TS fails to detect this */).filter(id => !!id)
  }
})

watch(() => props.value, async () => {
  if (ajaxLoading.value) {
    return
  }
  ajaxLoading.value = true
  for (const projectId of valueIds.value) {
    if (!projects.value[projectId]) {
      await findProjects('' + projectId)
    }
  }
  inputValObjects.value = getValueObjects()
  ajaxLoading.value = false
})

const select = ref(null)

const instance = getCurrentInstance()?.proxy

onMounted(() => {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  ncSelect.value = (select.value! as any).ncSelect
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  id.value = (instance as any)._uid
})

const isSelectable = (option: Project) => option.id > 0

const getProjectObject = (id: number) => {
  return projects.value[id] || { id, name: id, year: -1, type: '' }
}

const getValueObjects = () => {
  const result = valueIds.value.map((projectId) => getProjectObject(projectId))
  return props.multiple ? result : (result.length > 0) ? result[0] : undefined
}

const emit = defineEmits(['error'])

const findProjects = async (query: string) => {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  await appData.searchProjects(query, <E extends AppError>(error: E) => emit('error', { error, context: error.context }))
  return true
}
</script>
<style lang="scss">
ul[id$="-projects-select__listbox"] {
  li.vs__dropdown-option.vs__dropdown-option--disabled {
    background: var(--color-background-dark); // var(--vs-state-disabled-bg);
    color: var(--vs-state-disabled-color);
    cursor: default; // var(--vs-state-disabled-cursor);
    font-weight: bold;
    font-style: italic;
  }
}
</style>
