<!--
 - SPDX-FileCopyrightText: 2026 Claus-Justus Heine
 - SPDX-License-Identifier: AGPL-3.0-or-later
 -->
<template>
  <div class="dual-listbox">
    <div v-for="(pane, index) in panes" :key="index" class="dual-listbox__pane">
      <label :for="`${inputId}-${index}`">{{ pane.label }}</label>
      <div class="dual-listbox__filter">
        <TextFieldWithTrailingButton v-model="filters[index]"
                                     type="search"
                                     trailingButton="clear"
                                     hint=""
                                     :aria-label="`${t(appName, 'Filter')}: ${pane.label}`"
                                     :placeholder="t(appName, 'Filter')"
                                     :trailingButtonLabel="t(appName, 'show all')"
                                     :disabled="locked"
        >
          <template #icon>
            <SearchIcon :size="20" />
          </template>
        </TextFieldWithTrailingButton>
      </div>
      <div class="dual-listbox__info" aria-live="polite">
        <template v-if="!pane.total">
          {{ t(appName, 'Empty list') }}
        </template>
        <template v-else-if="filters[index]">
          {{ t(appName, 'Filtered') }} {{ pane.options.length }} {{ t(appName, 'from') }} {{ pane.total }}
        </template>
      </div>
      <button type="button"
              class="dual-listbox__move-all"
              :disabled="locked || !pane.options.some(option => !option.disabled)"
              @click="move(pane.options, index === 0)"
      >
        {{ index === 0 ? t(appName, 'Select all') : t(appName, 'Remove all') }}
      </button>
      <select :id="`${inputId}-${index}`"
              multiple
              size="12"
              :disabled="locked"
              :title="t(appName, 'Click on the names to move the respective person to the other box')"
              @change.stop="moveSelection($event, index === 0)"
      >
        <option v-for="option in pane.options"
                :key="option.value"
                :value="option.value"
                :disabled="option.disabled"
                :title="option.title"
        >
          {{ option.label }}
        </option>
      </select>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { DualListboxControls, DualListboxOption, DualListboxProps } from '../types/dual-listbox.ts'

import { translate as t } from '@nextcloud/l10n'
import { computed, ref, useId, watch } from 'vue'
import TextFieldWithTrailingButton from '@rotdrop/nextcloud-vue-components/lib/components/TextFieldWithTrailingButton.vue'
import SearchIcon from 'vue-material-design-icons/Magnify.vue'
import { appName } from '../config.ts'

const props = defineProps<DualListboxProps>()
const emit = defineEmits<{
  'update:modelValue': [values: string[]]
  change: [values: string[]]
}>()
const suppliedState = ref<DualListboxProps>()
const state = computed(() => suppliedState.value ?? props)
const uid = useId()
const inputId = computed(() => state.value.inputId || `dual-listbox-${uid}`)
const filters = ref(['', ''])
const locked = computed(() => state.value.disabled || state.value.readonly)
const panes = computed(() => [
  t(appName, 'Remaining Recipients'),
  t(appName, 'Selected Recipients'),
].map((label, index) => {
  const options = state.value.options.filter((option) => state.value.modelValue.includes(option.value) === (index === 1))
  const query = filters.value[index]!.toLocaleLowerCase()
  return {
    label,
    total: options.length,
    options: options.filter((option) => option.label.toLocaleLowerCase().includes(query)),
  }
}))

const move = (options: DualListboxOption[], selected: boolean) => {
  if (locked.value) {
    return
  }
  const values = new Set(state.value.modelValue)
  for (const option of options) {
    if (!option.disabled) {
      if (selected) {
        values.add(option.value)
      } else {
        values.delete(option.value)
      }
    }
  }
  const next = state.value.options.filter((option) => values.has(option.value)).map((option) => option.value)
  if (next.length === state.value.modelValue.length && next.every((value) => state.value.modelValue.includes(value))) {
    return
  }
  suppliedState.value = { ...state.value, modelValue: next }
  emit('update:modelValue', next)
  emit('change', next)
}
const moveSelection = (event: Event, selected: boolean) => {
  const select = event.target as HTMLSelectElement
  const values = new Set(Array.from(select.selectedOptions, (option) => option.value))
  select.selectedIndex = -1
  move(state.value.options.filter((option) => values.has(option.value)), selected)
}
const setState = (newState: DualListboxProps) => {
  suppliedState.value = newState
}
watch(() => ({ ...props }), () => {
  suppliedState.value = undefined
}, { deep: true })
defineExpose({ setState } satisfies DualListboxControls)
</script>

<style scoped lang="scss">
.dual-listbox {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  width: 100%;
}

.dual-listbox__pane {
  display: flex;
  flex: 1 1 240px;
  flex-direction: column;
  min-width: 0;
  gap: 4px;

  label {
    font-weight: bold;
  }

  select {
    width: 100%;
    min-height: 200px;
    background: var(--color-main-background);
    color: var(--color-main-text);
    border: 2px solid var(--color-border-maxcontrast);
    border-radius: var(--border-radius-large);
  }
}

.dual-listbox__filter {
  min-width: 0;
}

.dual-listbox__info {
  min-height: 1.5em;
}

.dual-listbox__move-all {
  width: 100%;
}
</style>
