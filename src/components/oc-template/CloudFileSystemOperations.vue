<template>
  <component :is="'script'" id="imageUploadTemplate" type="text/template">
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
  </component>
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
