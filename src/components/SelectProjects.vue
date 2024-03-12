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
                          v-model="inputValObjects"
                          v-bind="$attrs"
                          :input-id="id + '-projects-select-input'"
                          :options="projectsArray"
                          :selectable="(option) => option.id > 0"
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

<script>
import { appName } from '../app/app-info.js'
import { set as vueSet } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import SelectWithSubmitButton from '@rotdrop/nextcloud-vue-components/lib/components/SelectWithSubmitButton.vue'

/**
 * Select multiple or a single project. The provided value is always an array of project ids.
 */
export default {
  name: 'SelectProjects',
  components: {
    SelectWithSubmitButton,
  },
  inheritAttrs: false,
  props: {
    multiple: {
      type: Boolean,
      default: true,
    },
    value: {
      type: [Array, Object, String, Number],
      default: () => [],
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
  data() {
    return {
      inputValObjects: [],
      projects: {},
      ajaxLoading: false,
      ncSelect: undefined,
      id: null,
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
      const projects = Object.values(this.projects).sort((p1, p2) => {
        const p1year = p1?.year || -1
        const p2year = p2?.year || -1
        return p1year === p2year ? (p1.name > p2.name) - (p1.name < p2.name) : -(p1year - p2year)
      })
      if (projects.length === 0) {
        return []
      }
      let index = 0
      let fakeId = -1
      while (index < projects.length) {
        const year = projects[index].year
        const yearName = year < 0 || year < 2000 ? t(appName, 'Permanent') : '' + year
        projects.splice(index, 0, { id: fakeId--, name: yearName, year })
        ++index
        while (++index < projects.length && projects[index].year === year) { /* nothing */ }
      }
      return projects
    },
  },
  watch: {
    async value(newValue) {
      if (this.ajaxLoading) {
        return
      }
      if (this.multiple) {
        if (newValue.length === 0) {
          this.inputValObjects = []
          return
        }
      } else {
        if (!newValue) {
          this.inputValObjects = null
          return
        }
        newValue = [newValue]
      }
      this.ajaxLoading = true
      for (const projectId of newValue) {
        if (!this.projects[projectId]) {
          await this.findProjects(projectId)
        }
      }
      this.inputValObjects = this.getValueObjects()
      this.ajaxLoading = false
    },
  },
  mounted() {
    this.ncSelect = this.$refs.select.ncSelect
    this.id = this._uid
  },
  methods: {
    info(...args) {
      console.info(this.$options.name, ...args)
    },
    getProjectObject(id) {
      return this.projects[id] || { id, name: id, year: -1 }
    },
    getValueObjects() {
      const value = Array.isArray(this.value) ? this.value : (this.value || this.value === 0 ? [this.value] : [])
      const result = value.filter((project) => project !== '' && typeof project !== 'undefined').map(
        (project) => {
          // project can be a simple project id if multiple == false
          return this.getProjectObject(project?.id || project)
        },
      )
      return this.multiple ? result : (result.length > 0) ? result[0] : undefined
    },
    async findProjects(query) {
      query = typeof query === 'string' ? encodeURI(query) : ''
      if (query !== '') {
        query = '/' + query
      }
      try {
        const response = await axios.get(generateUrl(`/apps/${appName}/projects/search${query}`), {
          params: { limit: 10 },
        })
        if (response.data.length > 0) {
          for (const project of response.data) {
            vueSet(this.projects, project.id, project)
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
