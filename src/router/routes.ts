/**
 * @copyright Copyright (c) 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 *
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

import { appName } from '../config.ts';
import { translate as t } from '@nextcloud/l10n';
import calendarRoutes from './calendar-routes.ts';
import type { RouteConfig } from 'vue-router';

// import Console from '../util/console.ts';
// const COMPONENT_NAME = 'router';
// const logger = new Console(COMPONENT_NAME);

export type HomeRouteName = 'home';
export type LegayPageRouteName = 'legacy-page';
export type RouteNames = HomeRouteName|LegayPageRouteName;

const routes: RouteConfig[] = [
  {
    path: '/',
    name: 'home',
    props: (/* router */) => ({
      routeTitle: t(appName, 'Home'),
    }),
  },
  {
    path: '/p/:template/:projectId(\\d+)?/:projectName?',
    component: () => import('../components/LegacyWrapperRouterReactivity.vue'),
    name: 'legacy-page',
    props: true,
    children: calendarRoutes,
  },
];

export default routes;
