<!--
 - Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  <div :class="['input-wrapper', { empty, required }]">
    <label v-if="!labelOutside && inputLabel" :for="selectId" class="select-with-submit-button-label">
      {{ inputLabel }}
    </label>
    <div :class="['alignment-wrapper', ...flexContainerClasses]">
      <div v-if="true || $slots.alignedBefore" :class="['aligned-before', ...flexItemClasses]">
        <slot name="alignedBefore" />
      </div>
      <div :class="['select-combo-wrapper', { loading },...flexItemClasses]">
        <NcSelect ref="ncSelect"
                  v-bind="$attrs"
                  v-tooltip="tooltipToShow"
                  :value="value"
                  :label-outside="true"
                  :clearable="clearable"
                  :disabled="disabled"
                  :required="required"
                  :input-id="selectId"
                  v-on="$listeners"
                  @open="active = true"
                  @close="active = false"
        >
          <!-- pass through scoped slots -->
          <template v-for="(_, scopedSlotName) in $scopedSlots" #[scopedSlotName]="slotData">
            <slot :name="scopedSlotName" v-bind="slotData" />
          </template>

          <!-- pass through normal slots -->
          <template v-for="(_, slotName) in $slots" #[slotName]>
            <slot :name="slotName" />
          </template>

          <!-- after iterating over slots and scopedSlots, you can customize them like this -->
          <!-- <template v-slot:overrideExample>
               <slot name="overrideExample" />
               <span>This text content goes to overrideExample slot</span>
               </template> -->
        </NcSelect>
        <input v-tooltip="active ? false : t(appName, 'Click to submit your changes.')"
               type="submit"
               class="icon-confirm"
               value=""
               :disabled="disabled"
               @click="emitUpdate"
        >
      </div>
      <div v-if="true || $slots.alignedAfter" :class="['aligned-before', ...flexItemClasses]">
        <slot name="alignedAfter" />
      </div>
    </div>
    <p v-if="hint !== ''" class="hint">
      {{ hint }}
    </p>
  </div>
</template>
<script>

import { appName } from '../app/app-info.js'
import { NcSelect } from '@nextcloud/vue'

export default {
  name: 'SelectWithSubmitButton',
  components: {
    NcSelect,
  },
  inheritAttrs: false,
  props: {
    // show an loading indicator on the wrapper select
    loading: {
      type: Boolean,
      default: false,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    // clearable allows deselection of the last item
    clearable: {
      type: Boolean,
      default: true,
    },
    // required blocks the final submit if no value is selected
    required: {
      type: Boolean,
      default: false,
    },
    labelOutside: {
      type: Boolean,
      default: false,
    },
    inputLabel: {
      type: String,
      required: false,
      default: null,
    },
    inputId: {
      type: String,
      required: false,
      default: null,
    },
    hint: {
      type: String,
      required: false,
      default: null,
    },
    tooltip: {
      type: [Object, String, Boolean],
      required: false,
      default: undefined,
    },
    value: {
      type: [String, Number, Object, Array],
      default: null,
    },
    flexContainerClasses: {
      type: Array,
      default: () => ['flex-justify-left', 'flex-align-center'],
    },
    flexItemClasses: {
      type: Array,
      default: () => ['flex-justify-left', 'flex-align-center'],
    },
  },
  data() {
    return {
      active: false,
      id: this._uid,
      ncSelect: undefined,
    }
  },
  computed: {
    empty() {
      return !this.value || (Array.isArray(this.value) && this.value.length === 0)
    },
    tooltipToShow() {
      if (this.active) {
        return false
      }
      if (this.tooltip) {
        return this.tooltip
      }
      if (this.empty && this.required) {
        return t(appName, 'Please select an item!')
      }
      return false
    },
    selectId() {
      return this.inputId || this._uid + '-select-input-id'
    },
    listenersToForward() {
      const listeners = { ...this.$listeners }
      // delete listeners.input
      this.info('FORWARDING LISTENERS', listeners)
      return listeners
    },
    attributesToForward() {
      const attributes = { ...this.$attrs }
      // delete attributes.value
      this.info('FORWARDING ATTRIBUTES', attributes)
      return attributes
    },
  },
  created() {
    this.id = this._uid
  },
  mounted() {
    this.ncSelect = this.$refs?.ncSelect
  },
  methods: {
    info(...args) {
      console.info(this.$options.name, ...args)
    },
    emitUpdate() {
      if (this.required && this.empty) {
        this.$emit('error', t(appName, 'An empty value is not allowed, please make your choice!'))
      } else {
        this.$emit('input', this.value)
        this.$emit('update:modelValue', this.value)
        this.$emit('update', this.value)
      }
    },
  },
}
</script>
<style lang="scss" scoped>
.input-wrapper {
  position:relative;
  display: flex;
  flex-wrap: wrap;
  flex-direction: column;
  width: 100%;
  &::v-deep .alignment-wrapper {
    display: flex;
    flex-grow: 1;
    &.flex- {
      &align- {
        &center {
          align-items: center;
        }
        &baseline {
          align-items: baseline;
        }
        &stretch {
          align-items: stretch;
        }
      }
      &justify- {
        &center {
          justify-content: center;
        }
        &start {
          justify-content: flex-start;
        }
        &left {
          justify-content: left;
        }
      }
    }
  }
  .loading-indicator.loading {
    position:absolute;
    width:0;
    height:0;
    top:50%;
    left:50%;
  }
  label.select-with-submit-button-label {
    width: 100%;
  }
  .select-combo-wrapper {
    display: flex;
    align-items: stretch;
    flex-grow: 1;
    flex-wrap: nowrap;
    .v-select.select::v-deep {
      flex-grow:1;
      max-width:100%;
      .vs__dropdown-toggle {
        // substract the round borders for the overlay
        padding-right: calc(var(--default-clickable-area) - var(--vs-border-radius));
      }
      + .icon-confirm {
        flex-shrink: 0;
        width:var(--default-clickable-area);
        align-self: stretch;
        // align-self: stretch should do what we want here :)
        // height:var(--default-clickable-area);
        margin: 0 0 0 calc(0px - var(--default-clickable-area));
        z-index: 2;
        border-radius: var(--vs-border-radius) var(--vs-border-radius);
        border-style: none;
        background-color: rgba(0, 0, 0, 0);
        background-clip: padding-box;
        opacity: 1;
        &:hover, &:focus {
          &:not(:readonly), &:not(:disabled) {
            border: var(--vs-border-width) var(--vs-border-style) var(--color-primary-element);
            border-radius: var(--vs-border-radius);
            outline: 2px solid var(--color-main-background);
            background-color: var(--vs-search-input-bg);
          }
        }
      }
      &.vs--disabled + .icon-confirm {
        cursor: var(--vs-disabled-cursor);
      }
    }
  }
  &.empty.required:not(.loading) {
    .v-select.select::v-deep .vs__dropdown-toggle {
      border-color: red;
    }
  }
  .hint {
    color: var(--color-text-lighter);
    font-size:80%;
  }
}
</style>
<style lang="scss">
// in vue-select anything starting from
.vue-tooltip-user-info-popup.vue-tooltip .tooltip-inner {
  text-align: left !important;
  *:not(h4) {
    color: var(--color-text-lighter);
    font-size:80%;
  }
  h4 {
    font-weight: bold;
  }
}
</style>
