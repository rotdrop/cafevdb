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

import type { OCSResponse } from '@nextcloud/typings/ocs';
import type { EntityResponse } from '~/build/ts-types/php-modules/Toolkit/Doctrine/ORM/EntitySerializer.ts';
import type { FrontEndEntity } from '~/src/toolkit/services/entity-factory.ts';
import type { ObjectEntries } from '~/src/toolkit/types/type-traits.d.ts';

import { spawnSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import entityFactory from '~/src/toolkit/services/entity-factory.ts';

export const entityNames = ['ProjectParticipant', 'Project', 'Musician'] as const;
export type EntityNames = typeof entityNames[number];
const inputFilePrefix = 'EntityRepositoryResponse-' as const;

export const dtos = {} as { [K in EntityNames]: OCSResponse<EntityResponse<K>> };
export const entities = {
  Musician: {},
  Project: {},
  ProjectParticipant: {},
} as { [K in typeof entityNames[number]]: Record<string, FrontEndEntity<K>> };

export const generateEntities = async (names: EntityNames[] = [...entityNames], depth: number = 2) => {
  for (const entityName of names) {
    const inputFile = `${inputFilePrefix}${entityName}.json`;
    spawnSync(path.join(__dirname, 'generate-entity-repository-response.php'), [entityName, TEST_ARTIFACTS, inputFile, '' + depth]);
    const dtoJSON = fs.readFileSync(path.join(TEST_ARTIFACTS, inputFile));
    dtos[entityName] = JSON.parse(dtoJSON.toString()); //  as OCSResponse<EntityResponse<typeof entityName> >;
    const entityRepository = (dtos[entityName] as OCSResponse<EntityResponse<typeof entityName>>).ocs.data.repositories[entityName];
    for (const [identifier, entityDto] of Object.entries(entityRepository) as ObjectEntries<typeof entityRepository>) {
      const entity = await entityFactory(entityName, entityDto);
      entities[entityName][identifier as string] = entity as (typeof entities)[typeof entityName][string];
    }
  }
};
