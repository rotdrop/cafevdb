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
import { initializeClientForUserView } from '@nextcloud/app-calendar/src/services/caldavService.js';
import Console from '../util/console.ts';

interface Calendar {
  owner: string,
}

const calendarStoreSetup = async () => {

  const logger = new Console('calendarSetup');

  const calendarsStore = useCalendarsStore()
  const principalsStore = usePrincipalsStore()

  if (calendarsStore.initialCalendarsLoaded) {
    logger.info('INITIAL CALENDARS ALREADY LOADED')
    return
  }
  logger.info('initializeClientForUserView')
  await initializeClientForUserView()
  logger.info('fetchCurrentUserPrincipal')
  await principalsStore.fetchCurrentUserPrincipal()
  logger.info('loadCollections')
  const { calendars, trashBin } = await calendarsStore.loadCollections()
  logger.info('calendars and trash bin loaded', { calendars, trashBin })
  const owners: string[] = []
  calendars.forEach((calendar: Calendar) => {
    if (owners.indexOf(calendar.owner) === -1) {
      owners.push(calendar.owner)
    }
  })
  owners.forEach((owner) => {
    principalsStore.fetchPrincipalByUrl({ url: owner })
  });
};

export default calendarStoreSetup;
