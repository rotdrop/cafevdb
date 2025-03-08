/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import useCalendarsStore from '@nextcloud/app-calendar/src/store/calendars.js';
import usePrincipalsStore from '@nextcloud/app-calendar/src/store/principals.js';
import useSettingsStore from '@nextcloud/app-calendar/src/store/settings.js';
import { initializeClientForUserView } from '@nextcloud/app-calendar/src/services/caldavService.js';
import loadMomentLocalization from '@nextcloud/app-calendar/src/utils/moment.js';
import getTimeZoneManager from '@nextcloud/app-calendar/src/services/timezoneDataProviderService.js';
import Console from '../util/console.ts';
import type { Store } from 'pinia';

export interface Calendar {
  owner: string,
}

export type CalendarsStore = Store<
  'calendars',
  {
    initialCalendarsLoaded: boolean,
    loadCollections: () => Promise<{ calendars: Calendar[] }>,
  }
>;
export type PricipalsStore = Store<
  'principals',
  {
    fetchCurrentUserPrincipal: () => Promise<void>,
    fetchPrincipalByUrl: ({ url }) => Promise<void>,
  }
>;

export type SettingsStore = Store<
  'settings',
  {
    setMomentLocale: ({ locale }) => void,
  }
>

// make sure all the required data is loaded in order to (mis-)reuse
// the calendar editor widgets.
const calendarStoreSetup = async () => {

  const logger = new Console('calendarSetup');

  const calendarsStore: CalendarsStore = useCalendarsStore();
  const principalsStore: PricipalsStore = usePrincipalsStore();
  const settingsStore: SettingsStore = useSettingsStore();
  loadMomentLocalization().then((locale: string) => { settingsStore.setMomentLocale({ locale }) })

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
  })
  owners.forEach((owner) => {
    principalsStore.fetchPrincipalByUrl({ url: owner });
  });
  logger.debug('Calendar stores have been setup');
  return {
    calendarsStore,
    principalsStore,
    settingsStore,
  };
};

export default calendarStoreSetup;
