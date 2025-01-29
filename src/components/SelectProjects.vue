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
<script lang="ts">
import { appName } from '../config.ts'
import { translate as t } from '@nextcloud/l10n'
import consoleMixin from '../mixins/console.ts'
import SelectWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/SelectWithSubmitButton.vue'
import useAppDataStore from '../stores/app-data.ts'
import type { PropType } from 'vue'
import type { Project, ProjectTemporalType } from '../stores/app-data.ts'

type OnlyIdType = { id: number }
type ProjectItemType = Project | (OnlyIdType & { name: string, year: number, type: ProjectTemporalType })
type IdType = OnlyIdType | ProjectItemType
type InputObjectType = IdType | number
type ValueType = InputObjectType[]|InputObjectType|undefined

const isIdType = (arg: ValueType): arg is IdType => !!arg && !Array.isArray(arg) && (typeof arg !== 'number')
const isIdTypeArray = (arg: ValueType): arg is IdType[] => !!arg && Array.isArray(arg) && (arg.length === 0 || (typeof arg[0] !== 'number'))

/**
 * Select multiple or a single project. The provided value is always an array of project ids.
 */
export default {
  name: 'SelectProjects',
  components: {
    SelectWithSubmitButton,
  },
  mixins: [
    consoleMixin,
  ],
  inheritAttrs: false,
  props: {
    multiple: {
      type: Boolean,
      default: true,
    },
    value: {
      type: [Array, Object, Number] as PropType<ValueType>,
      default: undefined,
    },
    clearable: {
      type: Boolean,
      default: true,
    },
    // clear all options, only makes sense if multiple == true
    clearAction: {
      type: Boolean,
      default: true,
    },
    label: {
      type: String,
      required: true,
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
  },
  setup() {
    const appData = useAppDataStore()
    return {
      appData,
      projects: appData.projects as ProjectItemType,
    }
  },
  data() {
    return {
      inputValObjects: [] as undefined|Project|Project[],
      ajaxLoading: false,
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      ncSelect: undefined as any,
      id: null as null|string,
    }
  },
  computed: {
    isLoading() {
      return (this.loading || this.ajaxLoading) && this.loadingIndicator
    },
    projectsArray() {
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
      const projects = Object.values(this.projects).sort((a, b) => {
        const p1 = a as Project
        const p2 = b as Project
        const p1year = p1?.year || -1
        const p2year = p2?.year || -1
        return p1year === p2year ? p1.name.localeCompare(p2.name) : -(p1year - p2year)
      })
      if (projects.length === 0) {
        return []
      }
      let index = 0
      let fakeId = -1
      while (index < projects.length) {
        const project = projects[index]
        const year = project.year
        const yearName = project.type === 'permanent' ? t(appName, 'Permanent') : '' + year
        projects.splice(index, 0, { id: fakeId--, name: yearName, year, type: '' })
        ++index
        while (++index < projects.length && projects[index].year === year) { /* nothing */ }
      }
      return projects
    },
    valueIds() {
      if (!this.value || (Array.isArray(this.value) && this.value.length === 0)) {
        return []
      }
      const value = this.value
      if (!Array.isArray(value)) {
        return [isIdType(value) ? value.id : value]
      }
      if (isIdTypeArray(value)) {
        return value.map((project) => project?.id).filter(id => !!id)
      } else {
        return (value as number[] /* TS fails to detect this */).filter(id => !!id)
      }
    },
  },
  watch: {
    async value() {
      if (this.ajaxLoading) {
        return
      }
      this.ajaxLoading = true
      for (const projectId of this.valueIds) {
        if (!this.projects[projectId]) {
          await this.findProjects('' + projectId)
        }
      }
      this.inputValObjects = this.getValueObjects()
      this.ajaxLoading = false
    },
  },
  mounted() {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    this.ncSelect = (this.$refs!.select! as any).ncSelect
    this.id = this._uid
  },
  methods: {
    isSelectable(option: Project) {
      return option.id > 0
    },
    getProjectObject(id: number) {
      return this.projects[id] || { id, name: id, year: -1, type: '' }
    },
    getValueObjects() {
      const result = this.valueIds.map((projectId) => this.getProjectObject(projectId))
      return this.multiple ? result : (result.length > 0) ? result[0] : undefined
    },
    async findProjects(query: string) {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      await this.appData.searchProjects(query, (error: any, context: object) => this.$emit('error', { error, context }))
      return true
    },
  },
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
