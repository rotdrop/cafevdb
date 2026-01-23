/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022, 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { mapDavCollectionToCalendar } from '@nextcloud/app-calendar/src/models/calendar.js';
import useCalendarsStore from '@nextcloud/app-calendar/src/store/calendars.js';
import usePrincipalsStore from '@nextcloud/app-calendar/src/store/principals.js';
import useSettingsStore from '@nextcloud/app-calendar/src/store/settings.js';
import useFechtedTimeRanges from '@nextcloud/app-calendar/src/store/fetchedTimeRanges.js';
import useCalendarObjectsStore from '@nextcloud/app-calendar/src/store/calendarObjects.js';
import { findAllCalendars, initializeClientForUserView } from '@nextcloud/app-calendar/src/services/caldavService.js';
import loadMomentLocalization from '@nextcloud/app-calendar/src/utils/moment.js';
import getTimeZoneManager from '@nextcloud/app-calendar/src/services/timezoneDataProviderService.js';
import Console from '../util/console.ts';
// the used eslint + packages is far too old -- will change after NC has moved to Vue3
// eslint-disable-next-line n/no-missing-import
import type { Calendar, CalendarObjectsStore, CalendarsStore, FetchedTimeRangesStore, PrincipalsStore, SettingsStore } from '@nextcloud/app-calendar';

const logger = new Console('calendarSetup');

type CalendarSyncHandlerArg = {
  calendarObjectsStore: CalendarObjectsStore,
  calendarsStore: CalendarsStore,
  fetchedTimeRangesStore: FetchedTimeRangesStore,
  principalsStore: PrincipalsStore,
};

export let backgroundSyncJob: NodeJS.Timeout|undefined;

const calendarSyncHandler = async ({
  calendarObjectsStore,
  calendarsStore,
  fetchedTimeRangesStore,
  principalsStore,
}: CalendarSyncHandlerArg) => {
  const currentUserPrincipal = principalsStore.getCurrentUserPrincipal;
  const calendars = (await findAllCalendars())
    .map((calendar: Calendar) => mapDavCollectionToCalendar(calendar, currentUserPrincipal));
  for (const calendar of calendars) {
    const existingSyncToken = calendarsStore.getCalendarSyncToken(calendar);
    if (!existingSyncToken && !calendarsStore.getCalendarById(calendar.id)) {
      // New calendar!
      logger.debug(`Adding new calendar ${calendar.url}`);
      calendarsStore.addCalendarMutation({ calendar });
      continue;
    }
    if (calendar.dav.syncToken === existingSyncToken) {
      continue;
    }
    logger.debug(`Refetching calendar ${calendar.url} (syncToken changed)`);
    const fetchedTimeRanges = fetchedTimeRangesStore
      .getAllTimeRangesForCalendar(calendar.id);
    for (const timeRange of fetchedTimeRanges) {
      fetchedTimeRangesStore.removeTimeRange({
        timeRangeId: timeRange.id,
      });
      calendarsStore.deleteFetchedTimeRangeFromCalendarMutation({
        calendar,
        fetchedTimeRangeId: timeRange.id,
      });
    }

    calendarsStore.updateCalendarSyncToken({
      calendar,
      syncToken: calendar.dav.syncToken,
    });
    logger.info('BUMP MUTATIONS COUNT', { oldModCount: calendarObjectsStore.modificationCount });
    calendarObjectsStore.modificationCount++;
  }
};

// make sure all the required data is loaded in order to (mis-)reuse
// the calendar editor widgets.
const calendarStoreSetup = async () => {

  const calendarsStore: CalendarsStore = useCalendarsStore();
  const principalsStore: PrincipalsStore = usePrincipalsStore();
  const settingsStore: SettingsStore = useSettingsStore();
  loadMomentLocalization().then((locale: string) => { settingsStore.setMomentLocale({ locale }); });

  if (calendarsStore.initialCalendarsLoaded) {
    logger.debug('INITIAL CALENDARS ALREADY LOADED');
    return {
      calendarsStore,
      principalsStore,
      settingsStore,
    };
  }
  getTimeZoneManager();
  await initializeClientForUserView();
  await principalsStore.fetchCurrentUserPrincipal();
  const { calendars } = await calendarsStore.loadCollections();
  const owners: string[] = [];
  calendars.forEach((calendar: Calendar) => {
    if (owners.indexOf(calendar.owner) === -1) {
      owners.push(calendar.owner);
    }
  });
  owners.forEach((owner) => {
    principalsStore.fetchPrincipalByUrl({ url: owner });
  });

  if (backgroundSyncJob) {
    clearInterval(backgroundSyncJob);
  }
  backgroundSyncJob = setInterval(() => calendarSyncHandler({
    calendarObjectsStore: useCalendarObjectsStore(),
    calendarsStore,
    fetchedTimeRangesStore: useFechtedTimeRanges(),
    principalsStore,
  }), 1000 * 60);

  logger.debug('Calendar stores have been setup');
  return {
    calendarsStore,
    principalsStore,
    settingsStore,
  };
};

export default calendarStoreSetup;
