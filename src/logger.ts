/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import type { ConsoleMethod } from './util/console.ts';

import { watch } from 'vue';
import { DEBUG_SMAPS } from './debug-modes.ts';
import globalState from './services/legacy-global-state.ts';
import Console, { defaultConsoleOptions } from './util/console.ts';

const COMPONENT = 'CAFEVDB';
const logger = new Console(COMPONENT);

watch(() => globalState.debugMode, (value) => {
  const enableSmaps = !!(value & DEBUG_SMAPS);
  for (const method of Object.keys(defaultConsoleOptions.smaps) as ConsoleMethod[]) {
    logger.enableSourceMaps(method, enableSmaps);
  }
});

export default logger;
