/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2023, 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import Vue from 'vue';
import AdminSettings from './components/AdminSettings.vue';
import { createPinia, PiniaVuePlugin } from 'pinia';
import { Tooltip } from '@nextcloud/vue';

Vue.directive('tooltip', Tooltip);

Vue.use(PiniaVuePlugin);
const pinia = createPinia();

// eslint-disable-next-line
__webpack_public_path__ = generateFilePath(appName, '', 'js');

Vue.mixin({ data() { return { appName }; }, methods: { t, n } });

const vueAnchorId = 'admin-settings-vue';

export default new Vue({
  el: '#' + vueAnchorId,
  render: h => h(AdminSettings),
  pinia,
});
