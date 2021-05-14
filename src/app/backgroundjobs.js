/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2013, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

import { globalState, cloudUser, $ } from './globals.js';
import generateUrl from './generate-url.js';

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

const documentReady = function() {
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

export default documentReady;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
