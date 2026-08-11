/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2013, 2020-2022, 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import axios from '@nextcloud/axios';
import { END_POINT } from '../../build/ts-types/php-modules/Controller/BackgroundJobController.ts';
import { isAxiosError } from '../toolkit/types/axios-type-guards.ts';
import generateAppUrl from '../toolkit/util/generate-url.ts';

import '../legacy/nextcloud/jquery/requesttoken.js';

const cloudUser = getCurrentUser();

const control = {
  timer: false as false|NodeJS.Timeout,
  interval: 600,
};

const url = generateAppUrl(END_POINT);

const runner = async function() {
  if (cloudUser) {
    console.info('Triggering background jobs.');
    try {
      await axios.get(url);
      console.info('Successful return from background jobs.');
    } catch (error) {
      if (!isAxiosError(error) || (error.status !== 429)) {
        console.info('Failed running background jobs', error);
      }
    } finally {
      control.timer = setTimeout(runner, control.interval * 1000);
    }
  } else if (control.timer !== false) {
    clearTimeout(control.timer);
    control.timer = false;
    console.info('Stopped background jobs.');
  }
};

const start = function() {
  if (cloudUser) {
    control.timer = setTimeout(runner, control.interval * 1000);
    console.info('Started background jobs.');
    runner();
  } else if (control.timer !== false) {
    clearTimeout(control.timer);
    control.timer = false;
    console.info('Stopped background jobs.');
  }
};

export default start;
