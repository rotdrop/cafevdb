/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import StackTrace from 'stacktrace-js';

import globalState from '../app/globalstate.js';
import { DEBUG_SMAPS } from '../debug-modes.ts';

export const stackTraceOptions = {
  sourceMapConsumerCache: {},
  sourceCache: {}
}

export const stackFrame = async (offset: number) => {
  const stackFrames = (globalState.debugModes & DEBUG_SMAPS)
    ? await StackTrace.get(stackTraceOptions)
    : StackTrace.getSync(stackTraceOptions);
  return stackFrames?.[offset + 1];
}

class Console {
  constructor(prefix: string) {
    this.prefix = prefix;
  }
  prefix: string;
  async locationMessage() {
    try {
      const frame = (await stackFrame(2)).toString();
      return this.prefix + ': ' + frame;
    } catch {
      return this.prefix;
    }
  };
  async debug(...args: any[]) {
    console.debug(await this.locationMessage(), ...args);
  };
  async info(...args: any[]) {
    console.info(await this.locationMessage(), ...args);
  };
  async error(...args: any[]) {
    console.error(await this.locationMessage(), ...args);
  };
  async trace(...args: any[]) {
    console.trace(await this.locationMessage(), ...args);
  };
}

export default Console;
