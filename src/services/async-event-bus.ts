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

import globalState from '../app/globalstate.js';
import { SimpleBus } from '@rotdrop/async-nextcloud-event-bus';
import type { AsyncNextcloudEvents, EventHandler, EventArg } from '@rotdrop/async-nextcloud-event-bus';

if (!globalState.eventBus) {
  globalState.eventBus = new SimpleBus();
}
const bus = globalState.eventBus;

/**
 * Register an event listener
 *
 * @param name name of the event
 * @param handler callback invoked for every matching event emitted on the bus
 */
export function subscribe<K extends keyof AsyncNextcloudEvents>(
  name: K,
  handler: EventHandler<AsyncNextcloudEvents[K]>,
) {
  return bus.subscribe(name, handler);
}

/**
 * Unregister a previously registered event listener
 *
 * Note: doesn't work with anonymous functions (closures). Use method of an object or store listener function in variable.
 *
 * @param name name of the event
 * @param handler callback passed to `subscribed`
 */
export function unsubscribe<K extends keyof AsyncNextcloudEvents>(
       name: K,
       handler: EventHandler<AsyncNextcloudEvents[K]>,
) {
  bus.unsubscribe(name, handler);
}

/**
 * Emit an event
 *
 * @param name name of the event
 * @param event event payload
 */
export function emit<K extends keyof AsyncNextcloudEvents>(
  name: K,
  ...event: EventArg<AsyncNextcloudEvents, K>
       // eslint-disable-next-line @typescript-eslint/no-explicit-any
): Promise<any> {
  return bus.emit(name, ...event);
}

/**
 * Unsubscribe all subscribers for an event.
 *
 * @param name name of the event
 */
export function unsubscribeAll<K extends keyof AsyncNextcloudEvents>(
  name: K,
): void {
  bus.unsubscribeAll(name);
}

/**
 * Check if the given event has any subscribers.
 *
 * @param name The name of the event to examine.
 */
export function hasSubscriptions<K extends keyof AsyncNextcloudEvents>(
  name: K,
): boolean {
  return bus.hasSubscriptions(name);
}
