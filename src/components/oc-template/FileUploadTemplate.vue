<!--
 - @copyright Copyright (c) 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  <div v-show="false" id="fileUploadTemplate" data-type="text/template">
    <div id="{wrapperId}" class="file-upload-wrapper">
      <form class="float hidden {formClass}" enctype="multipart/form-data">
        <input class="file-upload-start"
               type="file"
               accept="{accept}"
               name="{uploadName}"
        >
        <input type="hidden" name="uploadName" value="{uploadName}">
        <!-- eslint-disable-next-line vue/html-quotes -->
        <input type="hidden" name="data" value='{uploadData}'>
        <input type="hidden" name="requesttoken" value="{requestToken}">
        <input type="hidden" name="MAX_FILE_SIZE" :value="uploadMaxFileSize">
        <input type="hidden" class="max_human_file_size" :value="uploadMaxHumanFileSize">
        <input type="hidden" name="projectId" value="{projectId}">
        <input type="hidden" name="musicianId" value="{musicianId}">
      </form>
      <div class="uploadprogresswrapper">
        <div class="uploadprogressbar" />
      </div>
    </div>
  </div>
</template>
<script lang="ts" setup>
defineProps({
  uploadMaxFileSize: {
    type: Number,
    required: true,
  },
  uploadMaxHumanFileSize: {
    type: String,
    required: true,
  },
})
</script>
