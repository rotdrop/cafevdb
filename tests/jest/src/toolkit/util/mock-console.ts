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
 *
 * @package
 * Must be imported first in order to have the mock active.
 */

import { jest } from '@jest/globals';
import { type ConsoleOptions } from '../../../../../src/toolkit/util/console.ts';

let silent = false;

export const setSilent = (arg = false) => { silent = arg; };

const consoleMock = jest.mock('@/src/toolkit/util/console.ts', () => {
  return function(prefix: string, _options?: ConsoleOptions) {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const emitMessage = (method: string, ...args: any[]) => {
      if (!silent) {
        console[method](prefix, ...args);
      }
    };
    return {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      debug: (...args: any[]) => { emitMessage('debug', ...args); },
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      info: (...args: any[]) => { emitMessage('info', ...args); },
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      warn: (...args: any[]) => { emitMessage('warn', ...args); },
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      error: (...args: any[]) => { emitMessage('error', ...args); },
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      trace: (...args: any[]) => { emitMessage('trace', ...args); },
    };
  };
});

export default consoleMock;
