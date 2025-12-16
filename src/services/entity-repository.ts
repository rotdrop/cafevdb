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
import entityFactory from '../services/entity-factory.ts';
import { AppError } from '../types/errors.ts';

type EntityRepository<E extends keyof EntityMap> = {
  [Identifier: string]: EntityMap[E];
};

export const repositories = reactive<{ [E in keyof EntityMap]?: EntityRepository<E> }>({});
export const find = (entityName: keyof EntityMap, identifier: string) => {
  return repositories?.[entityName]?.[identifier] ?? undefined;
};
export const fetch = async <N extends keyof EntityMap>(
  entityName: N,
  identifier: EntityId<N>,
  depth: number = 1,
) => {
  const url = generateOcsUrl(`/v1/entitites/${entityName}`, {
    find: btoa(JSON.stringify(identifier)),
    depth,
  });
  try {
    const result = await axios.get<OCSResponse<EntityResponse> >(url);
    const responseRepositories = result.data.ocs.data.repositories;
    for (const entityName of Object.keys(responseRepositories) as (keyof EntityMap)[]) {
      for (const [identifier, entityDto] of Object.entries(responseRepositories[entityName])) {
        const entity = entityFactory(entityName, entityDto);
        if (repositories[entityName] === undefined) {
          vueSet(repositories, entityName, {});
        }
        vueSet(repositories[entityName] as object, identifier, entity);
      }
    }
  } catch (e) {
    throw new AppError(
      { entityName, identifier, depth },
      t(appName, 'Unable to fetch entity "{entityName}" with identifier "{identifier}".', { entityName, identifier: JSON.stringify(identifier) }),
      { cause: e },
    );
  }
};
