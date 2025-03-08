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
import type {
  Location,
  NavigationGuardNext,
  Route,
  RouteConfig,
  RouteRecord,
} from 'vue-router';

const COMPONENT_NAME = 'CalendarRoutes';
const logger = new Console(COMPONENT_NAME);

const returnByPush = true;

const ProjectEventsListing = async () => {
  return import('../components/ProjectEventsListing.vue');
};

const calendarSetup = async () => {
  // make sure the timezones are actually loaded
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

export const CALENDAR_APP_ROUTES = [
  'EditPopoverView',
  'EditSidebarView',
  'NewPopoverView',
  'NewSidebarView',
];

let preCalendarRoute: Location|undefined;
let pushDepth = 0;

const beforeCalendarRouteEnter = <V extends Vue>(to: Route, from: Route, next: NavigationGuardNext<V>) => {
  if (!CALENDAR_APP_ROUTES.includes(from.name!)) {
    logger.info('Remember previous route before entering calendar stuff', {
      from,
      to,
    });
    let prev: undefined|RouteRecord;
    for (const match of to.matched) {
      if (CALENDAR_APP_ROUTES.includes(match.name!)) {
        break;
      }
      prev = match;
    }
    if (prev) {
      preCalendarRoute = {
        name: prev.name,
        params: to.params,
        query: to.query,
      };
    }
  }
  // preserve the post-data hash
  if (from.query.hash && !to.query.hash) {
    const target = {
      name: to.name!,
      params: to.params,
      query: Object.assign({}, to.query || {}, { hash: from.query.hash }),
      replace: to.transition === 'replace',
    }
    next(target);
  } else {
    if (to.transition === 'push') {
      ++pushDepth;
      logger.debug('PUSH DEPTH INCREASE', {
        pushDepth,
        to: { ...to },
        from: { ...from },
        windowHistoryLength: window.history.length,
      });
    }
    next();
  }
};

const calendarAppRoutes: RouteConfig[] = [
  {
    path: 'edit/popover/:object/:recurrenceId/:context?',
    name: 'EditPopoverView',
    component: SimpleEventEditor,
    beforeEnter: beforeCalendarRouteEnter,
  },
  {
    path: 'edit/sidebar/:object/:recurrenceId/:context?',
    name: 'EditSidebarView',
    component: SidebarEventEditor,
    beforeEnter: beforeCalendarRouteEnter,
  },
  {
    path: 'new/popover/:allDay/:dtstart/:dtend/:context?',
    name: 'NewPopoverView',
    component: SimpleEventEditor,
    beforeEnter: beforeCalendarRouteEnter,
  },
  {
    path: 'new/sidebar/:allDay/:dtstart/:dtend/:context?',
    name: 'NewSidebarView',
    component: SidebarEventEditor,
    beforeEnter: beforeCalendarRouteEnter,
  },
  {
    path: '--never--',
    name: 'CalendarView',
    beforeEnter: (to, _from, next) => {
      if (returnByPush && pushDepth > 0) {
        logger.info('Try go back', pushDepth);
        next(false);
        asyncEmit(HISTORY_GO_REQUEST, { level: -pushDepth });
        pushDepth = 0;
      } else if (preCalendarRoute) {
        logger.info('Try restore previous route on leaving calendar stuff', preCalendarRoute);
        const target = {
          ...preCalendarRoute,
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


// p/projects/events/345/event/edit/popover/...

export const PROJECT_EVENTS_LISTING_NAME = 'ProjectEventsListing';

const projectEventRoutes: RouteConfig[] = [
  {
    path: 'events/:eventsProjectId',
    name: PROJECT_EVENTS_LISTING_NAME,
    component: ProjectEventsListing,
    props: route => ({ projectId: +route.params.eventsProjectId }),
    beforeEnter: <V extends Vue>(to: Route, from: Route, next: NavigationGuardNext<V>) => {
      logger.info('BEFORE PROJECT EVENTS LISTING ENTER', {
        to,
        from,
      });
      // preserve the post-data hash
      if (from.query.hash && !to.query.hash) {
        const target = {
          name: to.name!,
          params: to.params,
          query: Object.assign({}, to.query || {}, { hash: from.query.hash }),
          replace: to.transition === 'replace',
        }
        next(target);
      } else {
        next();
      }
    },
    children: calendarAppRoutes,
  },
];

export default projectEventRoutes;
