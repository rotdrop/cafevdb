/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2024, 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import CAFeVDB from './CAFeVDB.vue';
import globalState from './app/globalstate.ts';
import * as Authorization from './authorization.ts';
import { appName } from './config.ts';
import { mixin as globalMixin } from './mixins/global-mixin.ts';
import router from './router/app-router.ts';
import { provideMountableComponents } from './services/mountable-components.ts';

import 'core-js/actual';
import './webpack-setup.ts';

// Enable dev-tools also needs unsafe-eval on script-src in the CSP.
// window.__VUE_DEVTOOLS_GLOBAL_HOOK__.enabled = true;
// window.__VUE__ = Vue;

const pinia = createPinia();

const provide = {
  appId: appName,
  ...Object.fromEntries(
    Object.entries(Authorization).filter(([key]) => key.startsWith('PERMISSION_')),
  ),
};

const app = createApp(CAFeVDB);
app.directive('tooltip', Tooltip);
app.mixin(globalMixin);
app.use(router);
app.use(pinia);
for (const [key, value] of Object.entries(provide)) {
  app.provide(key, value);
}
app.mount('#content');

globalState.vueMode = true;

provideMountableComponents(app);

export default app;
