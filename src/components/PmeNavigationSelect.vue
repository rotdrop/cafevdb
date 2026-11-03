<!--
 - SPDX-FileCopyrightText: 2026 Claus-Justus Heine
 - SPDX-License-Identifier: AGPL-3.0-or-later
 -->
<template>
  <div class="pme-navigation-select" :class="{ 'pme-navigation-select--disabled': state.disabled }">
    <label class="pme-navigation-select__label" :for="selectId">{{ state.caption }}</label>
    <NcSelect :modelValue="selected"
              :options="state.options"
              :inputId="selectId"
              :uid="`pme-navigation-select-${uid}`"
              :labelOutside="true"
              :disabled="state.disabled"
              :clearable="false"
              :searchable="searchable"
              :selectable="(option: PmeNavigationOption) => !option.disabled"
              :getOptionKey="(option: PmeNavigationOption) => option.value"
              :appendToBody="true"
              placement="bottom"
              @update:modelValue="choose"
    >
      <template #search="{ attributes, events }">
        <input v-bind="{ ...attributes, ref: undefined }"
               ref="searchInput"
               class="vs__search"
               autocomplete="off"
               v-on="events"
        >
      </template>
      <template #selected-option="option">
        <span class="pme-navigation-select__caption">{{ state.caption }}</span>
        <span class="pme-navigation-select__value">{{ option.label }}</span>
      </template>
    </NcSelect>
  </div>
</template>

<script setup lang="ts">
import type {
  PmeNavigationOption,
  PmeNavigationSelectControls,
  PmeNavigationSelectProps,
} from '../types/pme-navigation-select.ts'

import { computed, ref, useId, useTemplateRef, watch } from 'vue'
import NcSelect from '@nextcloud/vue/components/NcSelect'

const props = withDefaults(defineProps<PmeNavigationSelectProps>(), {
  modelValue: null,
  searchThreshold: Infinity,
})
const emit = defineEmits<{
  'update:modelValue': [value: string]
  change: [value: string]
}>()
const suppliedState = ref<PmeNavigationSelectProps>()
const state = computed(() => suppliedState.value ?? props)
const uid = useId()
const selectId = computed(() => state.value.inputId || `pme-navigation-select-${uid}`)
const searchInput = useTemplateRef('searchInput')
const value = ref(props.modelValue)
// Without a selection the placeholder takes the value's place, keeping the caption visible.
const selected = computed(() => state.value.options.find((option) => option.value === value.value)
  ?? { value: '', label: state.value.placeholder ?? '', disabled: true })
const searchable = computed(() => state.value.options.length > (state.value.searchThreshold ?? Infinity))

const choose = (option: PmeNavigationOption|null) => {
  if (state.value.disabled || !option || option.disabled || option.value === value.value) {
    return
  }
  value.value = option.value
  emit('update:modelValue', option.value)
  emit('change', option.value)
}
const getValue = () => value.value ?? null
const focus = () => searchInput.value?.focus()
const open = () => {
  if (state.value.disabled || !searchInput.value) {
    return
  }
  focus()
  if (searchInput.value.getAttribute('aria-expanded') !== 'true') {
    searchInput.value.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', keyCode: 40, bubbles: true }))
  }
}
const close = () => {
  searchInput.value?.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', keyCode: 27, bubbles: true }))
  searchInput.value?.blur()
}
const setState = (newState: PmeNavigationSelectProps) => {
  suppliedState.value = newState
  value.value = newState.modelValue ?? null
}
watch(() => props.modelValue, (newValue) => {
  if (!suppliedState.value) {
    value.value = newValue
  }
})
defineExpose({ setState, getValue, open, close, focus } satisfies PmeNavigationSelectControls)
</script>

<style scoped lang="scss">
.pme-navigation-select {
  display: inline-block;
  height: 28px;

  :deep(.nc-select.v-select.select) {
    --default-clickable-area: 28px;
    --vs-border-radius: var(--border-radius-element, var(--border-radius-large));
    --vs-border-color: transparent;
    --vs-search-input-bg: transparent;
    width: auto;
    min-width: 0;
    min-height: 0;
    height: 28px;
    margin: 0;

    .vs__dropdown-toggle {
      height: 28px;
      min-height: 0;
      padding: 0 0 0 8px;
      background-color: var(--color-primary-element-light);
      color: var(--color-primary-element-light-text);
      cursor: pointer;
    }

    &:not(.vs--disabled) .vs__dropdown-toggle:hover {
      background-color: var(--color-primary-element-light-hover);
    }

    .vs__selected-options {
      flex-wrap: nowrap;
      height: 28px;
      min-height: 0;
      padding: 0;
    }

    // NcSelect positions these absolutely, assuming a fixed width.
    .vs__selected,
    .vs__actions {
      position: static;
      transform: none;
      margin: 0;
    }

    .vs__selected-options {
      flex: 0 0 auto;
    }

    .vs__selected {
      gap: 4px;
      min-height: 0;
      margin: 0;
      padding: 0;
      border: none;
      color: inherit;
      white-space: nowrap;
    }

    // Shrink to the caption and value instead of the search input's intrinsic width.
    .vs__search {
      flex: 0 0 0;
      width: 0;
      min-width: 0;
      height: 28px;
      min-height: 0;
      margin: 0;
      padding: 0;
      border: none;
      cursor: pointer;
    }

    &.vs--searchable.vs--open .vs__search {
      cursor: text;
    }

    &.vs--searching {
      .vs__selected {
        display: none;
      }

      .vs__search {
        flex-basis: 6em;
      }
    }

    .vs__actions {
      padding: 0 4px 0 0;
    }

    .vs__open-indicator-button {
      width: 20px;
      height: 28px;
      min-height: 0;
      color: inherit;
    }
  }
}

.pme-navigation-select__caption {
  font-weight: bold;
}

.pme-navigation-select__label {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}
</style>

<style lang="scss">
// The menu is appended to the body; NcSelect derives its id from the uid prop.
.vs__dropdown-menu[id^="vs-pme-navigation-select-"] {
  min-width: 0;
}
</style>
