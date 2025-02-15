<!--
 - Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
           :show="open"
           size="large"
           :has-previous="false"
           :has-next="false"
           name="dummy"
           @update:show="$emit('update:open', false)"
  >
    <template #default>
      <!-- eslint-disable-next-line vue/no-v-html  -->
      <div class="error-html-container" v-html="htmlString" />
    </template>
  </NcModal>
</template>
<script setup lang="ts">
import {
  NcModal,
} from '@nextcloud/vue'
// import Console from '../util/console.ts'

// const COMPONENT_NAME = 'HtmlErrorPage'
// const logger = new Console(COMPONENT_NAME)

defineProps <{
  open: boolean,
  htmlString: string,
}>()

</script>
<style lang="scss">
@import '../../style/flex.scss';
.error-html-container {
  // so the many nth mean that this is tied closely to the core
  // exception template ...
  padding: 12px;
  // heading
  // > h1 {
  // }
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
