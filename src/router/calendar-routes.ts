/**
 * @copyright Copyright (c) 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import type {
  NavigationGuard,
  RouteLocationAsRelativeGeneric,
  RouteLocationRaw,
  RouteRecord,
  RouteRecordRaw,
} from 'vue-router';

import { loadTranslations } from '@nextcloud/l10n';
import { emit as asyncEmit } from '@rotdrop/async-nextcloud-event-bus';
import { HISTORY_GO_REQUEST } from '../event-bus-events.ts';
import Console from '../util/console.ts';

export type CalendarObjectEditLocation = RouteLocationRaw & {
  name: string;
  params: {
    object: string;
    recurrenceId: string; // seconds
    context: string;
  };
  query: Record<string, string>;
};

export type CalendarObjectAddLocation = RouteLocationRaw & {
  name: string;
  params: {
    allDay: string; // === '1' is used inside the calendar app
    dtstart: string; // seconds
    dtend: string; // secons
    context: string;
  };
  query: Record<string, string>;
};

const COMPONENT_NAME = 'CalendarRoutes';
const logger = new Console(COMPONENT_NAME);

const returnByPush = true;

const ProjectEventsListing = async () => {
  return import('../components/ProjectEventsListing.vue');
};

const calendarSetup = async () => {
  // make sure the timezones are actually loaded
  // @ts-expect-error 2307 blah
  // import('@nextcloud/app-calendar/css/app-full.scss');
  import('../services/calendar-store-setup.ts')
    .then(({ default: calendarStoreSetup }) => calendarStoreSetup());
  // translations are probably not reactive, so we have to await their
  // loading.
  await loadTranslations('calendar');
};

const SimpleEventEditor = async () => {
  await calendarSetup();
  return import('@nextcloud/app-calendar/src/views/EditSimple.vue');
};

const FullEventEditor = async () => {
  await calendarSetup();
  return import('@nextcloud/app-calendar/src/views/EditFull.vue');
};

export const CALENDAR_APP_ROUTES = [
  'EditPopoverView',
  'EditFullView',
  'NewPopoverView',
  'NewFullView',
];

let preCalendarRoute: RouteLocationAsRelativeGeneric|undefined;
let pushDepth = 0;

const beforeCalendarRouteEnter: NavigationGuard = (to, from) => {
  logger.debug('BEFORE CALENDAR ROUTE ENTER', { to, from });
  if (!CALENDAR_APP_ROUTES.includes(from.name! as string)) {
    logger.debug('Remember previous route before entering calendar stuff', {
      from,
      to,
    });
    let prev: undefined|RouteRecord;
    for (const match of to.matched) {
      if (CALENDAR_APP_ROUTES.includes(match.name! as string)) {
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
      query: { ...(to.query || {}), hash: from.query.hash },
      replace: to.transition === 'replace',
    };
    return target;
  } else {
    if (from.path === '/' && from.transition === 'unknown') {
      pushDepth = 1;
    } else if (to.transition === 'push') {
      ++pushDepth;
      logger.debug('PUSH DEPTH INCREASE', {
        pushDepth,
        to: { ...to },
        from: { ...from },
        windowHistoryLength: window.history.length,
      });
    }
    return true;
  }
};

const calendarAppRoutes: RouteRecordRaw[] = [
  {
    path: 'edit/popover/:object/:recurrenceId/:context?',
    name: 'EditPopoverView',
    component: SimpleEventEditor,
    beforeEnter: beforeCalendarRouteEnter,
  },
  {
    path: 'edit/full/:object/:recurrenceId/:context?',
    name: 'EditFullView',
    component: FullEventEditor,
    beforeEnter: beforeCalendarRouteEnter,
  },
  {
    path: 'new/popover/:allDay/:dtstart/:dtend/:context?',
    name: 'NewPopoverView',
    component: SimpleEventEditor,
    beforeEnter: beforeCalendarRouteEnter,
  },
  {
    path: 'new/full/:allDay/:dtstart/:dtend/:context?',
    name: 'NewFullView',
    component: FullEventEditor,
    beforeEnter: beforeCalendarRouteEnter,
  },
  {
    path: '--never--',
    name: 'CalendarView',
    component: () => true,
    beforeEnter: (to, _from) => {
      if (returnByPush && pushDepth > 0) {
        logger.debug('Try go back', pushDepth);
        asyncEmit(HISTORY_GO_REQUEST, { level: -pushDepth });
        pushDepth = 0;
        return false;
      } else if (preCalendarRoute) {
        logger.debug('Try restore previous route on leaving calendar stuff', { preCalendarRoute });
        const target = {
          ...preCalendarRoute,
          // Unconditional replace would be wrong, we just redirect
          // the push to --never-- to the previous page, but still
          // push to the history stack. So just keep the transition
          // type of the original target route.
          replace: to.transition === 'replace',
        };
        preCalendarRoute = undefined;
        return target;
      } else {
        logger.error('No previous route defined');
        return { name: 'home', replace: to.transition === 'replace' };
      }
    },
  },
];

// p/projects/events/345/event/edit/popover/...

export const PROJECT_EVENTS_LISTING_NAME = 'ProjectEventsListing';

const projectEventsRoute: RouteRecordRaw = {
  path: 'events/:eventsProjectName',
  name: PROJECT_EVENTS_LISTING_NAME,
  component: ProjectEventsListing,
  props: (route) => ({ projectName: route.params.eventsProjectName }),
  beforeEnter: (to, from) => {
    logger.debug('BEFORE PROJECT EVENTS LISTING ENTER', {
      to,
      from,
    });
    // preserve the post-data hash
    if (from.query.hash && !to.query.hash) {
      const target = {
        name: to.name!,
        params: to.params,
        query: { ...(to.query || {}), hash: from.query.hash },
        replace: to.transition === 'replace',
      };
      return target;
    } else {
      return true;
    }
  },
  children: calendarAppRoutes,
};

export default projectEventsRoute;
