/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

import { appName } from './config.ts';
import globalState from './app/globalstate.js';
import { generateFilePath } from '@nextcloud/router';
import { getRequestToken } from '@nextcloud/auth';
// import { sync } from 'vuex-router-sync'
import Vue, { set as vueSet } from 'vue';
import CAFeVDB from './CAFeVDB.vue';
import router from './router/app-router.js';
import { createPinia, PiniaVuePlugin } from 'pinia';
import { Tooltip } from '@nextcloud/vue';
import { mixin as globalMixin } from './mixins/global-mixin.ts';
import { GLOBAL_STATE, PME_STATE } from './event-bus-events.ts';
import { subscribe as asyncSubscribe } from '@rotdrop/async-nextcloud-event-bus';
import * as Authorization from './authorization.ts';

Vue.use(PiniaVuePlugin);
const pinia = createPinia();

declare global {
  var __webpack_nonce__: string;
  var __webpack_public_path: string;
}
// CSP config for webpack dynamic chunk loading
__webpack_nonce__ = btoa(getRequestToken() || '');
__webpack_public_path__ = generateFilePath(appName, '', '');

Vue.directive('tooltip', Tooltip);
Vue.mixin(globalMixin);

asyncSubscribe(GLOBAL_STATE, (event) => {
  for (const [key, value] of Object.entries(event.state)) {
    Vue.delete(globalState, key);
    vueSet(globalState, key, value);
  }
});
asyncSubscribe(PME_STATE, (event) => {
  Vue.delete(globalState, 'PHPMyEdit');
  vueSet(globalState, 'PHPMyEdit', event.state);
  for (const [key, value] of Object.entries(event.state)) {
    Vue.delete(globalState.PHPMyEdit, key);
    vueSet(globalState.PHPMyEdit, key, value);
  }
});

const provide = Object.assign(
  {},
  { appId: appName },
  Object.fromEntries(
    Object.entries(Authorization).filter(([key,]) => key.startsWith('PERMISSION_'))
  ),
);

const vueApp = new Vue({
  el: '#content',
  name: appName,
  router,
  pinia,
  render: h => h(CAFeVDB),
  provide,
});

globalState.vueMode = true;
globalState.vue = {
  app: vueApp,
  Vue,
  router,
  store: pinia,
};

export default vueApp;
