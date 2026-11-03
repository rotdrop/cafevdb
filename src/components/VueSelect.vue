<!--
 - SPDX-FileCopyrightText: 2026 Claus-Justus Heine
 - SPDX-License-Identifier: AGPL-3.0-or-later
 -->
<template>
  <div ref="root"
       :class="[`${appName}-vue-select`, state.rootClass, {
         [`${appName}-vue-select--dropup`]: dropup,
         [`${appName}-vue-select--single-no-clear`]: !state.multiple && !state.allowSingleDeselect,
       }]"
       :aria-busy="creating"
  >
    <label :class="`${appName}-vue-select__label`" :for="selectId">{{ state.label }}</label>
    <NcSelect :modelValue="selectedChoices"
              :options="choices"
              :multiple="state.multiple"
              :inputId="selectId"
              :inputLabel="state.label"
              :labelOutside="state.labelOutside"
              :placeholder="placeholder"
              :disabled="blocked"
              :loading="creating"
              :clearable="!state.notEmpty && !state.multiple && state.allowSingleDeselect"
              :searchable="searchable"
              :clearSearchOnBlur="() => false"
              :deselectFromDropdown="false"
              :keepOpen="state.multiple && !state.closeAfterSelect"
              :filterable="false"
              :selectable="isSelectable"
              :getOptionKey="getChoiceKey"
              :getOptionLabel="getChoiceLabel"
              :appendToBody="false"
              @update:modelValue="selectChoices"
              @search="search = $event"
              @open="onOpen"
              @close="onClose"
    >
      <template #search="{ attributes, events }">
        <input v-bind="{ ...attributes, ref: undefined }"
               ref="searchInput"
               class="vs__search"
               :class="`${appName}-vue-select__input`"
               :placeholder="selectedValues.length === 0 ? placeholder : ''"
               :required="required && empty"
               :readonly="!searchable && !required"
               :aria-invalid="errorMessage ? true : undefined"
               :aria-describedby="errorMessage ? `${selectId}-error` : undefined"
               autocomplete="off"
               v-on="{ ...events, keydown: (event: KeyboardEvent) => onKeydown(event, events) }"
               @beforeinput="(creating || !searchable) && $event.preventDefault()"
               @blur="onBlur(events)"
        >
      </template>
      <template #option="option">
        <strong v-if="option.kind === 'group'" :class="`${appName}-vue-select__group`">{{ option.label }}</strong>
        <span v-else-if="option.kind === 'create'">{{ t(appName, 'Add "{value}"', { value: option.value }) }}</span>
        <span v-else
              :class="{ [`${appName}-vue-select__option--selected`]: selectedValues.includes(option.value) }"
              :title="option.title"
        >{{ option.label }}</span>
      </template>
      <template #selected-option-container="{ option }">
        <span :key="option.value"
              class="vs__selected"
              :class="[`${appName}-vue-select__selected`, {
                [`${appName}-vue-select__selected--move-on-focus`]: state.showMoveButtons === 'focus',
              }]"
              :data-value="option.value"
              :draggable="state.sortable && state.multiple && !blocked && !creating"
              @mousedown.stop
              @dragstart="startDrag($event, option.value)"
              @dragover.prevent
              @drop.prevent.stop="dropItem(option.value)"
              @dragend="draggedValue = undefined"
        >
          <span>{{ option.label }}</span>
          <template v-if="state.sortable && state.multiple && state.showMoveButtons">
            <button type="button"
                    :class="`${appName}-vue-select__move`"
                    :disabled="blocked || creating || selectedValues[0] === option.value"
                    :aria-label="t(appName, 'Move {label} earlier', { label: option.label })"
                    @click.stop="moveItem(selectedValues.indexOf(option.value), -1)"
            >
              <span aria-hidden="true">&lt;</span>
            </button>
            <button type="button"
                    :class="`${appName}-vue-select__move`"
                    :disabled="blocked || creating || selectedValues.at(-1) === option.value"
                    :aria-label="t(appName, 'Move {label} later', { label: option.label })"
                    @click.stop="moveItem(selectedValues.indexOf(option.value), 1)"
            >
              <span aria-hidden="true">&gt;</span>
            </button>
          </template>
          <button v-if="state.multiple"
                  type="button"
                  class="vs__deselect"
                  :disabled="blocked || creating || (state.notEmpty && selectedValues.length === 1)"
                  :aria-label="t(appName, 'Remove {label}', { label: option.label })"
                  @mousedown.prevent.stop
                  @click.stop="removeItem(option.value)"
          >
            <span aria-hidden="true">&times;</span>
          </button>
        </span>
      </template>
      <template v-if="state.noResultsText" #no-options>
        {{ state.noResultsText }}: {{ search }}
      </template>
    </NcSelect>
    <p v-if="errorMessage"
       :id="`${selectId}-error`"
       :class="`${appName}-vue-select__error`"
       role="alert"
    >
      {{ errorMessage }}
    </p>
    <template v-if="state.name">
      <input v-for="value in formValues"
             :key="value"
             type="hidden"
             :name="state.name"
             :value="value"
             :disabled="isDisabled"
      >
    </template>
  </div>
</template>

<script setup lang="ts">
import type {
  VueSelectCreateOptions,
  VueSelectGroup,
  VueSelectOption,
  VueSelectProps,
  VueSelectValue,
} from '../types/vue-select.ts'

import { translate as t } from '@nextcloud/l10n'
import {
  computed,
  nextTick,
  onBeforeUnmount,
  ref,
  useId,
  useTemplateRef,
  watch,
  watchEffect,
} from 'vue'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { appName } from '../config.ts'
import { createRemoteSelectOption } from '../services/select-option-creation.ts'
import { isAxiosErrorResponse } from '../toolkit/types/axios-type-guards.ts'
import Console from '../util/console.ts'

type Choice = {
  key: string
  kind: 'option'|'group'|'create'
  value: string
  label: string
  disabled: boolean
  title?: string
}

const props = withDefaults(defineProps<VueSelectProps>(), {
  modelValue: null,
  groups: () => [],
  multiple: false,
  disabled: false,
  readonly: false,
  locked: false,
  required: false,
  notEmpty: false,
  sortable: false,
  showMoveButtons: true,
  placeholder: '',
  inputId: undefined,
  name: undefined,
  create: false,
  createOnBlur: false,
  persist: false,
  closeAfterSelect: false,
  restoreOnBackspace: false,
  allowSingleDeselect: true,
  disableSearch: false,
  disableSearchThreshold: 0,
  noResultsText: undefined,
  dropup: false,
  hideSelected: false,
  removeOnBackspace: true,
  nativeOrder: false,
  blankPlaceholder: false,
  retainMissingValues: true,
  searchValues: true,
  labelOutside: true,
  multiplePlaceholder: true,
})

const emit = defineEmits<{
  'update:modelValue': [value: VueSelectValue]
  change: [value: VueSelectValue]
  error: [error: Error]
  open: []
  close: []
  create: [option: VueSelectOption]
}>()
const suppliedState = ref<typeof props>()
const state = computed(() => suppliedState.value ?? props)
const logger = new Console('VueSelect')
const suppliedRequired = ref<boolean>()
const required = computed(() => suppliedRequired.value ?? state.value.required)
const uid = useId()
const selectId = computed(() => state.value.inputId || `${appName}-vue-select-${uid}`)
const root = useTemplateRef('root')
const searchInput = useTemplateRef('searchInput')
const search = ref('')
const errorMessage = ref('')
const creating = ref(false)
const locallyDisabled = ref(false)
const locallyLocked = ref(false)
const isDisabled = computed(() => state.value.disabled || locallyDisabled.value)
const isLocked = computed(() => state.value.locked || state.value.readonly || locallyLocked.value)
const blocked = computed(() => isDisabled.value || isLocked.value)
const draggedValue = ref<string>()
const createdOptions = ref<VueSelectOption[]>([])
const suppliedOptions = ref<VueSelectOption[]>()
const suppliedGroups = ref<VueSelectGroup[]>()
let request: AbortController|undefined

const options = computed(() => {
  const result = new Map(createdOptions.value.map((option) => [option.value, option]))
  for (const option of suppliedOptions.value ?? state.value.options) {
    result.set(option.value, option)
  }
  return [...result.values()]
})
const groups = computed(() => suppliedGroups.value ?? state.value.groups)
const searchable = computed(() => !state.value.disableSearch
  && (state.value.multiple || options.value.length > state.value.disableSearchThreshold))
const placeholder = computed(() => !state.value.multiple || state.value.multiplePlaceholder ? state.value.placeholder : '')
const opened = ref(false)
const autoDropup = ref(false)
const dropup = computed(() => state.value.dropup === 'auto' ? autoDropup.value : state.value.dropup)
const normalize = (value: VueSelectValue) => {
  let result = [...new Set(value === null ? [] : Array.isArray(value) ? value : [value])]
  if (!state.value.retainMissingValues) {
    result = result.filter((value) => options.value.some((option) => option.value === value))
  }
  if (state.value.nativeOrder && !state.value.sortable) {
    const values = new Set(result)
    const available = new Set(options.value.map((option) => option.value))
    result = [
      ...options.value.filter((option) => values.has(option.value)).map((option) => option.value),
      ...result.filter((value) => !available.has(value)),
    ]
  }
  return state.value.multiple ? result : result.slice(0, 1)
}
const selectedValues = ref(normalize(state.value.modelValue))
const empty = computed(() => selectedValues.value.length === 0
  || (state.value.blankPlaceholder && !state.value.multiple && !selectedValues.value[0]))
const formValues = computed(() => state.value.multiple ? selectedValues.value : [selectedValues.value[0] ?? ''])

const optionChoice = (option: VueSelectOption, group = ''): Choice => ({
  key: JSON.stringify(['option', group, option.value]),
  kind: 'option',
  value: option.value,
  label: option.label,
  disabled: !!option.disabled,
  title: option.title,
})

const searchableText = (text: string) => text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase()

const choices = computed(() => {
  const words = searchable.value ? searchableText(search.value).trim().split(/\s+/).filter(Boolean) : []
  const matching = options.value.filter((option) =>
    (!state.value.hideSelected || !state.value.multiple || !selectedValues.value.includes(option.value))
    && (!state.value.blankPlaceholder || state.value.multiple || option.value !== '' || option.label !== '')
    && words.every((word) => searchableText(`${option.label} ${state.value.searchValues ? option.value : ''}`).includes(word)))
  const result: Choice[] = []
  const knownGroups = new Set(groups.value.map((group) => group.value))
  for (const option of matching) {
    if (!option.groups?.some((group) => knownGroups.has(group))) {
      result.push(optionChoice(option))
    }
  }
  for (const group of groups.value) {
    const members = matching.filter((option) => option.groups?.includes(group.value))
    if (members.length === 0) {
      continue
    }
    result.push({ key: JSON.stringify(['group', group.value]), kind: 'group', value: group.value, label: group.label, disabled: true })
    for (const option of members) {
      result.push({ ...optionChoice(option, group.value), disabled: !!group.disabled || !!option.disabled })
    }
  }
  const input = search.value.trim()
  if (searchable.value && state.value.create && input && !options.value.some((option) => option.label === input || option.value === input)) {
    result.unshift({ key: JSON.stringify(['create', input]), kind: 'create', value: input, label: input, disabled: false })
  }
  return result
})

const selectedChoices = computed(() => {
  const result = selectedValues.value.filter((value) => !state.value.blankPlaceholder || state.value.multiple
    || value !== '' || options.value.find((option) => option.value === value)?.label !== '').map((value) => optionChoice(
    options.value.find((option) => option.value === value) ?? { value, label: value },
  ))
  return state.value.multiple ? result : result[0] ?? null
})

const getChoiceKey = (choice: Choice) => choice.key
const getChoiceLabel = (choice: Choice) => choice.label
const isSelectable = (choice: Choice) =>
  !blocked.value && !creating.value && choice.kind !== 'group' && !choice.disabled && !selectedValues.value.includes(choice.value)

const getValue = (): VueSelectValue => state.value.multiple ? [...selectedValues.value] : selectedValues.value[0] ?? null

const cancelCreation = () => {
  request?.abort()
  request = undefined
  creating.value = false
}

const updateValue = (value: VueSelectValue, silent = false) => {
  const next = normalize(value)
  if (next.length === selectedValues.value.length && next.every((value, index) => selectedValues.value[index] === value)) {
    return
  }
  selectedValues.value = next
  if (!state.value.persist) {
    createdOptions.value = createdOptions.value.filter((option) => next.includes(option.value))
  }
  if (!silent) {
    emit('update:modelValue', getValue())
    emit('change', getValue())
  }
}

const setValue = (value: VueSelectValue, silent = false) => {
  cancelCreation()
  errorMessage.value = ''
  updateValue(value, silent)
}

const removeItem = (value: string) => {
  if (!blocked.value && !creating.value && !(state.value.notEmpty && selectedValues.value.length === 1)) {
    setValue(selectedValues.value.filter((item) => item !== value))
  }
}

const clear = (silent = false) => setValue(null, silent)

const moveItem = (index: number, offset: number) => {
  const target = index + offset
  if (!state.value.multiple || !state.value.sortable || blocked.value || creating.value
    || !Number.isInteger(index) || !Number.isInteger(offset)
    || index < 0 || target < 0 || target >= selectedValues.value.length) {
    return
  }
  const next = [...selectedValues.value]
  next.splice(target, 0, ...next.splice(index, 1))
  setValue(next)
}

const startDrag = (event: DragEvent, value: string) => {
  if (blocked.value || creating.value || !state.value.multiple || !state.value.sortable) {
    event.preventDefault()
    return
  }
  draggedValue.value = value
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', value)
  }
}

const dropItem = (value: string) => {
  if (draggedValue.value !== undefined) {
    const index = selectedValues.value.indexOf(draggedValue.value)
    moveItem(index, selectedValues.value.indexOf(value) - index)
    draggedValue.value = undefined
  }
}

const readCreatedOption = (data: unknown, configuration: VueSelectCreateOptions): VueSelectOption => {
  const value = typeof data === 'object' && data !== null ? Reflect.get(data, configuration.valueField ?? 'value') : undefined
  const label = typeof data === 'object' && data !== null ? Reflect.get(data, configuration.labelField ?? 'text') : undefined
  if (typeof value !== 'string' || !value.trim() || typeof label !== 'string' || !label.trim()) {
    throw new Error(t(appName, 'The validation response does not contain a valid option.'))
  }
  return { value, label }
}

const optionEnabled = (option: VueSelectOption) => !option.disabled && (!option.groups?.length
  || option.groups.some((value) => !groups.value.find((group) => group.value === value)?.disabled))

const setOptions = (options: VueSelectOption[], nextGroups: VueSelectGroup[] = groups.value) => {
  suppliedOptions.value = options
  suppliedGroups.value = nextGroups
}

const createItem = async (input: string): Promise<boolean> => {
  input = input.trim()
  if (!input || !state.value.create || blocked.value || creating.value) {
    return false
  }
  const existing = options.value.find((option) => option.value === input || option.label === input)
  if (existing) {
    const allowed = optionEnabled(existing)
    if (allowed) {
      setValue(state.value.multiple ? [...selectedValues.value, existing.value] : existing.value)
    }
    return allowed
  }
  const configuration = typeof state.value.create === 'object' ? state.value.create : {}
  const controller = new AbortController()
  request = controller
  creating.value = true
  errorMessage.value = ''
  try {
    let option: VueSelectOption = { value: input, label: input }
    if (configuration.resolve) {
      option = await configuration.resolve(input, controller.signal)
      if (!option.value?.trim() || !option.label?.trim()) {
        throw new Error(t(appName, 'The validation response does not contain a valid option.'))
      }
    } else if (configuration.url) {
      option = await createRemoteSelectOption(
        { ...configuration, url: configuration.url },
        (data) => readCreatedOption(data, configuration),
      )(input, controller.signal)
    }
    if (controller.signal.aborted || blocked.value) {
      return false
    }
    const existing = options.value.find((existing) => existing.value === option.value)
    if (existing && !optionEnabled(existing)) {
      throw new Error(t(appName, 'This option is disabled.'))
    }
    if (!existing) {
      createdOptions.value.push(option)
      emit('create', option)
    }
    updateValue(state.value.multiple ? [...selectedValues.value, option.value] : option.value)
    return true
  } catch (error) {
    if (!controller.signal.aborted) {
      const failure = error instanceof Error ? error : new Error(String(error))
      const data = isAxiosErrorResponse(error) ? error.response.data : undefined
      const messages = typeof data === 'object' && data !== null && 'messages' in data ? data.messages : undefined
      const message = Array.isArray(messages) && messages.every((entry) => typeof entry === 'string') && messages.length > 0
        ? messages.join(' ')
        : failure.message
      errorMessage.value = t(appName, 'Could not add "{value}": {message}', { value: input, message })
      logger.error(errorMessage.value, error)
      emit('error', failure)
    }
    return false
  } finally {
    if (request === controller) {
      request = undefined
      creating.value = false
    }
  }
}

const selectChoices = (value: Choice|Choice[]|null) => {
  if (blocked.value || creating.value) {
    return
  }
  const next = value === null ? [] : Array.isArray(value) ? value : [value]
  if (next.some((choice) => !isSelectable(choice) && !selectedValues.value.includes(choice.value))) {
    return
  }
  const candidate = next.find((choice) => choice.kind === 'create')
  if (candidate) {
    void createItem(candidate.value)
  } else if (next.length > 0 || (!state.value.notEmpty && (state.value.multiple || state.value.allowSingleDeselect))) {
    setValue(next.filter((choice) => choice.kind === 'option').map((choice) => choice.value))
  }
}

type SearchEvents = {
  input: (event: { target: { value: string } }) => void
  keydown: (event: KeyboardEvent) => void
}

const commitSearch = async (events: SearchEvents) => {
  if (await createItem(search.value)) {
    events.input({ target: { value: '' } })
  }
}

const onBlur = (events: SearchEvents) => {
  if (state.value.createOnBlur) {
    void commitSearch(events)
  }
}

const onKeydown = (event: KeyboardEvent, events: SearchEvents) => {
  if (event.isComposing || event.ctrlKey || event.metaKey) {
    events.keydown(event)
    return
  }
  if (event.key === 'Backspace' && !search.value && !state.value.removeOnBackspace) {
    return
  }
  if (event.key === ',' && state.value.create) {
    event.preventDefault()
    void commitSearch(events)
  } else if (event.key === 'Backspace' && !search.value && selectedValues.value.length > 0
    && (state.value.multiple || state.value.restoreOnBackspace)) {
    event.preventDefault()
    const value = selectedValues.value.at(-1)!
    if (state.value.restoreOnBackspace) {
      if (!blocked.value && !creating.value && !(state.value.notEmpty && selectedValues.value.length === 1)) {
        const label = options.value.find((option) => option.value === value)?.label ?? value
        setValue(selectedValues.value.slice(0, -1))
        events.input({ target: { value: label } })
      }
    } else {
      removeItem(value)
    }
  }
  if (!event.defaultPrevented) {
    events.keydown(event)
  }
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
const onOpen = () => {
  opened.value = true
  emit('open')
}
const onClose = () => {
  opened.value = false
  autoDropup.value = false
  emit('close')
}
watch([opened, choices, () => state.value.dropup], async (_value, _previous, onCleanup) => {
  if (!opened.value || state.value.dropup !== 'auto') {
    return
  }
  let cancelled = false
  const updateDirection = () => {
    const toggle = root.value?.querySelector<HTMLElement>('.vs__dropdown-toggle')
    const menu = root.value?.querySelector<HTMLElement>('.vs__dropdown-menu')
    if (!toggle || !menu) {
      return
    }
    let top = window.visualViewport?.offsetTop ?? 0
    let bottom = top + (window.visualViewport?.height ?? window.innerHeight)
    for (let ancestor = root.value?.parentElement; ancestor; ancestor = ancestor.parentElement) {
      if (/(auto|scroll|hidden|clip)/.test(getComputedStyle(ancestor).overflowY)) {
        const bounds = ancestor.getBoundingClientRect()
        top = Math.max(top, bounds.top + ancestor.clientTop)
        bottom = Math.min(bottom, bounds.top + ancestor.clientTop + ancestor.clientHeight)
      }
    }
    const bounds = toggle.getBoundingClientRect()
    const above = Math.max(0, bounds.top - top)
    const below = Math.max(0, bottom - bounds.bottom)
    autoDropup.value = menu.getBoundingClientRect().height > below && above > below
  }
  const observer = new ResizeObserver(updateDirection)
  onCleanup(() => {
    cancelled = true
    observer.disconnect()
    window.removeEventListener('resize', updateDirection)
    window.removeEventListener('scroll', updateDirection, true)
    window.visualViewport?.removeEventListener('resize', updateDirection)
    window.visualViewport?.removeEventListener('scroll', updateDirection)
  })
  // Legacy open handlers may change ancestor overflow before measurement.
  await nextTick()
  if (cancelled) {
    return
  }
  updateDirection()
  if (root.value) {
    observer.observe(root.value)
    const menu = root.value.querySelector('.vs__dropdown-menu')
    if (menu) {
      observer.observe(menu)
    }
    for (let ancestor = root.value.parentElement; ancestor; ancestor = ancestor.parentElement) {
      if (/(auto|scroll|hidden|clip)/.test(getComputedStyle(ancestor).overflowY)) {
        observer.observe(ancestor)
      }
    }
  }
  window.addEventListener('resize', updateDirection)
  window.addEventListener('scroll', updateDirection, true)
  window.visualViewport?.addEventListener('resize', updateDirection)
  window.visualViewport?.addEventListener('scroll', updateDirection)
}, { flush: 'post' })

const setState = (value: VueSelectProps) => {
  cancelCreation()
  suppliedOptions.value = undefined
  suppliedGroups.value = undefined
  suppliedRequired.value = undefined
  suppliedState.value = { ...props, ...value, modelValue: value.modelValue ?? null }
  setValue(value.modelValue ?? null, true)
}
watch([() => state.value.modelValue, () => state.value.multiple], () => {
  // Echoes of our own v-model update must not cancel a successful creation.
  const next = normalize(state.value.modelValue ?? null)
  if (JSON.stringify(next) !== JSON.stringify(selectedValues.value)) {
    setValue(state.value.modelValue ?? null, true)
  }
}, { deep: true })
watch([options, () => state.value.retainMissingValues, () => state.value.nativeOrder, () => state.value.sortable], () => {
  if (!state.value.retainMissingValues || state.value.nativeOrder) {
    updateValue(getValue(), true)
  }
}, { deep: true })

watch([() => state.value.create, blocked], () => {
  cancelCreation()
}, { deep: true })

watch(search, () => {
  errorMessage.value = ''
})
watchEffect(() => {
  const validityMessage = creating.value
    ? t(appName, 'Please wait for validation to finish.')
    : errorMessage.value || (required.value && empty.value ? t(appName, 'Please select an item.') : '')
  searchInput.value?.setCustomValidity(validityMessage)
})
onBeforeUnmount(cancelCreation)

defineExpose({
  setState,
  setRequired: (value: boolean) => { suppliedRequired.value = value },
  getValue,
  getOptions: () => options.value,
  setValue,
  setOptions,
  clear,
  moveItem,
  createItem,
  focus,
  open,
  close,
  lock: () => { locallyLocked.value = true },
  unlock: () => { locallyLocked.value = false },
  disable: () => { locallyDisabled.value = true },
  enable: () => { locallyDisabled.value = false },
  isLocked,
  isDisabled,
  creating,
})
</script>

<style scoped lang="scss">
@use '../../style/variables.scss' as *;

:global(.#{$appName}-vue-select-control) {
  display: inline-block;
  vertical-align: middle;
}

:global(.#{$appName}-vue-select-control > .#{$appName}-vue-select) {
  width: 100%;
  min-width: 0;
}

.#{$appName}-vue-select {
  min-width: 4em;
  width: 100%;

  :deep(.nc-select.v-select.select) {
    width: 100%;
    min-width: 0;
    max-width: 100%;
    margin-inline: 0;
  }

  :deep(.nc-select.v-select.select.vs--multiple) {
    margin-block: 0;
  }

  :deep(.nc-select.v-select.select.vs--multiple),
  :deep(.nc-select.v-select.select.vs--multiple .vs__dropdown-toggle),
  :deep(.nc-select.v-select.select.vs--multiple .vs__selected-options) {
    height: auto;
    max-height: none;
  }

  :deep(.nc-select.v-select.select .vs__dropdown-toggle) {
    padding-block-start: 0;
  }

  :deep(.nc-select.v-select.select.vs--multiple .vs__dropdown-toggle) {
    // Multi-select uses per-item remove buttons, not the global clear button.
    padding-inline-end: var(--default-clickable-area) !important;
  }

  :deep(.nc-select.v-select.select.vs--single .vs__dropdown-toggle) {
    border: var(--border-width-input-focused, 2px) solid transparent !important;
    box-shadow: var(--input-border-box-shadow) !important;
  }

  :deep(.nc-select.v-select.select.vs--single:not(.vs--disabled) .vs__dropdown-toggle) {
    background: var(--color-main-background);
  }

  :deep(.nc-select.v-select.select.vs--single:not(.vs--disabled) .vs__dropdown-toggle:hover) {
    box-shadow: 0 0 0 1px var(--vs-border-color) !important;
  }

  :deep(.nc-select.v-select.select.vs--single.vs--open .vs__dropdown-toggle) {
    box-shadow: none !important;
    border: var(--border-width-input-focused, 2px) solid var(--color-main-text) !important;
    border-block-end-color: var(--color-border-maxcontrast) !important;
    border-end-start-radius: 0;
    border-end-end-radius: 0;
    outline: 2px solid var(--color-main-background);
  }

  :deep(.nc-select.v-select.select .vs__selected-options) {
    min-height: calc(var(--default-clickable-area) - 2 * var(--vs-border-width));
  }

  :deep(.nc-select.v-select.select.vs--single .vs__search) {
    padding-inline: var(--border-radius-element) calc(var(--default-clickable-area) + var(--default-grid-baseline)) !important;
    background: transparent;
    box-shadow: none;
  }

  :deep(.nc-select.v-select.select.vs--single:has(.vs__clear) .vs__search) {
    padding-inline-end: calc(2 * var(--default-clickable-area)) !important;
  }

  :deep(.nc-select.v-select.select.vs--single .#{$appName}-vue-select__selected) {
    inset-block-start: 0;
    padding-inline-start: var(--border-radius-element);
  }

  :deep(.nc-select.v-select.select.vs--multiple .#{$appName}-vue-select__selected.vs__selected) {
    border-radius: var(--border-radius-large) !important;
  }

  :deep(.vs__dropdown-option) {
    box-sizing: border-box;
    min-height: var(--default-clickable-area);

    &:has(.#{$appName}-vue-select__option--selected) {
      background-color: var(--color-primary-element-light);
      color: var(--color-main-text);
    }

    &:hover,
    &.vs__dropdown-option--highlight {
      background-color: var(--color-background-hover);
      color: var(--color-main-text);
    }

    &:has(.#{$appName}-vue-select__group) {
      background-color: var(--color-main-background);
      color: var(--color-main-text);
      cursor: default;
    }
  }

  &__label {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
  }

  &__selected {
    display: inline-flex;
    align-items: center;
    gap: 4px;

    &--move-on-focus {
      .#{$appName}-vue-select__move {
        opacity: 0;
        pointer-events: none;
      }

      &:has(:focus-visible) .#{$appName}-vue-select__move {
        opacity: 1;
        pointer-events: auto;
      }
    }

    &[draggable="true"] {
      cursor: grab;
    }

    button {
      min-width: 24px;
      min-height: 24px;
      margin: 0;
      padding: 0 4px;
    }
  }

  &__error {
    color: var(--color-error);
  }

  &__group {
    color: var(--color-main-text);
  }

  :deep(.vs__search),
  :deep(.vs__search:hover),
  :deep(.vs__search:active),
  :deep(.vs__search:focus) {
    background: none !important;
    opacity: 1;
    filter: none;
  }

  &--single-no-clear :deep(.nc-select.v-select.select.vs--single .vs__selected) {
    padding-inline-end: var(--default-clickable-area);
  }

  &--single-no-clear {
    display: flex;
    align-items: stretch;
  }

  &--dropup :deep(.vs__dropdown-menu) {
    top: auto;
    bottom: 100%;
    border-radius: var(--vs-border-radius) var(--vs-border-radius) 0 0;
    border-top-style: var(--vs-border-style);
    border-bottom-style: none;
  }

  &--dropup :deep(.nc-select.v-select.select.vs--open .vs__dropdown-toggle) {
    border-start-start-radius: 0;
    border-start-end-radius: 0;
    border-end-start-radius: var(--vs-border-radius);
    border-end-end-radius: var(--vs-border-radius);
    border-block-start-color: var(--vs-border-color);
    border-block-end-color: var(--color-main-text);
  }

  &--dropup :deep(.v-select .vs__open-indicator) {
    transform: rotate(180deg) scale(var(--vs-controls-size));
  }

  &--dropup :deep(.v-select.vs--open .vs__open-indicator) {
    transform: scale(var(--vs-controls-size));
  }
}
</style>
