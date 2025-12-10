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

import { generateEntities, entities, dtos } from './entity-repository-setup.ts';
import { beforeAll, jest } from '@jest/globals';
import { type EntityMap } from '@/build/ts-types/php-modules/Database/Doctrine/ORM/EntityMetadata.ts';
import { fetch as fetchEntity, find as findEntity } from '@/src/services/entity-repository.ts';

const entityIdentifiers = {
  ProjectParticipant: { project: 1, musician: 1 },
  Project: { id: 1 },
  Musician: { id: 1 },
} as const;

// Mock axios and set the type
jest.mock('@nextcloud/axios', () => {
  const originalModule: object = jest.requireActual('@nextcloud/axios');

  return {
    __esModule: true,
    ...originalModule,
    default: {
      get: async (url: string) => {
        // url: 'http://localhost/ocs/v2.php/apps/cafevdb//v1/entitites/ProjectParticipant?find=eyJwcm9qZWN0IjoxLCJtdXNpY2lhbiI6MX0%3D&depth=1'
        const prefix = '/ocs/v2.php/apps/cafevdb//v1/entitites/';
        const urlInfo = URL.parse(url);
        if (!urlInfo?.pathname.startsWith(prefix)) {
          throw Error(`Unexpected URL "${url}".`);
        }
        const depth = +(urlInfo?.searchParams?.get('depth') ?? 0);
        const identifier = JSON.parse(atob(urlInfo?.searchParams?.get('find') ?? ''));
        const entityName = urlInfo.pathname.substring(prefix.length);
        switch (entityName) {
          case 'Musician':
          case 'Project':
            if (identifier.id !== 1) {
              throw Error(`Unexpected identifier for "${entityName}".`);
            }
            break;
          case 'ProjectParticipant':
            if (identifier.project !== 1 || identifier.musician !== 1) {
              throw Error(`Unexpected identifier for "${entityName}".`);
            }
            break;
          default:
            throw Error(`Unexpected entity name "${entityName}".`);
        }
        await generateEntities([entityName], depth);
        // console.info('ENTITIES', { entities, dtos });
        return {
          data: dtos[entityName],
        };
      },
    },
  };
});

beforeAll(generateEntities);

describe('Fetch entities', () => {
  it('Should work ;)', async () => {
    for (const [entityName, identifier] of Object.entries(entityIdentifiers)) {
      await fetchEntity(entityName as keyof EntityMap, identifier);
      const flattenedIdentifier = Object.values(identifier).join(':');
      const entity = findEntity(entityName as keyof EntityMap, flattenedIdentifier);
      expect(entity).toBeDefined();
    }
  });
});
