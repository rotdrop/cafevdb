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

import { generateEntities, entities, dtos } from './entity-repository-setup.ts';
import { beforeAll, jest } from '@jest/globals';
import entityFactory from '@/src/toolkit/services/entity-factory.ts';

const entityNames = ['ProjectParticipant', 'Project', 'Musician'] as const;

jest.mock('@/src/toolkit/services/entity-repository.ts', () => {
  const originalModule: object = jest.requireActual('@/src/toolkit/services/entity-repository.ts');

  return {
    __esModule: true,
    ...originalModule,
    find: (entityName: string, identifier: string) => entities?.[entityName]?.[identifier],
  };
});

beforeAll(generateEntities);

describe('Validate Response Object', () => {
  for (const entityName of entityNames) {
    it('Should have entities and repositories', () => {
      const dto = dtos[entityName];
      expect(dto.ocs.meta.statuscode).toBe(200);
      expect(dto.ocs.meta.message).toBe('OK');
      expect(dto.ocs.meta.status).toBe('ok');
      expect(dto.ocs.data.entities).toBeDefined();
      expect(dto.ocs.data.repositories).toBeDefined();
    });
  }
});

describe('Generate Musician Entity from DTO object', () => {
  it('Should work ;)', async () => {
    const entityName = 'Musician';
    const entityDtos = Object.values(dtos[entityName].ocs.data.repositories[entityName]);
    expect(entityDtos.length).toBe(1);
    const entityDto = entityDtos[0];
    const musician = await entityFactory(entityName, entityDto);
    expect(musician.publicName).toBeTruthy();
    expect(Object.entries(musician.projectParticipation).length).toBe(1);
    const projectId = Object.keys(musician.projectParticipation)[0];
    const participant = await musician.projectParticipation[projectId];
    const musicianId = musician.id;
    expect(participant).toBe(entities.ProjectParticipant[`${projectId}:${musicianId}`]);
    expect(await participant.project).toBe(entities.Project[projectId]);
    expect(await participant.musician).toBe(entities.Musician[musicianId]);
    expect(await participant.musician === musician).toBeFalsy();
  });
});
