/**
 * @copyright Copyright (c) 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { loadTranslations } from '@nextcloud/l10n'
import Console from '../util/console.ts';
import { HISTORY_GO_REQUEST } from '../event-bus-events.ts';
import { emit as asyncEmit } from '../services/async-event-bus.ts';
import type { Route, RouteConfig } from 'vue-router';

const COMPONENT_NAME = 'CalendarRoutes';
const logger = new Console(COMPONENT_NAME);

const calendarSetup = async () => {
  // make sure the timezone are actually loaded
  // @ts-expect-error
  import('@nextcloud/app-calendar/css/app-sidebar.scss');
  import('../services/calendar-store-setup.ts')
    .then(({ default: calendarStoreSetup }) => calendarStoreSetup());
  // translations are probably not reactive, so we have to await their
  // loading.
  await loadTranslations('calendar', () => {});
};

const SimpleEventEditor = async () => {
  await calendarSetup()
  return import('@nextcloud/app-calendar/src/views/EditSimple.vue');
}

const SidebarEventEditor = async () => {
  await calendarSetup()
  return import('@nextcloud/app-calendar/src/views/EditSidebar.vue');
};

const calenderEditRoutes = [
  'EditPopoverView',
  'EditSidebarView',
  'NewPopoverView',
  'NewSidebarView',
];

let preCalendarRoute: Route|undefined;
let pushDepth = 0;

const calendarRoutes: RouteConfig[] = [
  {
    path: 'event/edit/popover/:object/:recurrenceId/:context?',
    name: 'EditPopoverView',
    component: SimpleEventEditor,
    beforeEnter: (_to, from, next) => {
      if (!calenderEditRoutes.includes(from.name!)) {
        logger.info('Remember previous route before entering calendar stuff', from);
        preCalendarRoute = from;
      }
      ++pushDepth;
      next();
    },
  },
  {
    path: 'event/edit/sidebar/:object/:recurrenceId/:context?',
    name: 'EditSidebarView',
    component: SidebarEventEditor,
    beforeEnter: (_to, from, next) => {
      if (!calenderEditRoutes.includes(from.name!)) {
        logger.info('Remember previous route before entering calendar stuff', from);
        preCalendarRoute = from;
      }
      ++pushDepth;
      next();
    },
  },
  {
    path: 'event/new/popover/:allDay/:dtstart/:dtend/:context?',
    name: 'NewPopoverView',
    component: SimpleEventEditor,
    beforeEnter: (_to, from, next) => {
      if (!calenderEditRoutes.includes(from.name!)) {
        logger.info('Remember previous route before entering calendar stuff', from);
        preCalendarRoute = from;
      }
      ++pushDepth;
      next();
    },
  },
  {
    path: 'event/new/sidebar/:allDay/:dtstart/:dtend/:context?',
    name: 'NewSidebarView',
    component: SidebarEventEditor,
    beforeEnter: (_to, from, next) => {
      if (!calenderEditRoutes.includes(from.name!)) {
        logger.info('Remember previous route before entering calendar stuff', from);
        preCalendarRoute = from;
      }
      ++pushDepth;
      next();
    },
  },
  {
    path: '--never--',
    name: 'CalendarView',
    beforeEnter: (to, _from, next) => {
      if (pushDepth > 0 && pushDepth < 0) { // grin So: the history
        // tail is deleted by a push, but not by simple go back. This
        // means that going back will keep the history stack as is and
        // just move to the desired position. Then clicking next would
        // "move back" to the previous view which is probably not what
        // the user would expect, so it is probably really better to
        // only push to the history, and not restore a previous view
        // by a programmatic go-to.
        //
        // The only way around be complicated: if we have a
        // previous-previous view, move to that view and then push. If
        // there is no such view, move to the base view and replace --
        // oh no.
        logger.info('Try go back', pushDepth);
        next(false);
        asyncEmit(HISTORY_GO_REQUEST, { level: -pushDepth });
      } else if (preCalendarRoute) {
        logger.info('Try restore previous route on leaving calendar stuff', preCalendarRoute);
        const target = {
          name: preCalendarRoute.name!,
          params: preCalendarRoute.params,
          query: preCalendarRoute.query,
          // Unconditional replace would be wrong, we just redirect
          // the push to --never-- to the previous page, but still
          // push to the history stack. So just keep the transition
          // type of the original target route.
          replace: to.transition === 'replace',
        };
        preCalendarRoute = undefined;
        next(target);
      } else {
        logger.error('No previous route defined');
        next({ name: 'home', replace: to.transition === 'replace' })
      }
    },
  },
];

export default calendarRoutes;
