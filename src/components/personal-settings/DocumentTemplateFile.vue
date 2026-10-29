<template>
  <div class="document-template-file">
    <NcButton v-tooltip="cloudTooltip"
              :aria-label="t(appId, 'Choose a template file from the cloud storage.')"
              :disabled="disabled"
              @click="emit('chooseCloud')"
    >
      <template #icon>
        <CloudIcon :size="20" />
      </template>
    </NcButton>
    <NcButton v-tooltip="uploadTooltip"
              :aria-label="t(appId, 'Upload a document template from your computer or mobile.')"
              :disabled="disabled"
              @click="fileInput?.click()"
    >
      <template #icon>
        <UploadIcon :size="20" />
      </template>
    </NcButton>
    <input ref="fileInput"
           type="file"
           hidden
           :disabled="disabled"
           @change="selectFile"
    >
    <div class="document-template-file-target"
         @dragover.prevent
         @drop.prevent="dropFile"
    >
      <a v-if="fileName && downloadLink"
         :href="downloadLink"
         :download="fileName"
         :title="label"
      >
        {{ fileName }}
      </a>
      <NcTextField v-else
                   :label="label"
                   :modelValue="fileName"
                   :placeholder="t(appId, 'drop or click')"
                   :disabled="disabled"
                   readonly
                   @click="fileInput?.click()"
                   @keydown.enter.prevent="fileInput?.click()"
                   @keydown.space.prevent="fileInput?.click()"
      />
      <progress v-if="progress !== undefined"
                :value="progress"
                max="100"
                :aria-label="t(appId, 'Upload progress')"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcTextField } from '@nextcloud/vue'
import { ref } from 'vue'
import CloudIcon from 'vue-material-design-icons/Cloud.vue'
import UploadIcon from 'vue-material-design-icons/Upload.vue'
import { appName as appId } from '../../config.ts'

const props = defineProps<{
  label: string
  fileName: string
  downloadLink: string
  cloudTooltip?: string
  uploadTooltip?: string
  disabled: boolean
  progress?: number
}>()
const emit = defineEmits<{
  chooseCloud: []
  upload: [file: File]
  error: [error: Error]
}>()
const fileInput = ref<HTMLInputElement>()
const uploadFiles = (files: FileList|null) => {
  if (props.disabled || !files?.length) {
    return
  }
  if (files.length !== 1) {
    emit('error', new Error(t(appId, 'Please select a single document template file.')))
    return
  }
  emit('upload', files[0])
}
const selectFile = () => {
  const input = fileInput.value
  if (input) {
    uploadFiles(input.files)
    input.value = ''
  }
}
const dropFile = (event: DragEvent) => uploadFiles(event.dataTransfer?.files ?? null)
</script>

<style lang="scss" scoped>
.document-template-file {
  display: flex;
  flex: 1 1 20rem;
  align-items: center;
  gap: calc(var(--default-grid-baseline, 4px) * 2);
  min-width: 0;
}

.document-template-file-target {
  flex: 1;
  min-width: 0;

  a {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--color-primary-element);
    text-decoration: underline;
  }

  progress {
    display: block;
    width: 100%;
  }
}
</style>
