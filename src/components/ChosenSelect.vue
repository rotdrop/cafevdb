<!--
 - SPDX-FileCopyrightText: 2026 Claus-Justus Heine
 - SPDX-License-Identifier: AGPL-3.0-or-later
 -->
<template>
  <div class="chosen-select" :class="{ 'chosen-select--dropup': state.dropup }">
    <label class="chosen-select__label" :for="selectId">{{ state.label }}</label>
    <NcSelect :modelValue="selectedChoices"
              :options="choices"
              :multiple="state.multiple"
              :inputId="selectId"
              :inputLabel="state.label"
              :placeholder="state.placeholder"
              :disabled="blocked"
              :clearable="!!state.multiple || !!state.allowSingleDeselect"
              :searchable="searchable"
              :filterable="false"
              :selectable="isSelectable"
              :getOptionKey="getChoiceKey"
              :getOptionLabel="getChoiceLabel"
              :keepOpen="!!state.multiple"
              :deselectFromDropdown="false"
              :appendToBody="false"
              @update:modelValue="choose"
              @search="search = $event"
              @open="emit('open')"
              @close="emit('close')"
    >
      <template #search="{ attributes, events }">
        <input v-bind="{ ...attributes, ref: undefined }"
               ref="searchInput"
               class="vs__search"
               :required="state.required && empty"
               :readonly="!searchable && !state.required"
               autocomplete="off"
               v-on="{ ...events, keydown: (event: KeyboardEvent) => onKeydown(event, events) }"
               @beforeinput="!searchable && $event.preventDefault()"
        >
      </template>
      <template #option="option">
        <strong v-if="option.group" class="chosen-select__group">{{ option.label }}</strong>
        <span v-else>{{ option.label }}</span>
      </template>
      <template v-if="state.noResultsText" #no-options>
        {{ state.noResultsText }}: {{ search }}
      </template>
    </NcSelect>
  </div>
</template>

<script setup lang="ts">
import type { ChosenSelectControls, ChosenSelectProps, ChosenSelectValue } from '../types/chosen-select.ts'

import { translate as t } from '@nextcloud/l10n'
import { computed, ref, useId, useTemplateRef, watch, watchEffect } from 'vue'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { appName } from '../config.ts'

type Choice = {
  key: string
  value: string
  label: string
  disabled: boolean
  group: boolean
}

const props = withDefaults(defineProps<ChosenSelectProps>(), {
  modelValue: null,
})
const emit = defineEmits<{
  'update:modelValue': [value: ChosenSelectValue]
  change: [value: ChosenSelectValue]
  open: []
  close: []
}>()
const suppliedState = ref<ChosenSelectProps>()
const state = computed(() => suppliedState.value ?? props)
const uid = useId()
const selectId = computed(() => state.value.inputId || `chosen-select-${uid}`)
const searchInput = useTemplateRef('searchInput')
const search = ref('')
const blocked = computed(() => state.value.disabled || state.value.readonly)
const searchable = computed(() => !state.value.disableSearch
  && (state.value.multiple || state.value.options.length > (state.value.disableSearchThreshold ?? 0)))

const normalize = (value: ChosenSelectValue = null) => {
  const values = value === null ? [] : Array.isArray(value) ? value : [value]
  const available = new Set(state.value.options.map((option) => option.value))
  const result = [...new Set(values)].filter((value) => available.has(value))
  return state.value.multiple ? result : result.slice(0, 1)
}
const selectedValues = ref(normalize(props.modelValue))
const empty = computed(() => state.value.multiple ? selectedValues.value.length === 0 : !selectedValues.value[0])
watchEffect(() => {
  searchInput.value?.setCustomValidity(state.value.required && empty.value ? t(appName, 'Please select an item.') : '')
})
const text = (value: string) => value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase()
const allChoices = computed(() => {
  const groups = state.value.groups ?? []
  const choice = (option: ChosenSelectProps['options'][number]): Choice => ({
    key: `option:${option.value}`,
    value: option.value,
    label: option.label,
    group: false,
    disabled: !!option.disabled || !!groups.some((group) => group.disabled && option.groups?.includes(group.value)),
  })
  return state.value.options.map(choice)
})
const selectedChoices = computed(() => allChoices.value.filter((option) => selectedValues.value.includes(option.value)
  && (state.value.multiple || option.value !== '' || option.label !== '')))
const choices = computed(() => {
  const words = searchable.value ? text(search.value).trim().split(/\s+/).filter(Boolean) : []
  const matches = (option: Choice) => words.every((word) => text(option.label).includes(word))
  const groups = state.value.groups ?? []
  const knownGroups = new Set(groups.map((group) => group.value))
  const result: Choice[] = []
  const ungrouped = state.value.options.filter((option) => !option.groups?.some((group) => knownGroups.has(group)))
  const matchingChoices = allChoices.value.filter((option) => matches(option)
    && (!state.value.multiple || !selectedValues.value.includes(option.value))
    && (state.value.multiple || option.value !== '' || option.label !== ''))
  for (const option of matchingChoices) {
    if (ungrouped.some((entry) => entry.value === option.value)) {
      result.push(option)
    }
  }
  for (const group of groups) {
    const members = matchingChoices.filter((option) => state.value.options.some((entry) =>
      entry.value === option.value && entry.groups?.includes(group.value)))
    if (members.length > 0) {
      result.push({ key: `group:${group.value}`, value: group.value, label: group.label, group: true, disabled: true }, ...members)
    }
  }
  return result
})
const getChoiceKey = (option: Choice) => option.key
const getChoiceLabel = (option: Choice) => option.label
const isSelectable = (option: Choice) => !blocked.value && !option.disabled && !option.group
const getValue = (): ChosenSelectValue => state.value.multiple ? [...selectedValues.value] : selectedValues.value[0] ?? null
const choose = (value: Choice|Choice[]|null) => {
  if (blocked.value) {
    return
  }
  const options = value === null ? [] : Array.isArray(value) ? value : [value]
  if (options.some((option) => !isSelectable(option) && !selectedValues.value.includes(option.value))) {
    return
  }
  if (!state.value.multiple && options.length === 0 && !state.value.allowSingleDeselect) {
    return
  }
  const values = new Set(options.map((option) => option.value))
  selectedValues.value = normalize(state.value.options.filter((option) => values.has(option.value)).map((option) => option.value))
  emit('update:modelValue', getValue())
  emit('change', getValue())
}
const onKeydown = (event: KeyboardEvent, events: Record<string, (event: KeyboardEvent) => void>) => {
  // Legacy Chosen callers explicitly disable deleting selections with Backspace.
  if (event.key === 'Backspace' && search.value === '') {
    return
  }
  events.keydown?.(event)
}
const focus = () => searchInput.value?.focus()
const open = () => {
  if (blocked.value || !searchInput.value) {
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
const setState = (value: ChosenSelectProps) => {
  suppliedState.value = value
  selectedValues.value = normalize(value.modelValue)
}
watch(() => props.modelValue, (value) => {
  if (!suppliedState.value) {
    selectedValues.value = normalize(value)
  }
})
watch(() => [state.value.options, state.value.multiple], () => {
  selectedValues.value = normalize(selectedValues.value)
})
defineExpose({ setState, getValue, open, close, focus } satisfies ChosenSelectControls)
</script>

<style scoped lang="scss">
.chosen-select {
  min-width: 4em;
  width: 100%;

  :deep(.v-select) {
    width: 100%;
    min-width: 0;
  }

  &--dropup :deep(.vs__dropdown-menu) {
    top: auto;
    bottom: 100%;
    border-radius: var(--vs-border-radius) var(--vs-border-radius) 0 0;
    border-top-style: var(--vs-border-style);
    border-bottom-style: none;
  }

  &--dropup :deep(.v-select.select.vs--open .vs__dropdown-toggle) {
    border-start-start-radius: 0;
    border-start-end-radius: 0;
    border-end-start-radius: var(--vs-border-radius);
    border-end-end-radius: var(--vs-border-radius);
    border-block-start-color: transparent;
    border-block-end-color: var(--vs-border-color);
  }
}

.chosen-select__group {
  font-weight: bold;
}

.chosen-select__label {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}
</style>
