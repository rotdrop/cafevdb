<script>
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022, 2023 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
</script>
<template>
  <form class="select-projects" @submit.prevent="">
    <div v-if="loading" class="loading" />
    <div class="input-wrapper">
      <label :for="id">{{ label }}</label>
      <Multiselect :id="id"
                   v-model="inputValObjects"
                   v-tooltip="active ? false : tooltip"
                   v-bind="$attrs"
                   :options="projectsArray"
                   group-values="projects"
                   group-label="year"
                   :group-select="false"
                   :options-limit="100"
                   :placeholder="placeholder === undefined ? label : placeholder"
                   :hint="hint"
                   :show-labels="true"
                   :searchable="searchable"
                   track-by="id"
                   label="name"
                   class="multiselect-vue"
                   :multiple="multiple"
                   :tag-width="60"
                   :disabled="disabled"
                   @input="emitInput"
                   @search-change="asyncFindProjects"
                   @open="active = true"
                   @close="active = false"
      />
      <input v-if="clearButton"
             type="submit"
             class="clear-button icon-delete"
             value=""
             :disabled="disabled"
             @click="clearSelection"
      >
    </div>
    <p v-if="hint !== ''" class="hint">
      {{ hint }}
    </p>
  </form>
</template>

<script>

import { set as vueSet } from 'vue'
import { appName } from '../app/app-info.js'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import Multiselect from '@nextcloud/vue/dist/Components/NcMultiselect'

let uuid = 0

/**
 * Select multiple or a single project. The provided value is always an array of project ids.
 */
export default {
  name: 'SelectProjects',
  components: {
    Multiselect,
  },
  props: {
    searchable: {
      type: Boolean,
      default: true,
    },
    multiple: {
      type: Boolean,
      default: true,
    },
    label: {
      type: String,
      required: true,
    },
    hint: {
      type: String,
      default: '',
    },
    value: {
      type: [Array, Object, String, Number],
      default: () => [],
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    clearButton: {
      type: Boolean,
      default: true,
    },
    placeholder: {
      type: String,
      default: undefined,
    },
    tooltip: {
      type: [Object, String],
      default: undefined,
    },
  },
  data() {
    return {
      inputValObjects: [],
      projects: {},
      loading: true,
      active: false,
    }
  },
  computed: {
    id() {
      return 'select-projects-' + this.uuid
    },
    projectsArray() {
      const groupedValues = {}
      for (const project of Object.values(this.projects)) {
        const year = project.year
        if (groupedValues[year] === undefined) {
          groupedValues[year] = {
            year,
            projects: [project],
          }
        } else {
          groupedValues[year].projects.push(project)
        }
      }
      return Object.values(groupedValues).sort((p1, p2) => -(p1.year - p2.year))
    },
  },
  watch: {
    value(newVal, oldVal) {
      this.inputValObjects = this.getValueObjects()
    },
  },
  created() {
    this.uuid = uuid.toString()
    uuid += 1
    this.asyncFindProjects('').then((result) => {
      this.inputValObjects = this.getValueObjects()
      this.loading = false
    })
  },
  methods: {
    getValueObjects() {
      const value = Array.isArray(this.value) ? this.value : (this.value || this.value === 0 ? [this.value] : [])
      const result = value.filter((project) => project !== '' && typeof project !== 'undefined').map(
        (project) => {
          const id = project.id !== undefined ? project.id : project
          if (typeof this.projects[id] === 'undefined') {
            return {
              id,
              name: id,
            }
          }
          return this.projects[id]
        }
      )
      return this.multiple ? result : (result.length > 0) ? result[0] : undefined
    },
    clearSelection() {
      this.inputValObjects = []
      this.emitInput() // why is this needed?
    },
    emitInput() {
      this.emit('input')
      this.emitUpdate()
    },
    emitUpdate() {
      this.emit('update')
    },
    emit(event) {
      this.$emit(event, this.inputValObjects)
    },
    asyncFindProjects(query) {
      query = typeof query === 'string' ? encodeURI(query) : ''
      if (query !== '') {
        query = '/' + query
      }
      return axios
        .get(generateUrl(`/apps/${appName}/projects/search${query}`), {
          params: { limit: 10 },
        })
        .then((response) => {
          if (response.data.length > 0) {
            for (const project of response.data) {
              vueSet(this.projects, project.id, project)
            }
            return true
          }
          return false
        }).catch((error) => {
          this.$emit('error', error)
        })
    },
  },
}
</script>
<style lang="scss" scoped>
.select-projects {
  position:relative;
  .loading {
    position:absolute;
    width:0;
    height:0;
    top:50%;
    left:50%;
  }
  .input-wrapper {
    display: flex;
    flex-wrap: wrap;
    width: 100%;
    max-width: 400px;
    align-items: center;
    div.multiselect.multiselect-vue::v-deep {
      &:not(.multiselect--active) {
        height:35.2px;
      }
      flex-grow:1;
      &:hover .multiselect__tags {
        border-color: var(--color-primary-element);
        outline: none;
      }
      &:hover + .clear-button {
        border-color: var(--color-primary-element) !important;
        border-left-color: transparent !important;
        z-index: 2;
      }
      &.multiselect--active + .clear-button {
        display:none;
      }
      + .clear-button {
        &:disabled {
          background-color: var(--color-background-dark) !important;
        }
        margin-left: -8px !important;
        border-left-color: transparent !important;
        border-radius: 0 var(--border-radius) var(--border-radius) 0 !important;
        background-clip: padding-box;
        background-color: var(--color-main-background) !important;
        opacity: 1;
        padding: 7px 6px;
        height:35.2px;
        width:35.2px;
        margin-right:0;
        z-index:2;
        &:hover, &:focus {
          border-color: var(--color-primary-element) !important;
          border-radius: var(--border-radius) !important;
        }
      }

      &.multiselect--single {
        .multiselect__content-wrapper li > span {
          &::before {
            background-image: var(--icon-checkmark-000);
            display:block;
          }
          &:not(.multiselect__option--selected):hover::before {
            visibility:hidden;
          }
        }
      }
      &.multiselect--multiple {
        .multiselect__content-wrapper li > span {
          &:not(.multiselect__option--selected):hover::before {
            visibility:hidden;
          }
        }
      }
    }

    label {
      width: 100%;
    }
  }

  .hint {
    color: var(--color-text-lighter);
    font-size:80%;
  }
}
</style>
