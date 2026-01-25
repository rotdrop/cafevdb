/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2026-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

declare module '@nextcloud/app-calendar/src/models/calendar.js';
declare module '@nextcloud/app-calendar/src/services/caldavService.js';
declare module '@nextcloud/app-calendar/src/services/timezoneDataProviderService.js';
declare module '@nextcloud/app-calendar/src/store/calendars.js';
declare module '@nextcloud/app-calendar/src/store/fetchedTimeRanges.js';
declare module '@nextcloud/app-calendar/src/store/principals.js';
declare module '@nextcloud/app-calendar/src/store/settings.js';
declare module '@nextcloud/app-calendar/src/utils/moment.js';

declare module '@nextcloud/app-calendar' {

  import type { Store } from 'pinia';

  export interface Calendar {
    owner: string,
  }

  export interface TimeRange {
    id: number,
  }

  export type CalendarsStore = Store<
    'calendars',
    {
      initialCalendarsLoaded: boolean,
      loadCollections: () => Promise<{ calendars: Calendar[] }>,
      getCalendarSyncToken: (calendar: Calendar) => string,
      getCalendarById: (id: string) => Calendar,
      addCalendarMutation: ({ calendar: Calendar }) => void,
      deleteFetchedTimeRangeFromCalendarMutation: ({ calendar: Calendar, fetchedTimeRangeId: number }) => void,
      updateCalendarSyncToken: ({ calendar: Calendar, syncToken: string }) => void,
    }
  >;
  export type PrincipalsStore = Store<
    'principals',
    {
      fetchCurrentUserPrincipal: () => Promise<void>,
      fetchPrincipalByUrl: ({ url }) => Promise<void>,
      getCurrentUserPrincipal: () => unknown,
    }
  >;
  export type FetchedTimeRangesStore = Store<
    'fetchedTimeRanges',
    {
      getAllTimeRangesForCalendar: (id: number) => TimeRange[],
      removeTimeRange: ({ timeRangeId: number }) => void,
    }
  >;

  export type SettingsStore = Store<
    'settings',
    {
      setMomentLocale: ({ locale }) => void,
    }
  >;

  interface CalendarObject {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    [key: string]: any,
    calendarId: string,
  }

  interface CalendarObjectInstance {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    [key: string]: any,
    startDate: Date,
    endDate: Date,
    description: null|string,
    categories: string[],
    isAllDay: boolean,
    title: string,
    location: null|string,
  }

  type CalendarObjectsStore = Store<
    'calendarObjects',
    {
      modificationCount: number,
      calendarObjects: Record<string, CalendarObject>,
      updateCalendarObject: ({ calendarObject }) => Promise<unknown>,
    }
  >;

  type CalendarObjectInstanceStore = Store<
    'calendarObjectInstance',
    {
      isNew: boolean|null,
      calendarObject: CalendarObject|null,
      calendarObjectInstance: CalendarObjectInstance|null,
      existingEvent: {
        objectId: string|null,
        recurrenceId: number|null,
      },
      getCalendarObjectInstanceByObjectIdAndRecurrenceId: (arg: { objectId: string, recurrenceId: number, reload?: boolean })
      => Promise<{
        calendarObject: CalendarObject,
        calendarObjectInstance: CalendarObjectInstance,
      }>,
      addCategory: (arg: { calendarObjectInstance: CalendarObjectInstance, category: string }) => void,
      removeCategory: (arg: { calendarObjectInstance: CalendarObjectInstance, category: string }) => void,
      saveCalendarObjectInstance: (arg: { thisAndAllFuture: boolean, calendarId: string }) => Promise<void>,
      deleteCalendarObjectInstance: (arg: { thisAndAllFuture: boolean }) => Promise<void>,
    }
  >;
}
