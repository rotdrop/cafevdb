<!--
 - Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 -
 -->
<template>
  <NcModal v-if="htmlString"
           ref="modal"
           :closeOnClickOutside="false"
           :show="open"
           size="large"
           :hasPrevious="false"
           :hasNext="false"
           :name="t(appName, 'An Error Occurred')"
           container="#body-user"
           @update:show="emit('update:open', false)"
  >
    <template #actions>
      <NcActionButton :name="t(appName, 'report error')"
                      closeAfterClick
                      @click="handleReportError"
      >
        <template #icon>
          <IconReportError :size="20" />
        </template>
      </NcActionButton>
      <NcActionButton :name="closeDetailsLabel"
                      closeAfterClick
                      @click="emit('update:open', false)"
      >
        <template #icon>
          <IconClose :size="20" />
        </template>
      </NcActionButton>
    </template>
    <template #default>
      <div>
        <div class="error-html-container">
          <h5 v-if="!!caption" class="error-html-caption">
            {{ caption }}
          </h5>
          <!-- eslint-disable-next-line vue/no-v-html  -->
          <div v-html="htmlString" />
        </div>
      </div>
    </template>
  </NcModal>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import {
  NcActionButton,
  NcModal,
} from '@nextcloud/vue'
import { onMounted, ref, watch } from 'vue'
import IconClose from 'vue-material-design-icons/Close.vue'
import IconReportError from 'vue-material-design-icons/EmailArrowRightOutline.vue'
import { appName } from '../config.ts'

// import Console from '../util/console.ts'

// const COMPONENT_NAME = 'HtmlErrorPage'
// const logger = new Console(COMPONENT_NAME)

const props = defineProps<{
  open: boolean
  caption: string
  htmlString: string
  closeDetailsLabel: string
}>()

const emit = defineEmits([
  'update:open',
  'problemReport:show',
])

const handleReportError = () => {
  emit('update:open', false)
  emit('problemReport:show', true)
}

const modal = ref<Vue|null>(null)

const openMenu = () => {
  for (const child of (modal.value?.$children || [])) {
    if (child?.actionsMenuSemanticType === 'menu') {
      child.openMenu()
    }
  }
}

watch(() => props.open, (value) => {
  if (value) {
    openMenu()
  }
})

onMounted(() => {
  if (props.open) {
    openMenu()
  }
})

</script>

<style lang="scss">
@use '../../style/mixins/flex.scss';
@include flex.flexRules;
.error-html-container {
  padding: 24px; // do not overlap with the close button
  // so the many nth mean that this is tied closely to the core
  // exception template ...
  // heading
  // > h1 {
  // }
  h5.error-html-caption {
    margin: auto;
    color: inherit;
    text-overflow: ellipsis;
    white-space: normal;
    overflow: hidden;
  }
  .guest-box {
    ul:nth-of-type(2):not(:nth-of-type(3)) {
      // type, code, message, file, line
      li {
        display: inline;
        &:after {
          content: ", ";
        }
      }
      li:nth-of-type(3) {
        // message
        color:red;
        font-weight:bold;
      }
    }
    // trace heading
    // h3:nth-of-type(3) {
    // }
    // trace content
    pre {
      // display:none;
      // &.visible {
      //   display: block;
      // }
      color:blue;
      margin: 0 2em 0 2em;
      max-width:100%;
      overflow:auto;
    }
  }
}
</style>
