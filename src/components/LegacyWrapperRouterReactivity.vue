<!--
 * Orchestra member, musicion and project management application.
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
 -
 - THIS IS A GURKEREI. The vue router-view will not update properties
 - when the url params change. What a pity. Therefore wrap the desired
 - reactive component into an outer wrapper which injects the router
 - params as properties.
 -->
<template>
  <LegacyWrapper :template="template"
                 :template-parameters="templateParameters"
                 :hash="postDataHash"
                 :no-legacy-reload="noLegacyReload"
  />
</template>
<script setup lang="ts">
// import globalState from '../app/globalstate.js'
import { ref, onBeforeMount } from 'vue'
import LegacyWrapper from './LegacyWrapper.vue'
import objectHash from 'object-hash'
import {
  onBeforeRouteLeave,
  onBeforeRouteUpdate,
  useRoute,
} from 'vue-router/composables'
import type { Route } from 'vue-router'
import Console from '../util/console.ts'

const COMPONENT_NAME = 'LegacyWrapperRouterReactivity'
const logger = new Console(COMPONENT_NAME)

const template = ref('')
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const templateParameters = ref<Record<string, any> >({})
const postDataHash = ref<undefined|string>(undefined)
const noLegacyReload = ref(false)

const onRouteChange = (to: Route) => {
  logger.info('onRouteChange()', to, window?.history?.state)
  template.value = to.params.template
  Object.assign(templateParameters.value, to.params)
  delete templateParameters.value.template
  postDataHash.value = (to.query?.hash as string) || undefined
  noLegacyReload.value = +to.query?.['no-reload'] === 1
  if (!postDataHash.value) {
    postDataHash.value = objectHash(to.params || {})
  }
}

// onBeroreRouteEnter cannot exist, however, if we only want to react
// to the current route on component creation time then we can simply
// access it vie useRoute()
const initialRoute = useRoute()

logger.debug('BEFORE ROUTE ENTER', { ...initialRoute }, { ...window?.history?.state })
onBeforeMount(() => {
  logger.debug('ON BEFORE MOUNT', { ...initialRoute }, { ...window?.history?.state })
  onRouteChange(initialRoute)
})

onBeforeRouteUpdate((to, from, next) => {
  logger.debug('ON BEFORE ROUTE UPDATE', to, from, window?.history?.state)
  onRouteChange(to)
  next()
})

onBeforeRouteLeave((to, from, next) => {
  logger.debug('ON BEFORE ROUTE LEAVE', to, from, window?.history?.state)
  next()
})
</script>
