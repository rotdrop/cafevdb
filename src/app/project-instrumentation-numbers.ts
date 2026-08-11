/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2024-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import * as CAFEVDB from './cafevdb.ts';
import { $, globalState } from './globals.ts';
import * as PHPMyEdit from './pme.ts';

require('project-instrumentation-numbers.scss');

const ready = function(_selector?: string) {
};

const documentReady = function() {

  PHPMyEdit.addTableLoadCallback(
    'project-instrumentation-numbers',
    {
      callback(_template, selector, parameters, resizeCB) {
        if (parameters.reason !== 'dialogOpen') {
          resizeCB();
          return;
        }
        ready(selector);
        resizeCB();
      },
      context: globalState,
    },
  );

  CAFEVDB.addReadyCallback(async () => {
    const $container = $(PHPMyEdit.defaultSelector + '.project-instrumentation-numbers');
    if ($container.length <= 0) {
      return; // not for us
    }
    ready();
  });

};

export { documentReady };
