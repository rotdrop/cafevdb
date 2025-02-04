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

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import Console from '../util/console.ts';
import type { ErrorHandler } from '../types/errors.ts';

const storeId = 'error-handler';

export default defineStore(storeId, () => {
  const loggerRef = ref(new Console(storeId));
  const logger = loggerRef.value;

  logger.debug('ERROR HANDLER STORE INIT');

  const errorHandlerStack = ref<ErrorHandler[]>([]);

  const errorHandler = computed<null|ErrorHandler>(() => errorHandlerStack.value?.[errorHandlerStack.value.length - 1] || null);
  // Mmmh. Reactivity. But in principle here we want to have the
  // handler available RIGHT NOW and not only on the next tick ...
  const getHandler = () => errorHandlerStack.value?.[errorHandlerStack.value.length - 1] || null

  const pushHandler = (handler: ErrorHandler) => {
    errorHandlerStack.value.push(handler);
    logger.error('ERROR HANDLER PUSH', handler, getHandler());
    return handler;
  }
  const popHandler = () => {
    const handler = errorHandlerStack.value.pop();
    logger.error('ERROR HANDLER POP', handler, getHandler());
    return handler;
  },
  return {
    logger: loggerRef,
    errorHandlerStack,
    errorHandler,
    pushHandler,
    popHandler,
    getHandler,
  };
});
