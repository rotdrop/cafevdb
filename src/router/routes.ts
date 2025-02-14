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
import Console from '../util/console.ts';
import type { Route, RouteConfig } from 'vue-router';

const COMPONENT_NAME = 'router';
const logger = new Console(COMPONENT_NAME);

export type HomeRouteName = 'home';
export type LegayPageRouteName = 'legacy-page';
export type RouteNames = HomeRouteName|LegayPageRouteName;

const SimpleEventEditor = async () => {
  require('@nextcloud/app-calendar/css/app-sidebar.scss');
  const calendarStoreSetup = (await import('../services/calendar-store-setup.ts')).default;
  await calendarStoreSetup();
  return import('@nextcloud/app-calendar/src/views/EditSimple.vue');
}

const calenderEditRoutes = [
  'EditPopoverView',
  'EditSidebarView',
  'NewPopoverView',
  'NewSidebarView',
];

let preCalendarRoute: Route|undefined;

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
    children: [
      {
        path: 'event/edit/popover/:object/:recurrenceId',
        name: 'EditPopoverView',
        component: SimpleEventEditor,
        beforeEnter: (_to, from, next) => {
          if (!calenderEditRoutes.includes(from.name!)) {
            logger.info('Remember previous route before entering calendar stuff', from);
            preCalendarRoute = from;
          }
          next();
        },
      },
      // {
      //   path: '/:view/:firstDay/edit/sidebar/:object/:recurrenceId',
      //   name: 'EditSidebarView',
      //   component: EditSidebar,
      // },
      {
        path: 'event/new/popover/:allDay/:dtstart/:dtend',
        name: 'NewPopoverView',
        component: SimpleEventEditor,
      },
      // {
      //   path: '/:view/:firstDay/new/sidebar/:allDay/:dtstart/:dtend',
      //   name: 'NewSidebarView',
      //   component: EditSidebar,
      // },
      {
        path: '--never--',
        name: 'CalendarView',
        beforeEnter: (_to, _from, next) => {
          if (preCalendarRoute) {
            logger.info('Try restore previous route on leaving calendar stuff', preCalendarRoute);
            const target = {
              name: preCalendarRoute.name!,
              params: preCalendarRoute.params,
              query: preCalendarRoute.query,
              replace: true,
            };
            preCalendarRoute = undefined;
            next(target);
          } else {
            logger.error('No previous route defined');
            next({ name: 'home', replace: true })
          }
        },
      },
    ],
  },
];

export default routes;
