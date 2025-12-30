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

import { generateEntities, dtos } from './entity-repository-setup.ts';
import { beforeAll, jest } from '@jest/globals';
import type { AxiosRequestConfig } from 'axios';

export const entityIdentifiers = {
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
      get: async (url: string, options: AxiosRequestConfig) => {
        // url: 'http://localhost/ocs/v2.php/apps/cafevdb/v1/entitites/ProjectParticipant?find=eyJwcm9qZWN0IjoxLCJtdXNpY2lhbiI6MX0%3D&depth=1'
        const prefix = '/ocs/v2.php/apps/cafevdb/v1/entities/';
        const urlInfo = URL.parse(url);
        const pathName = urlInfo?.pathname;
        if (!pathName?.startsWith(prefix)) {
          throw Error(`Unexpected URL "${url}", path "${pathName}" does not start with "${prefix}".`);
        }
        const axiosQueryParams = options.params ?? {};
        const queryParams = {
          depth: urlInfo?.searchParams?.get('depth'),
          find: urlInfo?.searchParams?.get('find'),
          findBy: urlInfo?.searchParams?.get('findBy'),
          ...axiosQueryParams,
        };
        // const depth = +(queryParams.depth ?? 0);
        const [entityName, depth] = urlInfo!.pathname.substring(prefix.length).split('/');
        if (queryParams.find) {
          const identifier = JSON.parse(atob(queryParams.find ?? '{}'));
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
        } else if (queryParams.findBy) {
          let found = false;
          const criteria = JSON.parse(atob(queryParams.findBy ?? '{}'));
          let namePattern: undefined|RegExp;
          let idPattern: undefined|RegExp;
          for (const [key, value] of Object.entries(criteria) as [string, string][]) {
            if (key.endsWith('name')) {
              namePattern = new RegExp(value.replace(/%/g, '.*'));
            }
            if (key.endsWith('id')) {
              idPattern = new RegExp(value.replace(/%/g, '.*'));
            }
          }
          if (!namePattern && !idPattern) {
            throw Error(`No id or name given to match entity ${entityName}.`);
          }
          switch (entityName) {
            case 'Musician': {
              if ((namePattern && 'Max Musterperson'.match(namePattern)) || (idPattern && '1'.match(idPattern))) {
                found = true;
              }
              break;
            }
            case 'Project': {
              if ((namePattern && 'TestProject2026'.match(namePattern)) || (idPattern && '1'.match(idPattern))) {
                found = true;
              }
              break;
            }
            // case 'ProjectParticipant':
            //   // @todo, perhaps later.
            //   break;
            default:
              throw Error(`Unexpected entity name "${entityName}".`);
          }
          if (!found) {
            return {
              data: {
                ocs: {
                  meta: {
                    status: 'ok',
                    statuscode: 200,
                    message: 'OK',
                  },
                  data: {
                    entities: {
                      [entityName]: [],
                    },
                    repositories: {
                      [entityName]: {},
                    },
                  },
                },
              },
            };
          }
        }
        await generateEntities([entityName as keyof typeof dtos], +depth);
        // console.info('ENTITIES', { entities, dtos });
        return {
          data: dtos[entityName],
        };
      },
    },
  };
});

beforeAll(generateEntities);
