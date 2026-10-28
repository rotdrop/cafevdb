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
  <NcButton v-if="collapsible && narrowScreen"
            class="personal-settings-navigation-toggle"
            variant="primary"
            :aria-label="t(appId, 'Toggle settings navigation')"
            :aria-expanded="navigationOpen"
            @click="navigationOpen = !navigationOpen"
  >
    <template #icon>
      <CloseIcon v-if="navigationOpen" :size="20" />
      <ListIcon v-else :size="20" />
    </template>
  </NcButton>
  <nav class="personal-settings-navigation"
       :class="{ 'navigation-open': navigationOpen }"
       :aria-label="t(appId, 'Settings navigation')">
    <ul>
      <li v-for="tab in tree" :key="tab.id">
        <a :href="'#' + tab.id"
           :class="{ active: activeId === tab.id, 'child-active': isChildActive(tab) }"
           @click.prevent="scrollTo(tab.id)"
        >{{ tab.name }}</a>
        <ul v-if="tab.children.length > 0">
          <li v-for="child in tab.children" :key="child.id">
            <a :href="'#' + child.id"
               :class="{ active: activeId === child.id }"
               @click.prevent="scrollTo(child.id)"
            >{{ child.name }}</a>
          </li>
        </ul>
      </li>
    </ul>
  </nav>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import ListIcon from 'vue-material-design-icons/FormatListBulleted.vue'
import type { TabEntry } from './settings-tree.ts'
import { appName as appId } from '../../config.ts'

// On narrow screens the navigation is hidden unless "collapsible" is set,
// in which case a floating toggle button opens it as an overlay.
const props = defineProps<{ tree: TabEntry[], collapsible?: boolean }>()

const navigationOpen = ref(false)

const narrowQuery = window.matchMedia('(width < 1024px)')
const narrowScreen = ref(narrowQuery.matches)
const updateNarrowScreen = (event: MediaQueryListEvent) => {
  narrowScreen.value = event.matches
  if (!event.matches) {
    navigationOpen.value = false
  }
}
narrowQuery.addEventListener('change', updateNarrowScreen)

const activeId = ref<string|null>(null)

const allIds = () => props.tree.flatMap((tab) => [tab.id, ...tab.children.map((child) => child.id)])

const isChildActive = (tab: TabEntry) => tab.children.some((child) => child.id === activeId.value)

const scrollTo = (id: string) => {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  navigationOpen.value = false
  activeId.value = id
}

// Scroll spy: the topmost visible element of the settings tree is "active".
const visible = new Set<string>()
let observer: IntersectionObserver | null = null

onMounted(() => {
  const ids = allIds()
  observer = new IntersectionObserver((records) => {
    for (const record of records) {
      if (record.isIntersecting) {
        visible.add(record.target.id)
      } else {
        visible.delete(record.target.id)
      }
    }
    const first = ids.find((id) => visible.has(id))
    if (first) {
      activeId.value = first
    }
  }, { rootMargin: '0px 0px -60% 0px' })
  for (const id of ids) {
    const element = document.getElementById(id)
    if (element) {
      observer.observe(element)
    }
  }
})

onBeforeUnmount(() => {
  observer?.disconnect()
  narrowQuery.removeEventListener('change', updateNarrowScreen)
})
</script>

<style lang="scss" scoped>
.personal-settings-navigation {
  position: sticky;
  top: 1rem;
  align-self: flex-start;
  flex: 0 0 14rem;
  max-height: calc(100vh - 6rem);
  overflow-y: auto;
  padding: 0 1rem;
  border-inline-start: 1px solid var(--color-border);

  ul ul {
    padding-inline-start: 1rem;
  }

  a {
    display: block;
    padding: 0.2rem 0.5rem;
    border-radius: var(--border-radius);
    color: var(--color-main-text);

    &:hover {
      background-color: var(--color-background-hover);
    }

    &.active {
      background-color: var(--color-primary-element-light);
      font-weight: bold;
    }

    &.child-active {
      font-weight: bold;
    }
  }

  // Same breakpoint at which Nextcloud collapses the left navigation.
  @media only screen and (width < 1024px) {
    display: none;

    &.navigation-open {
      display: block;
      position: fixed;
      top: 5rem;
      right: 1rem;
      bottom: 5rem;
      z-index: 1500;
      max-height: none;
      width: min(18rem, 100vw - 2rem);
      padding: 1rem;
      border: 1px solid var(--color-border);
      border-radius: var(--border-radius-large);
      background-color: var(--color-main-background);
      box-shadow: 0 0 10px var(--color-box-shadow);
    }
  }
}

.personal-settings-navigation-toggle {
  position: fixed;
  right: 1rem;
  bottom: 1rem;
  z-index: 1501;
}
</style>
