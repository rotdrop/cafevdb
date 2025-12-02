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

import { jest, expect } from '@jest/globals';
import {
  emit,
  getEmitResult,
  hasSubscriptions,
  subscribe,
  unsubscribe,
  unsubscribeAll,
} from '../../../../src/services/async-event-bus.ts';

const JEST_EVENT = 'jest-event';

const handler1 = (success: boolean) => new Promise<'handler1'>((resolve, reject) => { setTimeout(() => success ? resolve('handler1') : reject(Error('handler1')), 500); });
const handler2 = (success: boolean) => new Promise<'handler2'>((resolve, reject) => { setTimeout(() => success ? resolve('handler2') : reject(Error('handler2')), 1000); });

declare module '@rotdrop/async-nextcloud-event-bus' {
  export interface AsyncNextcloudEvents {
    [JEST_EVENT]: boolean,
  }
}

describe('async-event-bus', () => {
  afterEach(() => {
    unsubscribeAll(JEST_EVENT);
  });
  it('should not have subscriptions', () => {
    expect(hasSubscriptions(JEST_EVENT)).toBe(false);
  });
  it('should be able to subscribe and unsubscribe', () => {
    subscribe(JEST_EVENT, handler1);
    expect(hasSubscriptions(JEST_EVENT)).toBe(true);
    unsubscribe(JEST_EVENT, handler1);
    expect(hasSubscriptions(JEST_EVENT)).toBe(false);
  });
  it('should be able to unsubscribe all', () => {
    subscribe(JEST_EVENT, handler1);
    subscribe(JEST_EVENT, handler2);
    expect(hasSubscriptions(JEST_EVENT)).toBe(true);
    unsubscribeAll(JEST_EVENT);
    expect(hasSubscriptions(JEST_EVENT)).toBe(false);
  });
  it('should collect a single result with single listeners', async () => {
    subscribe(JEST_EVENT, handler1);
    expect(hasSubscriptions(JEST_EVENT)).toBe(true);
    const result = await emit(JEST_EVENT, true);
    expect(result).toMatchObject([{ status: 'fulfilled', value: 'handler1' }]);
  });
  it('should collect multiple results', async () => {
    subscribe(JEST_EVENT, handler1);
    subscribe(JEST_EVENT, handler2);
    expect(hasSubscriptions(JEST_EVENT)).toBe(true);
    const result = await emit(JEST_EVENT, true);
    expect(result).toMatchObject([
      { status: 'fulfilled', value: 'handler1' },
      { status: 'fulfilled', value: 'handler2' },
    ]);
  });
  it('should simplify collecting results', async () => {
    subscribe(JEST_EVENT, handler1);
    subscribe(JEST_EVENT, handler2);
    expect(hasSubscriptions(JEST_EVENT)).toBe(true);
    const result = await emit(JEST_EVENT, true);
    expect(await getEmitResult(result)).toBe('handler1');
    const expected = ['handler1', 'handler2'];
    const received = await getEmitResult(result, 2);
    expect(received).toEqual(expect.arrayContaining(expected));
    expect(expected).toEqual(expect.arrayContaining(received));
  });
});
