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

import type { GlobalState } from '../app/globalstate.ts';
import type Console from '../util/console.ts';

import {
  subscribe as asyncSubscribe,
  awaitEmit,
  hasSubscriptions,
  unsubscribe,
} from '@rotdrop/async-nextcloud-event-bus';
import { reactive } from 'vue';
import { legacyGlobalState } from '../app/globalstate.ts';
import {
  GLOBAL_STATE_INITIALIZED,
  REQUEST_GLOBAL_STATE,
} from '../event-bus-events.ts';

export type { GlobalState };

export const globalState = reactive({ ...legacyGlobalState });
const {
  resolve: resolveGlobalStateInitialized,
  promise: globalStateInitialized,
} = Promise.withResolvers<GlobalState>();

let logger: Console;

const reactifyGlobalState = function(legacyGlobalState: GlobalState) {
  logger.debug('BEFORE REACTIFY GLOBAL STATE', globalState);
  Object.assign(globalState, legacyGlobalState);
  globalState.PHPMyEdit = { ...legacyGlobalState.PHPMyEdit };
  // reactive(globalState) this alone does not seem to work ...
  logger.debug('AFTER REACTIFY GLOBAL STATE', {
    globalState,
    expanded: { ...globalState },
  });
};

export const synchronizeGlobalState = async () => {
  logger = (await import('../logger.ts')).default;
  if (hasSubscriptions(REQUEST_GLOBAL_STATE)) {
    logger.debug('EMIT REQUEST GLOBAL STATE');
    const legacyGlobalState = await awaitEmit(REQUEST_GLOBAL_STATE);
    reactifyGlobalState(legacyGlobalState);
    logger.debug('AFTER GLOBAL STATE REACTIFY', globalState);
    await awaitEmit(GLOBAL_STATE_INITIALIZED, globalState);
    resolveGlobalStateInitialized(globalState);
  } else {
    logger.debug('WAIT FOR GLOBAL STATE INITIALIZED');
    const initializedHandler = asyncSubscribe(GLOBAL_STATE_INITIALIZED, (legacyGlobalState) => {
      unsubscribe(GLOBAL_STATE_INITIALIZED, initializedHandler);
      reactifyGlobalState(legacyGlobalState);
      logger.debug('AFTER GLOBAL STATE REACTIFY', globalState);
      resolveGlobalStateInitialized(globalState);
      return globalState;
    });
  }
  return globalStateInitialized;
};

export {
  globalStateInitialized,
  resolveGlobalStateInitialized,
};

export default globalState;
