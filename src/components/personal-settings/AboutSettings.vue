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
<!-- Ported from templates/settings/about.php. Sub-sections replace the legacy accordion/fieldsets. -->
<template>
  <NcSettingsSection :id="entry.id" class="about-settings" :name="entry.name">
    <div class="product name">
      {{ about.name }} v{{ about.version }}
      <template v-if="about.codeName">
        AKA "{{ about.codeName }}"
      </template>
    </div>
    <div class="product description">
      {{ about.description }}
    </div>
    <div class="product author">
      <span class="text">&copy; {{ about.lifetime }}</span>
      <span class="hspace" />
      <a :href="authorMailto">{{ about.author }} &langle;{{ about.authorEmail }}&rangle;</a>
    </div>
    <div class="sourcecode">
      <span class="text">{{ t(appId, 'Source Code:') }}</span>
      <span class="hspace" />
      <a :target="appId + '-source'" :href="about.homePage">{{ about.homePage }}</a>
    </div>
    <div class="product licence">
      <a target="_wikipedia" :href="wikipedia(about.licenceName)">{{ t(appId, 'Licenced under the {licence}', { licence: about.licence }) }}</a>
    </div>
    <div class="product credits">
      <div class="product credits intro">
        {{ t(appId, 'There is a long list of free software projects this application depends on, coded in the spare-time of an amazingly long list of volunteers. We just randomly choose five projects and display them below. The list refreshes "itself" every {seconds} seconds.', { seconds: refreshSeconds }) }}
      </div>
      <div class="product credits list">
        <ul>
          <li v-for="item in visibleCredits" :key="item.link">
            <a target="_creditlink" :href="item.link">{{ item.title }}</a>
          </li>
        </ul>
      </div>
    </div>
  </NcSettingsSection>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import { NcSettingsSection } from '@nextcloud/vue'
import { computed, onBeforeUnmount, ref } from 'vue'
import { appName as appId } from '../../config.ts'
import getInitialState from '../../toolkit/util/initial-state.ts'
import type { TabEntry } from './settings-tree.ts'

type CreditItem = { title: string, link: string }

const NUM_VISIBLE_CREDITS = 5
const refreshSeconds = 30

const about = getInitialState<{
  name: string,
  version: string,
  codeName: string,
  description: string,
  lifetime: string,
  licence: string,
  licenceName: string,
  author: string,
  authorEmail: string,
  homePage: string,
  credits: CreditItem[],
}>({
  section: 'about',
  defaults: {
    name: appId,
    version: '',
    codeName: '',
    description: '',
    lifetime: '',
    licence: '',
    licenceName: '',
    author: '',
    authorEmail: '',
    homePage: '',
    credits: [],
  },
})!

const wikipedia = (title: string) => 'http://www.wikipedia.org/w/index.php?title='
  + encodeURIComponent(title.replace(/ /g, '_'))

const authorMailto = computed(() => 'mailto:'
  + encodeURIComponent(`${about.author}<${about.authorEmail}>`) + '?subject=[cafevdb] ')

// Randomly choose a few distinct credit items and refresh them periodically.
const pickCredits = () => {
  const items = [...about.credits]
  for (let i = items.length - 1; i > 0; --i) {
    const j = Math.floor(Math.random() * (i + 1));
    [items[i], items[j]] = [items[j], items[i]]
  }
  return items.slice(0, NUM_VISIBLE_CREDITS)
}
const visibleCredits = ref<CreditItem[]>(pickCredits())
const timer = setInterval(() => { visibleCredits.value = pickCredits() }, refreshSeconds * 1000)
onBeforeUnmount(() => clearInterval(timer))

defineProps<{ entry: TabEntry }>()
</script>

<style lang="scss" scoped>
.about-settings {
  a {
    font-weight: bold;

    &:hover {
      font-style: italic;
    }
  }

  .hspace {
    display: inline-block;
    width: 1em;
  }

  .product.credits {
    &.intro {
      padding: 1em;
      font-style: italic;
    }

    &.list ul {
      list-style-type: circle;
      list-style-position: inside;
    }
  }
}
</style>
