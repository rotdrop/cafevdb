/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import Tooltip from '@rotdrop/nextcloud-vue-components/lib/directives/Tooltip';
import { createPinia } from 'pinia';
import { createApp } from 'vue';
import AdminSettings from './components/AdminSettings.vue';
import { appName } from './config.ts';
import { mixin as globalMixin } from './mixins/global-mixin.ts';

import 'core-js/actual';

const pinia = createPinia();

const app = createApp(AdminSettings);
app.provide('appId', appName);
app.directive('tooltip', Tooltip);
app.mixin(globalMixin);
app.use(pinia);
app.mount('#admin-settings-vue');

export default app;
