/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license GNU AGPL version 3 or any later version
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

import { appName } from './app/app-info.js';
import { generateFilePath } from '@nextcloud/router';

import Vue from 'vue';
import AdminSettings from './components/AdminSettings';

// eslint-disable-next-line
__webpack_public_path__ = generateFilePath(appName, '', 'js');

Vue.mixin({ data() { return { appName }; }, methods: { t, n } });

const vueAnchorId = 'admin-settings-vue';
const vueAnchor = document.getElementById(vueAnchorId);

export default new Vue({
  el: '#' + vueAnchorId,
  render: h => h(AdminSettings, { props: { initial: JSON.parse(vueAnchor.dataset.initial) } }),
});
