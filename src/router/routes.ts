/**
 * @copyright Copyright (c) 2024-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import type { RouteRecordRaw } from 'vue-router';

import { translate as t } from '@nextcloud/l10n';
import { END_POINT_PAGE as controllerEndPoint } from '../../build/ts-types/php-modules/Controller/VueAppController.ts';
import { appName } from '../config.ts';
import addContactsToProjectsRoute from './add-contacts-to-project.ts';
import calendarRoutes from './calendar-routes.ts';

// import Console from '../util/console.ts';
// const COMPONENT_NAME = 'router';
// const logger = new Console(COMPONENT_NAME);

export type HomeRouteName = 'home';
export type LegayPageRouteName = 'legacy-page';
export type RouteNames = HomeRouteName|LegayPageRouteName;

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'home',
    component: () => import('../components/StartPage.vue'),
    props: (_to) => ({
      routeTitle: t(appName, 'Home'),
    }),
  },
  {
    // use the human readable project-name and not the data base id for the URLs
    path: `/${controllerEndPoint}/:template/:projectName?`,
    component: () => import('../components/LegacyWrapperRouterReactivity.vue'),
    name: 'legacy-page',
    props: false,
    children: [
      addContactsToProjectsRoute,
      calendarRoutes,
    ],
  },
];

export default routes;
