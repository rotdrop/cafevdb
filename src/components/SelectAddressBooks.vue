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
  <form class="select-address-books" @submit.prevent="">
    <div v-if="loading" class="loading" />
    <div class="input-wrapper">
      <label :for="id">{{ label }}</label>
      <Multiselect :id="id"
                   ref="multiselect"
                   v-model="inputValObjects"
                   v-tooltip="active ? false : tooltip"
                   v-bind="$attrs"
                   :options="addressBooksArray"
                   :options-limit="100"
                   :placeholder="placeholder === undefined ? label : placeholder"
                   :hint="hint"
                   :show-labels="true"
                   :searchable="searchable"
                   track-by="key"
                   label="displayName"
                   class="multiselect-vue"
                   :multiple="multiple"
                   :tag-width="60"
                   :disabled="disabled"
                   @input="emitInput"
                   @open="active = true"
                   @close="active = false"
      />
      <input v-if="clearButton && !resetButton"
             type="submit"
             class="clear-button icon-delete"
             value=""
             :disabled="disabled"
             @click="clearSelection"
      >
      <input v-if="resetButton"
             type="submit"
             class="clear-button icon-history"
             value=""
             :disabled="disabled"
             @click="resetSelection"
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
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import { getInitialState } from '../services/initial-state-service.js'

let uuid = 0
export default {
  name: 'SelectAddressBooks',
  components: {
    Multiselect,
  },
  props: {
    searchable: {
      type: Boolean,
      default: true,
    },
    searchScope: {
      type: String,
      default: 'addressBooks',
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
      type: [Array, String, Object, Number],
      default: () => [],
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    clearButton: {
      type: Boolean,
      default: false,
    },
    resetButton: {
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
      initialValObjects: [],
      addressBooks: {},
      loading: true,
      active: false,
    }
  },
  computed: {
    id() {
      return 'settings-address-books-' + this.uuid
    },
    addressBooksArray() {
      return Object.values(this.addressBooks)
    },
  },
  watch: {
    value(newVal, oldVal) {
      this.inputValObjects = this.getValueObject()
      if (this.multiple && this.inputValObjects.length === 1 && this.inputValObjects[0].UID === 0) {
        this.$refs.multiselect.$refs.VueMultiselect.deactivate()
      }
    },
  },
  async created() {
    this.uuid = uuid.toString()
    uuid += 1
    const initialState = getInitialState()
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
      for (const book of Object.values(this.addressBooks)) {
        if (!book.isSystemAddressBook) {
          this.inputValObjects.push(book)
        }
      }
      this.emitInput() // why is this needed?
    } else {
      this.inputValObjects = this.getValueObject()
    }
    if (this.resetButton) {
      this.initialValObjects = this.inputValObjects
    }
    this.loading = false
  },
  methods: {
    getValueObject(noUndefined) {
      const value = Array.isArray(this.value) ? this.value : (this.value || this.value === 0 ? [this.value] : [])
      let everybody = false
      let result = value.filter((contact) => contact !== '' && typeof contact !== 'undefined').map(
        (contact) => {
          const key = contact.key !== undefined ? contact.key : (contact.UID || contact.URI || contact)
          if (key === 0) {
            everybody = true
          }
          if (typeof this.addressBooks[key] === 'undefined') {
            return noUndefined ? null : { key, uid: key, FN: key }
          }
          return this.addressBooks[key]
        }
      ).filter((contact) => contact !== null && contact !== undefined)
      if (this.multiple) {
        if (everybody) {
          result = [this.addressBooks[0]]
        }
        for (const [contactKey, contact] of Object.entries(this.addressBooks)) {
          if (contactKey !== 0 && contactKey !== '0') {
            contact.$isDisabled = everybody
          }
        }
      }
      return this.multiple ? result : (result.length > 0) ? result[0] : undefined
    },
    clearSelection() {
      this.inputValObjects = []
      this.emitInput() // why is this needed?
    },
    resetSelection() {
      this.inputValObjects = this.initialValObjects
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
    provideAddressBooks() {
      return axios
        .get(generateUrl(`/apps/${appName}/contacts/address-books`))
        .then((response) => {
          for (const [key, book] of Object.entries(response.data)) {
            vueSet(this.addressBooks, key, book)
          }
          // console.info('ADDRESSBOOKS', this.addressBooks)
          return true
        }).catch((error) => {
          this.$emit('error', error)
        })
    },
  },
}
</script>
<style lang="scss" scoped>
.select-address-books {
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

      .multiselect__tag {
        position: relative;
        padding-right: 18px;
        .multiselect__tag-icon {
          cursor: pointer;
          margin-left: 7px;
          position: absolute;
          right: 0;
          top: 0;
          bottom: 0;
          font-weight: 700;
          font-style: initial;
          width: 22px;
          text-align: center;
          line-height: 22px;
          transition: all 0.2s ease;
          border-radius: 5px;
        }

        .multiselect__tag-icon:after {
          content: "×";
          color: #266d4d;
          font-size: 14px;
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
