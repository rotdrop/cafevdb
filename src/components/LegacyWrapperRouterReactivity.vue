<!--
 * Orchestra member, musicion and project management application.
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
 -
 - THIS IS A GURKEREI. The vue router-view will not update properties
 - when the url params change. What a pity. Therefore wrap the desired
 - reactive component into an outer wrapper which injects the router
 - params as properties.
 -->
<template>
  <div class="container flex-container">
    <LegacyWrapper :template="template"
                   :templateParameters="templateParameters"
                   :hash="postDataHash"
                   :noLegacyReload="noLegacyReload"
                   class="legacy-page-wrapper"
    />
    <!-- Project-event editing -->
    <div class="router-view-container">
      <router-view />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { RouteLocationNormalizedGeneric } from 'vue-router'

import { onBeforeMount, ref } from 'vue'
import {
  onBeforeRouteLeave,
  // onBeforeRouteUpdate,
  useRoute,
  useRouter,
} from 'vue-router'
import LegacyWrapper from './LegacyWrapper.vue'
import {
  ADD_CONTACTS_TO_PROJECT,
  PROJECT_EVENTS_LISTING,
} from '../event-bus-events.ts'
import { ADD_CONTACTS_TO_PROJECT_NAME } from '../router/add-contacts-to-project.ts'
import { PROJECT_EVENTS_LISTING_NAME } from '../router/calendar-routes.ts'
import { subscribe as asyncSubscribe } from '../services/async-event-bus.ts'
import Console from '../util/console.ts'
import { sanitizeTemplateParams } from '../util/legacy-post-data.ts'

const COMPONENT_NAME = 'LegacyWrapperRouterReactivity'
const logger = new Console(COMPONENT_NAME)

const template = ref('')
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const templateParameters = ref<Record<string, any>>({})
const postDataHash = ref<undefined|string>(undefined)
const noLegacyReload = ref(false)

// onBeroreRouteEnter cannot exist, however, if we only want to react
// to the current route on component creation time then we can simply
// access it vie useRoute()
const currentRoute = useRoute()
const router = useRouter()

logger.debug('BEFORE ROUTE ENTER', { ...currentRoute }, { ...window?.history?.state })

asyncSubscribe(PROJECT_EVENTS_LISTING, async (event) => {
  const location = {
    name: PROJECT_EVENTS_LISTING_NAME,
    params: {
      ...currentRoute.params,
      eventsProjectName: '' + event.projectName,
    },
    query: currentRoute.query,
  }
  try {
    return await router.push(location)
  } catch (error) {
    logger.error('ROUTE PUSH FAILED', {
      error,
      location,
      currentRoute: { ...currentRoute },
    })
  }
})

asyncSubscribe(ADD_CONTACTS_TO_PROJECT, async (event) => {
  const location = {
    name: ADD_CONTACTS_TO_PROJECT_NAME,
    params: {
      ...currentRoute.params,
      addContactsProjectName: '' + event.projectName,
    },
    query: currentRoute.query,
  }
  try {
    return await router.push(location)
  } catch (error) {
    logger.error('ROUTE PUSH FAILED', {
      error,
      location,
      currentRoute: { ...currentRoute },
    })
  }
})

const onRouteChange = (to: RouteLocationNormalizedGeneric) => {
  logger.info(
    'onRouteChange()',
    {
      to: { ...to },
      historyState: window?.history?.state,
      currentRoute: { ...currentRoute },
    },
  )
  template.value = to.params.template as string
  // Object.assign(templateParameters.value, to.params)
  templateParameters.value = sanitizeTemplateParams(to.params)
  postDataHash.value = (to.query?.hash as string) || undefined
  noLegacyReload.value = +(to.query?.['no-reload'] ?? 0) === 1
}

onBeforeMount(() => {
  logger.debug('ON BEFORE MOUNT', { ...currentRoute }, { ...window?.history?.state })
  onRouteChange(currentRoute)
})

// This used to work with vue-router 3 in that the wrapped
// LegacyWrapper component had its currentRoute instance already set
// to "to". Updates now seem to run quicker, so updating the
// LegacyWrapper has to wait until route transition has been
// confirmed.
//
// onBeforeRouteUpdate((to, from) => {
//   logger.debug('ON BEFORE ROUTE UPDATE', {
//     to: { ...to },
//     from: { ...from },
//     windowState: { ...(window?.history?.state || {}) },
//     route: { ...currentRoute },
//   })
// })

const unregister = router.afterEach((to, from) => {
  logger.debug('AFTER EACH', {
    to: { ...to },
    from: { ...from },
    windowState: { ...(window?.history?.state || {}) },
    route: { ...currentRoute },
  })
  if (!to.path.includes('--never--')
    && (to.name === 'legacy-page'
      || (to.matched.length > 1 && to.matched[0].name === 'legacy-page'))) {
    onRouteChange(to)
  }
})

onBeforeRouteLeave((to, from) => {
  logger.debug('ON BEFORE ROUTE LEAVE', { ...to }, { ...from }, window?.history?.state)
  unregister()
})
</script>

<style scoped lang="scss">
@use '../../style/mixins/flex.scss';
@include flex.flexRules;
.container {
  height: 100%;
  > .legacy-page-wrapper {
    flex-shrink: 1;
    max-width: 100%;
    overflow: auto
  }
}
</style>
