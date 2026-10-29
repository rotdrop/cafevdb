<!--
 - Orchestra member, musicion and project management application.
 -
 - CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 -
 - @author Claus-Justus Heine
 - @copyright 2011-2016, 2020-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  <div class="locale-information">
    <span class="locale heading">{{ t(appId, 'Locale Information:') }}</span>
    <span class="locale time">{{ time }}</span>
    <span class="locale timezone">{{ timeZone }}</span>
    <span class="locale thelocale">{{ localeSymbol }}</span>
    <span class="locale currency">
      <span class="locale currency-symbol">{{ currencySymbol }}</span>
      <span v-if="currencyCode !== currencySymbol" class="locale currency-code">{{ currencyCode }}</span>
    </span>
  </div>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import { computed, onBeforeUnmount, ref } from 'vue'
import { appName as appId } from '../../config.ts'
import getInitialState from '../../toolkit/util/initial-state.ts'

const props = defineProps<{
  localeSymbol?: string
  currencySymbol?: string
  currencyCode?: string
  timeZone?: string
}>()

const initialState = getInitialState<{
  localeSymbol?: string
  currencySymbol?: string
  currencyCode?: string
  dateTimeZone?: string
}>({ section: 'CAFEVDB', defaults: {} })

// Strip the encoding, e.g. "de_DE.UTF-8" -> "de_DE".
const localeSymbol = computed(() => (props.localeSymbol ?? initialState?.localeSymbol ?? '').split('.')[0])
const currencySymbol = computed(() => props.currencySymbol ?? initialState?.currencySymbol ?? '')
const currencyCode = computed(() => props.currencyCode ?? initialState?.currencyCode ?? '')
const timeZone = computed(() => props.timeZone ?? initialState?.dateTimeZone ?? 'UTC')

const formatTime = (date: Date) => {
  try {
    return new Intl.DateTimeFormat(localeSymbol.value.replace('_', '-') || undefined, {
      dateStyle: 'medium',
      timeStyle: 'medium',
      timeZone: timeZone.value,
    }).format(date)
  } catch {
    return date.toLocaleString()
  }
}

const now = ref(new Date())
const time = computed(() => formatTime(now.value))
const timer = setInterval(() => {
  now.value = new Date()
}, 1000)
onBeforeUnmount(() => clearInterval(timer))
</script>

<style lang="scss" scoped>
.locale-information {
  margin-block-start: 1ex;

  .locale {
    margin-inline-end: calc(var(--default-grid-baseline) * 2);

    &.heading {
      font-weight: normal;
      font-style: italic;
    }

    &.time,
    &.thelocale {
      color: var(--color-primary-element);
    }

    &.timezone,
    &.currency {
      color: var(--color-element-error, var(--color-error));
    }
  }
}
</style>
