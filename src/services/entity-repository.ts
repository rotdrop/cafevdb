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

import { appName } from '../config.ts';
import {
  reactive,
  set as vueSet,
} from 'vue';
import axios from '@nextcloud/axios';
import { translate as t } from '@nextcloud/l10n';
// eslint-disable-next-line n/no-missing-import
import type { OCSResponse } from '@nextcloud/typings/ocs';
import { generateOcsUrl } from '../toolkit/util/generate-url.ts';
import { type EntityId, type EntityMap } from '../../build/ts-types/php-modules/Database/Doctrine/ORM/EntityMetadata.ts';
import { type EntityResponse } from '../../build/ts-types/php-modules/Database/Doctrine/ORM/Util.ts';
import entityFactory, { type FrontEndEntity } from '../services/entity-factory.ts';
import { AppError } from '../types/errors.ts';
import type { ObjectEntries } from '../types/type-traits.d.ts';

type EntityRepository<E extends keyof EntityMap> = {
  [Identifier: string]: FrontEndEntity<E>;
};

export const repositories = reactive<{ [E in keyof EntityMap]?: EntityRepository<E> }>({});
export const find = <N extends keyof EntityMap>(entityName: N, identifier: string) => {
  return repositories?.[entityName]?.[identifier] ?? undefined;
};

const loadEntities = async <const N extends keyof EntityMap>(url: string) => {
  const response = await axios.get<OCSResponse<EntityResponse<N> > >(url);
  const responseRepositories = response.data.ocs.data.repositories;
  for (const entityName of Object.keys(responseRepositories) as N[]) {
    for (const [identifier, entityDto] of Object.entries(responseRepositories[entityName])) {
      const entity = await entityFactory<N>(entityName, entityDto);
      if (repositories[entityName] === undefined) {
        vueSet(repositories, entityName, {});
      }
      vueSet(repositories[entityName] as object, identifier, entity);
    }
  }
  const entities = response.data.ocs.data.entities;
  const result = Object.fromEntries(
    (Object.entries(entities) as ObjectEntries<typeof entities>).map(
      ([entityName, identifiers]) => [
        entityName,
        Object.fromEntries(
          identifiers!.map(
            identifier => [identifier, find(entityName, identifier)!],
          ) as [string, FrontEndEntity<typeof entityName>][],
        ),
      ],
    ) as ObjectEntries<{
      [K in N]: N extends K ? Record<string, FrontEndEntity<K> > : undefined|Record<string, FrontEndEntity<K> >;
    }>,
  );
  return result;
};

export type SearchArguments<
  N extends keyof EntityMap,
  D extends number = 1,
  L extends null|number = null,
  O extends number = 0,
> = {
  entityName: N;
  findBy: Record<string, string|number|undefined>,
  sortBy?: Record<string, string|number|undefined>,
  depth?: D,
  limit?: L,
  offset?: O,
};

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
 * @param root0.sortBy Sort criteria. Basically everything which is
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
export const search = async <
  N extends keyof EntityMap,
  D extends number = 1,
  L extends null|number = null,
  O extends number = 0,
>({
  entityName,
  findBy,
  sortBy = undefined,
  depth = 1 as D,
  limit = null as L,
  offset = 0 as O,
}: SearchArguments<N, D, L, O>) => {

  const url = generateOcsUrl('v1/entities/{entityName}/{depth}', {
    entityName,
    depth,
    findBy: btoa(JSON.stringify(findBy)),
    sortBy: sortBy ? btoa(JSON.stringify(sortBy)) : null,
    limit,
    offset,
  });
  try {
    return await loadEntities<N>(url);
  } catch (e) {
    throw new AppError(
      { entityName, findBy, depth, limit, offset },
      t(appName, 'Unable to search for entities "{entityName}" with identifier "{criteria}".', {
        entityName, criteria: JSON.stringify(findBy),
      }),
      { cause: e },
    );
  }
};

export type FetchArguments<
  N extends keyof EntityMap,
  D extends number = 1,
> = {
  entityName: N;
  identifier: EntityId<N>,
  depth: D,
};

export const fetch = async <N extends keyof EntityMap, D extends number = 1>({
  entityName,
  identifier,
  depth = 1 as D,
}: FetchArguments<N, D>) => {
  const url = generateOcsUrl(`v1/entities/${entityName}`, {
    find: btoa(JSON.stringify(identifier)),
    depth,
  });
  try {
    return await loadEntities<N>(url);
  } catch (e) {
    throw new AppError(
      { entityName, identifier, depth },
      t(appName, 'Unable to fetch entity "{entityName}" with identifier "{identifier}".', { entityName, identifier: JSON.stringify(identifier) }),
      { cause: e },
    );
  }
};
