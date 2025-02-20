/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2013, 2020-2022, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { getCurrentUser } from '@nextcloud/auth';
import generateAppUrl from './generate-url.js';
import globalState from './globalstate.js';
import axios from '@nextcloud/axios';

require('../legacy/nextcloud/jquery/requesttoken.js');

const cloudUser = getCurrentUser();

globalState.BackgroundJobs = {
  timer: false,
  interval: 600,
};

const url = generateAppUrl('backgroundjob/trigger');

const runner = async function() {
  const self = globalState.BackgroundJobs;
  if (cloudUser) {
    console.info('Triggering background jobs.');
    try {
      await axios.get(url);
      console.info('Successful return from background jobs.');
    } catch (error) {
      if (error?.status !== 429) {
        console.info('Failed running background jobs', error);
      }
    } finally {
      self.timer = setTimeout(runner, self.interval * 1000);
    }
  } else if (self.timer !== false) {
    clearTimeout(self.timer);
    self.timer = false;
    console.info('Stopped background jobs.');
  }
};

const start = function() {
  const self = globalState.BackgroundJobs;
  if (cloudUser) {
    self.timer = setTimeout(runner, self.interval * 1000);
    console.info('Started background jobs.');
    runner();
  } else if (self.timer !== false) {
    clearTimeout(self.timer);
    self.timer = false;
    console.info('Stopped background jobs.');
  }
};

export default start;
