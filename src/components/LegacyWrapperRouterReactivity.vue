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
<script lang="ts">
// import globalState from '../app/globalstate.js'
import LegacyWrapper from './LegacyWrapper.vue'
import mixins from '../mixins/app-mixins.js'
import objectHash from 'object-hash'

export default {
  name: 'LegacyWrapperRouterReactivity',
  components: {
    LegacyWrapper,
  },
  mixins,
  beforeRouteEnter(to, from, next) {
    next(self => {
      self.debug('BEFORE ROUTE ENTER', to, from, window?.history?.state)
      self.onRouteChange(to)
    })
  },
  beforeRouteUpdate(to, from, next) {
    this.debug('BEFORE ROUTE UPDATE', to, from, window?.history?.state)
    this.onRouteChange(to)
    next()
  },
  beforeRouteLeave(to, from, next) {
    this.debug('BEFORE ROUTE LEAVE', to, from, window?.history?.state)
    next()
  },
  data() {
    return {
      template: null,
      templateParameters: {},
      postDataHash: null,
      noLegacyReload: false,
    }
  },
  methods: {
    onRouteChange(to) {
      this.info('onRouteChange()', to)
      this.template = to?.params?.template
      this.templateParameters.projectId = to?.params?.projectId
      this.templateParameters.projectName = to?.params?.projectName
      this.postDataHash = to?.query?.hash
      this.noLegacyReload = +to?.query?.['no-reload'] === 1
      if (!this.postDataHash) {
        this.postDataHash = objectHash(to?.params || {})
      }
    },
  },
}
</script>
