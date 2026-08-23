/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

export type * from './globalstate.ts';
import type { GlobalState } from './globalstate.ts';

import {
  GLOBAL_STATE_INITIALIZED,
  REQUEST_GLOBAL_STATE,
} from '../event-bus-events.ts';
import {
  subscribe as asyncSubscribe,
  awaitEmit,
  hasSubscriptions,
  unsubscribe,
} from '../services/async-event-bus.ts';
import { initialState } from './config.ts';
import { legacyGlobalState, setGlobalStateObject } from './globalstate.ts';

if (!legacyGlobalState.initialized) {
  Object.assign(legacyGlobalState, initialState.CAFEVDB);
}

export const globalStateInitializer = async (initFunction: (legacyGlobalState: GlobalState) => void) => {
  const oldInitialized = legacyGlobalState.initialized && legacyGlobalState.PHPMyEdit.initialized;

  initFunction(legacyGlobalState);

  if (!oldInitialized && legacyGlobalState.initialized && legacyGlobalState.PHPMyEdit.initialized) {
    if (hasSubscriptions(GLOBAL_STATE_INITIALIZED)) {
      console.debug('EMIT GLOBAL STATE INITIALIZED');
      const reactiveGlobalState = await awaitEmit(GLOBAL_STATE_INITIALIZED, legacyGlobalState);
      console.info('AFTER GLOBAL STATE INITIALIZATION', reactiveGlobalState);
      setGlobalStateObject(reactiveGlobalState);
    } else {
      console.debug('AWAIT GLOBAL STATE REQUEST');
      const requestHandler = asyncSubscribe(REQUEST_GLOBAL_STATE, () => {
        unsubscribe(REQUEST_GLOBAL_STATE, requestHandler);
        console.debug('RECEIVED GLOBAL STATE REQUEST');
        return legacyGlobalState;
      });
      const initializedHandler = asyncSubscribe(GLOBAL_STATE_INITIALIZED, (reactiveGlobalState) => {
        unsubscribe(GLOBAL_STATE_INITIALIZED, initializedHandler);
        setGlobalStateObject(reactiveGlobalState);
        return legacyGlobalState;
      });
    }
  }
};
