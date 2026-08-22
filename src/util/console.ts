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

import type { ConsoleOptions } from '../toolkit/util/console.ts';

import { DEBUG_SMAPS } from '../debug-modes.ts';
import globalState from '../services/legacy-global-state.ts';
import ToolKitConsole from '../toolkit/util/console.ts';

class Console extends ToolKitConsole {

  constructor(prefix: string, options?: ConsoleOptions) {
    const debugSmaps = !!(globalState.debugMode & DEBUG_SMAPS);
    options = {
      smaps: { ...{ debug: debugSmaps, info: debugSmaps, error: debugSmaps, trace: debugSmaps }, ...(options?.smaps || {}) },
      stackDepth: options?.stackDepth || 0,
    };
    super(prefix, options);
  }

}

export * from '../toolkit/util/console.ts';

export default Console;
