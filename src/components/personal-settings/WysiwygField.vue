<!--
 - Orchestra member, musician and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2011-2016, 2020-2026 Claus-Justus Heine <himself@rotdrop.de>
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
  <div class="wysiwyg-field">
    <label v-if="label" class="wysiwyg-label" @click="editor?.editing.view.focus()">{{ label }}</label>
    <div ref="host" class="wysiwyg-host" />
  </div>
</template>

<script setup lang="ts">
import type { ClassicEditor } from 'ckeditor5'

import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps<{
  modelValue: string
  label?: string
  disabled?: boolean
}>()
const emit = defineEmits<{
  'update:modelValue': [value: string]
  blur: [value: string]
  error: [error: Error]
}>()

const host = ref<HTMLElement|null>(null)
const editor = ref<ClassicEditor|null>(null)
let destroyed = false

onMounted(async () => {
  try {
    const ck = await import('ckeditor5')
    await import('ckeditor5/ckeditor5.css')
    if (destroyed || !host.value) {
      return
    }
    const instance = await ck.ClassicEditor.create(host.value, {
      licenseKey: 'GPL',
      plugins: [
        ck.Essentials,
        ck.Paragraph,
        ck.Autoformat,
        ck.Bold,
        ck.Italic,
        ck.Heading,
        ck.Link,
        ck.List,
        ck.BlockQuote,
        ck.Indent,
        ck.PasteFromOffice,
        ck.TextTransformation,
      ],
      toolbar: [
        'undo',
        'redo',
        '|',
        'heading',
        '|',
        'bold',
        'italic',
        '|',
        'link',
        'blockQuote',
        '|',
        'bulletedList',
        'numberedList',
        'outdent',
        'indent',
      ],
      initialData: props.modelValue,
    })
    if (destroyed) {
      await instance.destroy()
      return
    }
    editor.value = instance
    instance.enableReadOnlyMode('disabled-prop')
    if (!props.disabled) {
      instance.disableReadOnlyMode('disabled-prop')
    }
    instance.model.document.on('change:data', () => emit('update:modelValue', instance.getData()))
    instance.ui.focusTracker.on('change:isFocused', (_event, _name, isFocused) => {
      if (!isFocused) {
        emit('blur', instance.getData())
      }
    })
  } catch (error) {
    emit('error', error instanceof Error ? error : new Error(String(error)))
  }
})

watch(() => props.modelValue, (value) => {
  if (editor.value && value !== editor.value.getData()) {
    editor.value.setData(value)
  }
})

watch(() => props.disabled, (disabled) => {
  if (disabled) {
    editor.value?.enableReadOnlyMode('disabled-prop')
  } else {
    editor.value?.disableReadOnlyMode('disabled-prop')
  }
})

onBeforeUnmount(() => {
  destroyed = true
  editor.value?.destroy()
})
</script>

<style lang="scss" scoped>
.wysiwyg-field {
  flex: 1 1 100%;

  .wysiwyg-label {
    display: block;
    margin-block-end: calc(var(--default-grid-baseline) * 1);
  }

  :deep(.ck-editor__editable) {
    min-height: 10em;
  }
}
</style>
