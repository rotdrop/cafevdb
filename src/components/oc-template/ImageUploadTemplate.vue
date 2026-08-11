<!--
 - @copyright Copyright (c) 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
 -
 - @author Claus-Justus Heine <himself@claus-justus-heine.de>
 -
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
 - GNU Affero General Public License for more detail.s
 -
 - You should have received a copy of the GNU Affero General Public License
 - along with this program. If not, see <http://www.gnu.org/licenses/>.
 -
 -->
<template>
  <component :is="'script' /* intentionally literal */"
             id="imageUploadTemplate"
             ref="outer"
             type="text/template"
  >
    <form id="{formId}"
          ref="inner"
          class="float hidden"
          enctype="multipart/form-data"
    >
      <input type="hidden" name="ownerId" value="{ownerId}">
      <input type="hidden" name="imageId" value="{imageId}">
      <input type="hidden" name="joinTable" value="{joinTable}">
      <input type="hidden" name="requesttoken" value="{requestToken}">
      <input type="hidden" name="imageSize" value="{imageSize}">
      <input type="hidden"
             class="max_upload"
             name="MAX_FILE_SIZE"
             :value="uploadMaxFileSize"
      >
      <input type="hidden"
             class="max_human_file_size max_upload_human"
             :value="uploadMaxHumanFileSize"
      >
      <input class="file_upload_start"
             type="file"
             accept="image/*"
             :name="uploadName"
      >
    </form>
  </component>
</template>

<script lang="ts" setup>
import { onMounted, ref } from 'vue'
defineProps({
  uploadMaxFileSize: {
    type: Number,
    required: true,
  },
  uploadMaxHumanFileSize: {
    type: String,
    required: true,
  },
  uploadName: {
    type: String,
    default: 'imagefile',
  },
})
const inner = ref<null|HTMLElement>(null)
const outer = ref<null|HTMLScriptElement>(null)
onMounted(() => {
  outer.value!.innerHTML = inner.value!.outerHTML
})
</script>
