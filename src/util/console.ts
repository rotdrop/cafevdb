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

export const syncStackFrame = (offset: number) => StackTrace.getSync(stackTraceOptions)?.[offset+1];
export const asyncStackFrame = async (offset: number) => {
  const stackFrames = await StackTrace.get(stackTraceOptions);
  return stackFrames?.[offset + 1];
};

export const stackFrame = async (offset: number) => (globalState.debugModes & DEBUG_SMAPS)
  ? asyncStackFrame(offset)
  : syncStackFrame(offset);

type ConsoleMethods = 'debug'|'info'|'error'|'trace';

class Console {
  constructor(prefix: string) {
    this.prefix = prefix;
  }
  prefix: string;
  private timestamp() {
    return (new Date()).toLocaleTimeString("en-gb", { hour: '2-digit', minute: '2-digit', second: '2-digit', fractionalSecondDigits: 3 });
  }
  private async asyncLocationMessage() {
    const time = this.timestamp();
    try {
      const frame = (await stackFrame(2)).toString();
      return time + ' ' + this.prefix + ': ' + frame;
    } catch {
      return time + ' ' + this.prefix;
    }
  };
  private syncLocationMessage() {
    const time = this.timestamp();
    try {
      const frame = syncStackFrame(2).toString();
      return time + ' ' + this.prefix + ': ' + frame;
    } catch {
      return time + ' ' + this.prefix;
    }
  };
  private emitMessage(method: ConsoleMethods, ...args: any[]) {
    if (globalState.debugModes & DEBUG_SMAPS) {
      this.asyncLocationMessage().then(message => console[method](message, ...args));
    } else {
      console[method](this.syncLocationMessage(), ...args);
    }
  }
  debug(...args: any[]) {
    return this.emitMessage('debug', ...args);
  };
  async info(...args: any[]) {
    return this.emitMessage('info', ...args);
  };
  async error(...args: any[]) {
    return this.emitMessage('error', ...args);
  };
  async trace(...args: any[]) {
    return this.emitMessage('trace', ...args);
  };
}

export default Console;
