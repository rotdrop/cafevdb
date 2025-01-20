<template>
  <component :is="'script'" id="imageUploadTemplate" type="text/template">
    <form id="{formId}" class="float hidden" enctype="multipart/form-data">
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
<script setup>
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
</script>
