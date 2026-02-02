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

import { appName } from '../config.ts';
import { translate as t } from '@nextcloud/l10n';
import { ref } from 'vue';
import { defineStore } from 'pinia';
import useErrorHandler from './error-handler.ts';
import { AppError, type ErrorContext } from '../toolkit/types/errors.ts';
import Console from '../util/console.ts';
import * as EntityRepository from '../toolkit/services/entity-repository.ts';
import { subscribe as asyncSubscribe } from '../services/async-event-bus.ts';
import { SEARCH_DATABASE_ENTITIES } from '../event-bus-events.ts';

const storeId = 'entities';

export default defineStore(storeId, () => {
  const errorHandlerProvider = useErrorHandler();
  const errorHandler = <E extends Error>(error: E, context: ErrorContext) => {
    if (error instanceof AppError) {
      errorHandlerProvider.errorHandler(error);
    } else {
      const myError = new AppError(context, t(appName, 'Entity Storage Error'), { cause: error });
      console.error('APPERROR', { error: myError });
      errorHandlerProvider.errorHandler(myError);
    }
  };

  const loggerRef = ref(new Console(storeId));
  const logger = loggerRef.value;

  logger.debug('DATABASE ENTITY STORE INIT');

  asyncSubscribe(
    SEARCH_DATABASE_ENTITIES,
    async (event) => {
      try {
        return await EntityRepository.search(event);
      } catch (e) {
        errorHandler(e as Error, event);
        return {} as ReturnType<typeof EntityRepository.search>;
      }
    },
  );

  return {
    loggerRef,
    errorHandler,
    find: EntityRepository.find,
    fetch: EntityRepository.fetch,
    search: EntityRepository.search,
    repositories: EntityRepository.repositories,
  };
});
