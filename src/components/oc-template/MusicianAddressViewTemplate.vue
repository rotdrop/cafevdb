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
             id="musicianAddressViewTemplate"
             ref="outer"
             type="text/template"
  >
    <div ref="inner" class="musician-address-view" data-id="{id}">
      <table ref="outer">
        <thead>
          <tr class="personalPublicName musician-address-header">
            <th class="tag">
              <span class="tag-label">{{ t(appName, 'Name') }}:</span>
            </th>
            <th class="data">
              <span class="value">{personalPublicName}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr class="email">
            <td class="tag">
              <span class="tag-label">{{ t(appName, 'Email') }}:</span>
            </td>
            <td class="data">
              <span class="value">{email}</span>
            </td>
          </tr>
          <tr class="phone">
            <td class="tag">
              <span class="tag-label">{{ t(appName, 'Phone') }}:</span>
            </td>
            <td class="data">
              <span class="flex-container flex-justify-start">
                <span class="value">{mobilePhone}</span>
                <span class="separator">, </span>
                <span class="value">{fixedLinePhone}</span>
              </span>
            </td>
          </tr>
          <tr class="address">
            <td class="tag">
              <span class="tag-label">{{ t(appName, 'Address') }}:</span>
            </td>
            <td class="data">
              <span class="flex-container flex-justify-start flex-baseline">
                <span class="value">{addressSupplement}</span>
                <span class="separator">, </span>
                <span class="value">{streetAndNumber}</span>
                <span class="separator">, </span>
                <span class="value">{postalCode} {city}</span>
                <span class="separator">, </span>
                <span class="value">{country}</span>
              </span>
            </td>
          </tr>
          <tr>
            <td class="tag">
              <span class="tag-label">{{ t(appName, 'Probability') }}:</span>
            </td>
            <td class="data">
              <span class="value">{duplicatesProbability} ({reasons})</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </component>
</template>

<script lang="ts" setup>
import { translate as t } from '@nextcloud/l10n'
import { onMounted, ref } from 'vue'
import { appName } from '../../config.ts'

const inner = ref<null|HTMLElement>(null)
const outer = ref<null|HTMLScriptElement>(null)
onMounted(() => {
  outer.value!.innerHTML = inner.value!.outerHTML
})
</script>
