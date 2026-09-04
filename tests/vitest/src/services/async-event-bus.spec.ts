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

import {
  emit,
  getEmitResult,
  hasSubscriptions,
  subscribe,
  unsubscribe,
  unsubscribeAll,
} from '@rotdrop/async-nextcloud-event-bus';
import {
  afterEach,
  describe,
  expect,
  it,
} from 'vitest';

const TEST_EVENT = 'test-event' as const;

const handler1 = (success: boolean) => new Promise<'handler1'>(
  (resolve, reject) => { setTimeout(() => success ? resolve('handler1') : reject(Error('handler1')), 500); },
);
const handler2 = (success: boolean) => new Promise<'handler2'>(
  (resolve, reject) => { setTimeout(() => success ? resolve('handler2') : reject(Error('handler2')), 1000); },
);

declare module '@rotdrop/async-nextcloud-event-bus' {
  export interface AsyncNextcloudEvents {
    [TEST_EVENT]: {
      arg: boolean;
      res: string;
    };
  }
}

describe('async-event-bus', () => {
  afterEach(() => {
    unsubscribeAll(TEST_EVENT);
  });
  it('should not have subscriptions', () => {
    expect(hasSubscriptions(TEST_EVENT)).toBe(false);
  });
  it('should be able to subscribe and unsubscribe', () => {
    subscribe(TEST_EVENT, handler1);
    expect(hasSubscriptions(TEST_EVENT)).toBe(true);
    unsubscribe(TEST_EVENT, handler1);
    expect(hasSubscriptions(TEST_EVENT)).toBe(false);
  });
  it('should be able to unsubscribe all', () => {
    subscribe(TEST_EVENT, handler1);
    subscribe(TEST_EVENT, handler2);
    expect(hasSubscriptions(TEST_EVENT)).toBe(true);
    unsubscribeAll(TEST_EVENT);
    expect(hasSubscriptions(TEST_EVENT)).toBe(false);
  });
  it('should collect a single result with single listeners', async () => {
    subscribe(TEST_EVENT, handler1);
    expect(hasSubscriptions(TEST_EVENT)).toBe(true);
    const result = await emit(TEST_EVENT, true);
    expect(result).toMatchObject([{ status: 'fulfilled', value: 'handler1' }]);
  });
  it('should collect multiple results', async () => {
    subscribe(TEST_EVENT, handler1);
    subscribe(TEST_EVENT, handler2);
    expect(hasSubscriptions(TEST_EVENT)).toBe(true);
    const result = await emit(TEST_EVENT, true);
    expect(result).toMatchObject([
      { status: 'fulfilled', value: 'handler1' },
      { status: 'fulfilled', value: 'handler2' },
    ]);
  });
  it('should simplify collecting results', async () => {
    subscribe(TEST_EVENT, handler1);
    subscribe(TEST_EVENT, handler2);
    expect(hasSubscriptions(TEST_EVENT)).toBe(true);
    const result = await emit(TEST_EVENT, true);
    expect(await getEmitResult(result)).toBe('handler1');
    const expected = ['handler1', 'handler2'];
    const received = await getEmitResult(result, 2);
    expect(received).toEqual(expect.arrayContaining(expected));
    expect(expected).toEqual(expect.arrayContaining(received));
  });
});
