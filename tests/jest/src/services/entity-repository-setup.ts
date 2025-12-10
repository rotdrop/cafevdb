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

import { spawnSync } from 'child_process';
import type { OCSResponse } from '@nextcloud/typings/ocs';
import path from 'path';
import fs from 'fs';
import { type EntityResponse } from '@/build/ts-types/php-modules/Database/Doctrine/ORM/Util.ts';
import entityFactory from '@/src/services/entity-factory.ts';
import { type EntityMap } from '@/build/ts-types/php-modules/Database/Doctrine/ORM/EntityMetadata.ts';

const entityNames = ['ProjectParticipant', 'Project', 'Musician'] as const;
const inputFilePrefix = 'EntityRepositoryResponse-' as const;

export const dtos = {} as { [K in typeof entityNames[number]]: OCSResponse<EntityResponse> };
export const entities = {
  Musician: {},
  Project: {},
  ProjectParticipant: {},
} as { [K in typeof entityNames[number]]: Record<string, EntityMap[K]> };

export const generateEntities = async (names: (typeof entityNames[number])[] = [...entityNames], depth: number = 2) => {
  for (const entityName of names) {
    const inputFile = `${inputFilePrefix}${entityName}.json`;
    spawnSync(path.join(__dirname, 'generate-entity-repository-response.php'), [entityName, JEST_ARTIFACTS, inputFile, '' + depth]);
    const dtoJSON = fs.readFileSync(path.join(JEST_ARTIFACTS, inputFile));
    dtos[entityName] = JSON.parse(dtoJSON.toString()) as OCSResponse<EntityResponse>;
    for (const [identifier, entityDto] of Object.entries(dtos[entityName].ocs.data.repositories[entityName])) {
      const entity = await entityFactory(entityName, entityDto);
      entities[entityName][identifier] = entity;
    }
  }
};
