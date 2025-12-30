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

import { entityIdentifiers } from './mock-axios-entity-repository-controller.ts';
import { type EntityMap } from '@/build/ts-types/php-modules/Database/Doctrine/ORM/EntityMetadata.ts';
import { fetch as fetchEntity, find as findEntity, search as searchEntities } from '@/src/services/entity-repository.ts';
import type { ObjectEntries } from '@/src/types/type-traits';
import { describe, it, expect } from '@jest/globals';

describe('Fetch entities', () => {
  it('Should work ;)', async () => {
    for (const [entityName, identifier] of Object.entries(entityIdentifiers) as ObjectEntries<typeof entityIdentifiers>) {
      await fetchEntity({ entityName, identifier });
      const flattenedIdentifier = Object.values(identifier).join(':');
      const entity = findEntity(entityName as keyof EntityMap, flattenedIdentifier);
      expect(entity).toBeDefined();
    }
  });
  it('Should throw ;)', async () => {
    await expect(fetchEntity({
      entityName: 'Instrument',
      identifier: { id: 'blahblahblah' },
    }))
      .rejects
      .toThrow('Unable to fetch entity');
  });
  it('Should find existing entities', async () => {
    const query = '%Test%';
    const findBy = { '(|name': query, id: query, ')': true };
    const result = await searchEntities({ entityName: 'Project', findBy });
    const entity = result?.Project?.['1'];
    expect(entity).toBeDefined();
  });
  it('Should return empty result', async () => {
    const query = '%TestFooBlah%';
    const findBy = { '(|name': query, id: query, ')': true };
    const result = await searchEntities({ entityName: 'Project', findBy });
    const entity = result?.Project?.['1'];
    expect(entity).toBeUndefined();
  });
  it('Search for unknown entity throw ;)', async () => {
    await expect(searchEntities({
      entityName: 'Instrument',
      findBy: {},
    }))
      .rejects
      .toThrow('Unable to search for entities');
  });
});
