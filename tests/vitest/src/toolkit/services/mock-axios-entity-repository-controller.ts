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

import type { AxiosRequestConfig } from 'axios';
import type { ProjectFoldersResponse as ProjectFolders } from '~/build/ts-types/php-modules/Controller/DTO.ts';

import { beforeAll, vi } from 'vitest';
import { dtos, entities, entityNames, generateEntities } from './entity-repository-setup.ts';

export const entityIdentifiers = {
  ProjectParticipant: { project: 1, musician: 1 },
  Project: { id: 1 },
  Musician: { id: 1 },
};

export const projectsFolder = 'orchestra/projects';
export const projectFolders = {
  balancesFolder: 'orchestra/finance/balances/projects',
  projectParticipantsFolder: 'participants',
  projectPostersFolder: 'posters',
  projectPublicDownloadsFolder: 'downlaods',
  projectsFolder,
};

if (window !== undefined) {
  window._oc_webroot = '';
}

async function get(url: string, options: AxiosRequestConfig) {
  // url: 'http://localhost/ocs/v2.php/apps/cafevdb/v1/entitites/ProjectParticipant?find=eyJwcm9qZWN0IjoxLCJtdXNpY2lhbiI6MX0%3D&depth=1'
  if (url.endsWith('cafevdb/tooltips')) {
    const result = {
      data: Object.fromEntries(
        (options?.params?.keys ?? []).map((key: string) => [key, `TranslationTag ${key}`]),
      ),
    };
    return result;
  } else if (
    url.endsWith(`/apps/cafevdb/projects/${entityIdentifiers.Project.id}/folder/all`)
      || url.endsWith(`/apps/cafevdb/projects/${entityIdentifiers.Project.id}/folder`)
  ) {
    const result: { data: ProjectFolders } = {
      data: projectFolders,
    };
    return result;
  }
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
        if (identifier.id !== entityIdentifiers.Musician.id) {
          throw Error(`Unexpected identifier for "${entityName}".`);
        }
        break;
      case 'Project':
        if (identifier.id !== entityIdentifiers.Project.id) {
          throw Error(`Unexpected identifier for "${entityName}".`);
        }
        break;
      case 'ProjectParticipant':
        if (identifier.project !== entityIdentifiers.Project.id || identifier.musician !== entityIdentifiers.Musician.id) {
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
        if ((namePattern && 'Max Musterperson'.match(namePattern)) || (idPattern && ('' + entityIdentifiers.Musician.id).match(idPattern))) {
          found = true;
        }
        break;
      }
      case 'Project': {
        if ((namePattern && 'TestProject2026'.match(namePattern)) || (idPattern && ('' + entityIdentifiers.Project.id).match(idPattern))) {
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
  if (entityNames.includes(entityName)) {
    await generateEntities([entityName], +depth);
    return {
      // poor human beings faking Axios reponse: we really really do only need the data property
      data: dtos[entityName],
    };
  } else {
    return {
      data: undefined,
    };
  }
}

vi.mock(import('@nextcloud/axios'), async (originalImport) => {
  const originalModule = await originalImport();
  originalModule.default.get = get as typeof originalModule['default']['get'];
  return originalModule;
});

beforeAll(async () => {
  await generateEntities();
  entityIdentifiers.Project.id = +Object.keys(entities.Project)[0];
  entityIdentifiers.Musician.id = +Object.keys(entities.Musician)[0];
  entityIdentifiers.ProjectParticipant.project = entityIdentifiers.Project.id;
  entityIdentifiers.ProjectParticipant.musician = entityIdentifiers.Musician.id;
});
