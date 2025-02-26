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
import type { StackFrame } from 'stacktrace-js';

import globalState from '../app/globalstate.js';
import { DEBUG_SMAPS } from '../debug-modes.ts';

export const stackTraceOptions = {
  sourceMapConsumerCache: {},
  sourceCache: {}
}

const syncStackFrames = (offset: number, depth: number) =>
  StackTrace.getSync(stackTraceOptions).slice(offset + 1, offset + 1 + depth)
const asyncStackFrames = async (offset: number, depth: number) => {
  const stackFrames = await StackTrace.get(stackTraceOptions);
  return stackFrames.slice(offset + 1, offset + 1 + depth);
};

export const stackFrames = async (offset: number, depth: number) => (globalState.debugModes & DEBUG_SMAPS)
  ? asyncStackFrames(offset, depth)
  : syncStackFrames(offset, depth);

type ConsoleMethods = 'debug'|'info'|'error'|'trace';

class Console {
  constructor(prefix: string) {
    this.prefix = prefix;
    this.enabled = { debug: true, info: true, error: true, trace: true };
    this.stackDepth = 0;
  }
  private prefix: string;
  private enabled: { debug: boolean, info: boolean, error: boolean, trace: boolean };
  private stackDepth: number;
  private timestamp() {
    return (new Date()).toLocaleTimeString("en-gb", { hour: '2-digit', minute: '2-digit', second: '2-digit', fractionalSecondDigits: 3 });
  }
  private async asyncStackFrames(depth: number) {
    try {
      return (await stackFrames(4, depth));
    } catch {
      return [];
    }
  };
  private syncStackFrames(depth: number) {
    try {
      return syncStackFrames(4, depth);
    } catch {
      return [];
    }
  };
  private locationObject(stack: StackFrame[]) {
    const time = this.timestamp();
    const prefix = time + ' ' + this.prefix + (stack.length > 0 ? (' ' + stack[0].toString()) : '');
    return stack.length > 1 ? [ prefix, { stack: stack.map(entry => entry.toString()) } ] : [ prefix ];
  };
  private emitMessage(method: ConsoleMethods, ...args: any[]) {
    const depth = Math.max(1, (args.length > 0 && typeof args[0] === 'number') ? args.shift() : this.stackDepth);
    if (globalState.debugModes & DEBUG_SMAPS) {
      this.asyncStackFrames(depth).then(stack => { console[method](...this.locationObject(stack), ...args); });
    } else {
      console[method](...this.locationObject(this.syncStackFrames(depth)), ...args);
    }
  }
  debug(...args: any[]) {
    return this.emitMessage('debug', ...args);
  };
  info(...args: any[]) {
    return this.emitMessage('info', ...args);
  };
  error(...args: any[]) {
    return this.emitMessage('error', ...args);
  };
  trace(...args: any[]) {
    return this.emitMessage('trace', ...args);
  };
  disable(method: 'debug'|'info'|'error'|'trace') {
    this.enabled[method] = false;
  };
  enable(method: 'debug'|'info'|'error'|'trace') {
    this.enabled[method] = true;
  };
  withStack(depth: number) {
    this.stackDepth = depth;
  };
  withoutStack() {
    this.stackDepth = 0;
  };
}

export default Console;
