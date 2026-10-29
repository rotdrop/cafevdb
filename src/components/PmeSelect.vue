<!--
 - SPDX-FileCopyrightText: 2026 Claus-Justus Heine
 - SPDX-License-Identifier: AGPL-3.0-or-later
 -->
<template>
  <div class="pme-select" :class="rootClass" :aria-busy="creating">
    <label class="pme-select__label" :for="selectId">{{ label }}</label>
    <NcSelect :modelValue="selectedChoices"
              :options="choices"
              :multiple="multiple"
              :inputId="selectId"
              :inputLabel="label"
              :labelOutside="true"
              :placeholder="placeholder"
              :disabled="blocked"
              :loading="creating"
              :clearable="!notEmpty && !multiple"
              :clearSearchOnBlur="() => false"
              :deselectFromDropdown="false"
              :keepOpen="multiple"
              :filterable="false"
              :selectable="isSelectable"
              :getOptionKey="getChoiceKey"
              :getOptionLabel="getChoiceLabel"
              :appendToBody="false"
              @update:modelValue="selectChoices"
              @search="search = $event"
              @open="emit('open')"
              @close="emit('close')"
    >
      <template #search="{ attributes, events }">
        <input v-bind="{ ...attributes, ref: undefined }"
               ref="searchInput"
               class="vs__search selectize-input-element"
               :placeholder="selectedValues.length === 0 ? placeholder : ''"
               :required="required && selectedValues.length === 0"
               :aria-invalid="errorMessage ? true : undefined"
               :aria-describedby="errorMessage ? `${selectId}-error` : undefined"
               autocomplete="off"
               v-on="events"
               @beforeinput="creating && $event.preventDefault()"
               @blur="onBlur(events)"
               @keydown="onKeydown($event, events)"
        >
      </template>
      <template #option="option">
        <strong v-if="option.kind === 'group'" class="pme-select__group">{{ option.label }}</strong>
        <span v-else-if="option.kind === 'create'">{{ t(appName, 'Add "{value}"', { value: option.value }) }}</span>
        <span v-else>{{ option.label }}</span>
      </template>
      <template #selected-option-container="{ option }">
        <span :key="option.value"
              class="pme-select__selected"
              :class="{ vs__selected: multiple }"
              :data-value="option.value"
              :draggable="sortable && multiple && !blocked && !creating"
              @mousedown.stop
              @dragstart="startDrag($event, option.value)"
              @dragover.prevent
              @drop.prevent.stop="dropItem(option.value)"
              @dragend="draggedValue = undefined"
        >
          <span>{{ option.label }}</span>
          <template v-if="sortable && multiple">
            <button type="button"
                    :disabled="blocked || creating || selectedValues[0] === option.value"
                    :aria-label="t(appName, 'Move {label} earlier', { label: option.label })"
                    @click.stop="moveItem(selectedValues.indexOf(option.value), -1)"
            >
              <span aria-hidden="true">&lt;</span>
            </button>
            <button type="button"
                    :disabled="blocked || creating || selectedValues.at(-1) === option.value"
                    :aria-label="t(appName, 'Move {label} later', { label: option.label })"
                    @click.stop="moveItem(selectedValues.indexOf(option.value), 1)"
            >
              <span aria-hidden="true">&gt;</span>
            </button>
          </template>
          <button v-if="multiple"
                  type="button"
                  :disabled="blocked || creating || (notEmpty && selectedValues.length === 1)"
                  :aria-label="t(appName, 'Remove {label}', { label: option.label })"
                  @click.stop="removeItem(option.value)"
          >
            <span aria-hidden="true">&times;</span>
          </button>
        </span>
      </template>
    </NcSelect>
    <p v-if="errorMessage"
       :id="`${selectId}-error`"
       class="pme-select__error"
       role="alert"
    >
      {{ errorMessage }}
    </p>
    <template v-if="name">
      <input v-for="value in formValues"
             :key="value"
             type="hidden"
             :name="name"
             :value="value"
             :disabled="isDisabled"
      >
    </template>
  </div>
</template>

<script setup lang="ts">
import type {
  PmeSelectCreateOptions,
  PmeSelectGroup,
  PmeSelectOption,
  PmeSelectProps,
  PmeSelectValue,
} from '../types/pme-select.ts'

import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import {
  computed,
  onBeforeUnmount,
  ref,
  useId,
  useTemplateRef,
  watch,
  watchEffect,
} from 'vue'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { appName } from '../config.ts'
import { isAxiosErrorResponse } from '../toolkit/types/axios-type-guards.ts'
import generateAppUrl from '../toolkit/util/generate-url.ts'
import Console from '../util/console.ts'

type Choice = {
  key: string
  kind: 'option'|'group'|'create'
  value: string
  label: string
  disabled: boolean
}

const props = withDefaults(defineProps<PmeSelectProps>(), {
  modelValue: null,
  groups: () => [],
  multiple: false,
  disabled: false,
  readonly: false,
  locked: false,
  required: false,
  notEmpty: false,
  sortable: false,
  placeholder: '',
  inputId: undefined,
  name: undefined,
  create: false,
  createOnBlur: false,
  persist: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: PmeSelectValue]
  change: [value: PmeSelectValue]
  error: [error: Error]
  open: []
  close: []
}>()

const logger = new Console('PmeSelect')
const uid = useId()
const selectId = computed(() => props.inputId || `pme-select-${uid}`)
const searchInput = useTemplateRef('searchInput')
const search = ref('')
const errorMessage = ref('')
const creating = ref(false)
const locallyDisabled = ref(false)
const locallyLocked = ref(false)
const isDisabled = computed(() => props.disabled || locallyDisabled.value)
const isLocked = computed(() => props.locked || props.readonly || locallyLocked.value)
const blocked = computed(() => isDisabled.value || isLocked.value)
const draggedValue = ref<string>()
const createdOptions = ref<PmeSelectOption[]>([])
const suppliedOptions = ref<PmeSelectOption[]>()
const suppliedGroups = ref<PmeSelectGroup[]>()
let request: AbortController|undefined

const normalize = (value: PmeSelectValue) => {
  const result = [...new Set(value === null ? [] : Array.isArray(value) ? value : [value])]
  return props.multiple ? result : result.slice(0, 1)
}
const selectedValues = ref(normalize(props.modelValue))
const formValues = computed(() => props.multiple ? selectedValues.value : [selectedValues.value[0] ?? ''])
const options = computed(() => {
  const result = new Map(createdOptions.value.map((option) => [option.value, option]))
  for (const option of suppliedOptions.value ?? props.options) {
    result.set(option.value, option)
  }
  return [...result.values()]
})
const groups = computed(() => suppliedGroups.value ?? props.groups)

const optionChoice = (option: PmeSelectOption, group = ''): Choice => ({
  key: JSON.stringify(['option', group, option.value]),
  kind: 'option',
  value: option.value,
  label: option.label,
  disabled: !!option.disabled,
})

const searchableText = (text: string) => text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase()

const choices = computed(() => {
  const words = searchableText(search.value).trim().split(/\s+/).filter(Boolean)
  const matching = options.value.filter((option) => words.every((word) => searchableText(option.label).includes(word)))
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
  if (props.create && input && !options.value.some((option) => option.label === input || option.value === input)) {
    result.unshift({ key: JSON.stringify(['create', input]), kind: 'create', value: input, label: input, disabled: false })
  }
  return result
})

const selectedChoices = computed(() => {
  const result = selectedValues.value.map((value) => optionChoice(
    options.value.find((option) => option.value === value) ?? { value, label: value },
  ))
  return props.multiple ? result : result[0] ?? null
})

const getChoiceKey = (choice: Choice) => choice.key
const getChoiceLabel = (choice: Choice) => choice.label
const isSelectable = (choice: Choice) =>
  !blocked.value && !creating.value && !choice.disabled && !selectedValues.value.includes(choice.value)

const getValue = (): PmeSelectValue => props.multiple ? [...selectedValues.value] : selectedValues.value[0] ?? null

const cancelCreation = () => {
  request?.abort()
  request = undefined
  creating.value = false
}

const updateValue = (value: PmeSelectValue, silent = false) => {
  const next = normalize(value)
  if (next.length === selectedValues.value.length && next.every((value, index) => selectedValues.value[index] === value)) {
    return
  }
  selectedValues.value = next
  if (!props.persist) {
    createdOptions.value = createdOptions.value.filter((option) => next.includes(option.value))
  }
  if (!silent) {
    emit('update:modelValue', getValue())
    emit('change', getValue())
  }
}

const setValue = (value: PmeSelectValue, silent = false) => {
  cancelCreation()
  errorMessage.value = ''
  updateValue(value, silent)
}

const removeItem = (value: string) => {
  if (!blocked.value && !creating.value && !(props.notEmpty && selectedValues.value.length === 1)) {
    setValue(selectedValues.value.filter((item) => item !== value))
  }
}

const clear = (silent = false) => setValue(null, silent)

const moveItem = (index: number, offset: number) => {
  const target = index + offset
  if (!props.multiple || !props.sortable || blocked.value || creating.value
    || !Number.isInteger(index) || !Number.isInteger(offset)
    || index < 0 || target < 0 || target >= selectedValues.value.length) {
    return
  }
  const next = [...selectedValues.value]
  next.splice(target, 0, ...next.splice(index, 1))
  setValue(next)
}

const startDrag = (event: DragEvent, value: string) => {
  if (blocked.value || creating.value || !props.multiple || !props.sortable) {
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

const readCreatedOption = (data: unknown, configuration: PmeSelectCreateOptions): PmeSelectOption => {
  const value = typeof data === 'object' && data !== null ? Reflect.get(data, configuration.valueField ?? 'value') : undefined
  const label = typeof data === 'object' && data !== null ? Reflect.get(data, configuration.labelField ?? 'text') : undefined
  if (typeof value !== 'string' || !value.trim() || typeof label !== 'string' || !label.trim()) {
    throw new Error(t(appName, 'The validation response does not contain a valid option.'))
  }
  return { value, label }
}

const optionEnabled = (option: PmeSelectOption) => !option.disabled && (!option.groups?.length
  || option.groups.some((value) => !groups.value.find((group) => group.value === value)?.disabled))

const setOptions = (options: PmeSelectOption[], nextGroups: PmeSelectGroup[] = groups.value) => {
  suppliedOptions.value = options
  suppliedGroups.value = nextGroups
}

const createItem = async (input: string): Promise<boolean> => {
  input = input.trim()
  if (!input || !props.create || blocked.value || creating.value) {
    return false
  }
  const existing = options.value.find((option) => option.value === input || option.label === input)
  if (existing) {
    const allowed = optionEnabled(existing)
    if (allowed) {
      setValue(props.multiple ? [...selectedValues.value, existing.value] : existing.value)
    }
    return allowed
  }
  const configuration = typeof props.create === 'object' ? props.create : {}
  const controller = new AbortController()
  request = controller
  creating.value = true
  errorMessage.value = ''
  try {
    let option: PmeSelectOption = { value: input, label: input }
    if (configuration.url) {
      const post = new URLSearchParams()
      for (const [key, value] of Object.entries(configuration.post ?? {})) {
        post.set(key, String(value))
      }
      post.set(configuration.inputField ?? 'input', input)
      const { data } = await axios.post<unknown>(generateAppUrl(configuration.url), post, { signal: controller.signal })
      option = readCreatedOption(data, configuration)
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
    }
    updateValue(props.multiple ? [...selectedValues.value, option.value] : option.value)
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
  const candidate = next.find((choice) => choice.kind === 'create')
  if (candidate) {
    void createItem(candidate.value)
  } else if (next.length > 0 || !props.notEmpty) {
    setValue(next.filter((choice) => choice.kind === 'option').map((choice) => choice.value))
  }
}

type SearchEvents = { input: (event: { target: { value: string } }) => void }

const commitSearch = async (events: SearchEvents) => {
  if (await createItem(search.value)) {
    events.input({ target: { value: '' } })
  }
}

const onBlur = (events: SearchEvents) => {
  if (props.createOnBlur) {
    void commitSearch(events)
  }
}

const onKeydown = (event: KeyboardEvent, events: SearchEvents) => {
  if (event.isComposing || event.ctrlKey || event.metaKey) {
    return
  }
  if (event.key === ',' && props.create) {
    event.preventDefault()
    void commitSearch(events)
  } else if (event.key === 'Backspace' && props.multiple && !search.value && selectedValues.value.length > 0) {
    event.preventDefault()
    removeItem(selectedValues.value.at(-1)!)
  }
}

watch(() => [props.modelValue, props.multiple] as const, () => {
  // Echoes of our own v-model update must not cancel a successful creation.
  const next = normalize(props.modelValue)
  if (JSON.stringify(next) !== JSON.stringify(selectedValues.value)) {
    setValue(props.modelValue, true)
  }
}, { deep: true })

watch(() => [props.create, blocked.value], () => {
  cancelCreation()
}, { deep: true })

watch(search, () => {
  errorMessage.value = ''
})
watchEffect(() => {
  const validityMessage = creating.value
    ? t(appName, 'Please wait for validation to finish.')
    : errorMessage.value || (props.required && selectedValues.value.length === 0 ? t(appName, 'Please select an item.') : '')
  searchInput.value?.setCustomValidity(validityMessage)
})
onBeforeUnmount(cancelCreation)

defineExpose({
  getValue,
  getOptions: () => options.value,
  setValue,
  setOptions,
  clear,
  moveItem,
  createItem,
  focus: () => searchInput.value?.focus(),
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
.pme-select {
  min-width: 180px;

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
}
</style>
