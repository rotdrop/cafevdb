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
 *
 * @package
 * Must be imported first in order to have the mock active.
 */

import type { ConsoleMethod } from '~/src/toolkit/util/console.ts';

import { vi } from 'vitest';

let silent = false;

export function setSilent(arg = false) { silent = arg; }
function getSilent() { return silent; }

vi.mock(import('~/src/toolkit/util/console.ts'), async (originalImport) => {
  const OriginalConsole = await originalImport();
  const mockedConsole = vi.fn(OriginalConsole.default);

  const emitMessage = (method: ConsoleMethod, prefix: string, ...args: any[]) => {
    if (!getSilent()) {
      console[method](prefix, ...args);
    }
  };

  for (const method of ['debug', 'info', 'warn', 'error', 'trace'] as ConsoleMethod[]) {
    mockedConsole.prototype[method] = function(...args: any[]) { emitMessage(method, this.prefix, ...args); };
  }
  mockedConsole.prototype.setSilent = setSilent;
  return {
    default: mockedConsole,
  };
});
