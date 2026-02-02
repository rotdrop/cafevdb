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

import type { EntityMap } from '../../build/ts-types/php-modules/Toolkit/Doctrine/ORM/EntityMetadata.ts';
import { SEARCH_DATABASE_ENTITIES } from '../event-bus-events.ts';
import { emit as asyncEmit, getEmitResult } from './async-event-bus.ts';
import type { search as searchRepository, SearchArguments } from '../toolkit/services/entity-repository.ts';

/**
 * Search for entities of the given name. In order to separate "new"
 * and legacy code the search is wrapped by an event handler. The
 * event listener is required to handle all errors
 *
 * @param root0 Why the heck is this necessary?
 *
 * @param root0.entityName The name of the entity class to search for.
 *
 * @param root0.findBy Search criteria. Basically everything which is
 * understood by the PHP server code FindByTrait.
 *
 * @param root0.depth The "depth" of the entity mesh which is
 * fetched. Defaults to 1, meaning that direct associations will be
 * fetched, but associations of associations will only be represented
 * by their join-column values.
 *
 * @param root0.limit Limit the number of search results. Defaults to unlimited.
 *
 * @param root0.offset Start fetching at the given offset if limit is also set.
 */
const search = async <
  N extends keyof EntityMap,
  D extends number = 0,
  L extends null|number = null,
  O extends number = 0,
>({
  entityName,
  findBy,
  depth = 1 as D,
  limit = null as L,
  offset = 0 as O,
}: SearchArguments<N, D, L, O>) => {
  const result = asyncEmit(SEARCH_DATABASE_ENTITIES, {
    entityName, findBy, depth, limit, offset,
  });

  return await getEmitResult<typeof SEARCH_DATABASE_ENTITIES>(result) as Awaited<ReturnType<typeof searchRepository<N, D, L, O> > >;
};

export default search;
