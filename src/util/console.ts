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

export function stackFrame(offset: number) {
  const trace = StackTrace.getSync();
  const frame = trace?.[offset + 1];
  return frame;
}

class Console {
  constructor(prefix: string) {
    this.prefix = prefix;
  }
  prefix: string;
  locationMessage() {
    return this.prefix + ': ' + stackFrame(2).toString();
  };
  debug(...args: any[]) {
    console.debug(this.locationMessage(), ...args);
  };
  info(...args: any[]) {
    console.info(this.locationMessage(), ...args);
  };
  error(...args: any[]) {
    console.error(this.locationMessage(), ...args);
  };
  trace(...args: any[]) {
    console.trace(this.locationMessage(), ...args);
  };
}

export default Console;
