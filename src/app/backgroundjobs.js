/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2013, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import $ from './jquery.js';
import { getCurrentUser } from '@nextcloud/auth';
import generateUrl from './generate-url.js';

require('../legacy/nextcloud/jquery/requesttoken.js');

const cloudUser = getCurrentUser();

if (window.CAFEVDB === undefined) {
  window.CAFEVDB = {};
}
const globalState = window.CAFEVDB;

globalState.BackgroundJobs = {
  timer: false,
  interval: 600,
};

const url = generateUrl('backgroundjob/trigger');

const runner = function() {
  const self = globalState.BackgroundJobs;
  if (cloudUser) {
    console.info('Triggered background jobs.');
    $.get(url).always(function() {
      self.timer = setTimeout(runner, self.interval * 1000);
      console.info('Restarted background jobs.');
    });
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

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
