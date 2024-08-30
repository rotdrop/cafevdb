/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { appName } from './app/app-info.js';
import { generateFilePath } from '@nextcloud/router';
import { getRequestToken } from '@nextcloud/auth';
// import { sync } from 'vuex-router-sync'
import { translate as t, translatePlural as n } from '@nextcloud/l10n';
import Vue from 'vue';
import App from './App.vue';
import router from './router/app-router.js';
import { createPinia, PiniaVuePlugin } from 'pinia';

Vue.use(PiniaVuePlugin);
const pinia = createPinia();

// CSP config for webpack dynamic chunk loading
// eslint-disable-next-line
__webpack_nonce__ = btoa(getRequestToken());

// eslint-disable-next-line
__webpack_public_path__ = generateFilePath(appName, '', '');

Vue.mixin({ data() { return { appId: appName }; }, methods: { t, n } });

export default new Vue({
  el: '#content',
  name: appName,
  router,
  pinia,
  render: h => h(App),
});
