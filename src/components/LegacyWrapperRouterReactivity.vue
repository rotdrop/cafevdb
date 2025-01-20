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
  <LegacyWrapper :template="template" :template-parameters="templateParameters" />
</template>
<script>
// import globalState from '../app/globalstate.js'
import LegacyWrapper from './LegacyWrapper.vue'
import mixins from '../mixins/app-mixins.js'

export default {
  name: 'LegacyWrapperRouterReactivity',
  components: {
    LegacyWrapper,
  },
  mixins,
  beforeRouteEnter(to, from, next) {
    next(self => {
      self.info('BEFORE ROUTE ENTER', to, from, self.globalState.vue.router, self)
      self.onRouteChange(to, false)
    })
  },
  beforeRouteUpdate(to, from, next) {
    this.info('BEFORE ROUTE UPDATE', to, from, this.globalState.vue.router, this)
    this.onRouteChange(to, true)
    next()
  },
  beforeRouteLeave(to, from, next) {
    this.info('BEFORE ROUTE LEAVE', to, from, this.globalState.vue.router, this)
    next()
  },
  data() {
    return {
      template: null,
      templateParameters: {},
    }
  },
  computed: {
    // why is there no this.$router??????
    router() {
      return this?.globalState?.vue?.router
    },
  },
  watch: {
    'globalState.vue.router.history.current'(...args) {
      this.info('ROUTER CURRENT CHANGE', ...args)
    },
    '$router'(...args) {
      this.info('ROUTER CHANGE', ...args)
    },
  },
  methods: {
    onRouteChange(to, push) {
      this.info('onRouteChange()')
      // const url = to.path
      const state = history.state
      if (push) {
        this.info('SHOULD PUSH HISTORY STATE', state)
        // history.pushState(state, '', fullUrl)
        // history.replaceState(state, '')
      } else {
        this.info('SHOULD REPLACE HISTORY STATE', state)
        // history.replaceState(state, '')
      }
      this.template = to?.params?.template
      this.templateParameters.projectId = to?.params?.projectId
      this.templateParameters.projectName = to?.params?.projectName
    },
  },
}
</script>
