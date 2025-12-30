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

// mock-defining imports must come first
import '../services/mock-axios-entity-repository-controller.ts';
import { entities } from '../services/entity-repository-setup.ts';
import { setSilent as setLoggerSilent } from '../toolkit/util/mock-console.ts';
// normal imports
import { beforeEach, describe, it, expect } from '@jest/globals';
import { createPinia, setActivePinia } from 'pinia';
import useAppDataStore, { type Project } from '@/src/stores/app-data.ts';

setLoggerSilent(true);

const projectKeys = [
  'created',
  'deleted',
  'eventMatrix',
  'financialBalanceDocumentsStorage',
  'folders',
  'getEventMatrix',
  'getFolders',
  'id',
  'mailingListId',
  'name',
  'registrationDeadline',
  'registrationStartDate',
  'type',
  'updated',
  'wikiPage',
  'year',
];

const magicKeys = {
  __ob__: 'OB VALUE',
  // eslint-disable-next-line camelcase
  __v_skip: 'V SKIP VALUE',
};

describe('app-data store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('should be able to create the store', () => {
    useAppDataStore();
  });

  it('should search projects', async () => {
    const appData = useAppDataStore();
    const query = 'Test';
    const result = await appData.searchProjects(query);
    expect(result).toBeInstanceOf(Array);
    expect(result?.length).toEqual(1);
  });

  it('project proxy should be enumerable', async () => {
    const appData = useAppDataStore();
    const query = 'Test';
    const result = await appData.searchProjects(query);
    const project = result![0];
    const keys = Object.keys(project);
    expect(keys.sort()).toEqual(projectKeys);
    const forInKeys: string[] = [];
    for (const key in project) {
      forInKeys.push(key);
    }
    expect(forInKeys.sort()).toEqual(projectKeys);
  });

  it('project proxy should allow magic keys to be set', async () => {
    const appData = useAppDataStore();
    const query = 'Test';
    const result = await appData.searchProjects(query);
    const project = result![0];
    for (const [key, value] of Object.entries(magicKeys)) {
      project[key] = value;
      expect(project[key]).toEqual(value);
    }
  });

  it('should get a project by id or name', async () => {
    const appData = useAppDataStore();
    const project = await appData.getProject(1);
    expect(project).toBeDefined();
    const projectEntity = entities.Project['1'];
    for (const key of Object.keys(projectEntity)) {
      expect(project[key]).toEqual(projectEntity[key]);
    }
  });

  it('current project should work', async () => {
    const appData = useAppDataStore();
    const query = 'Test';
    const result = await appData.searchProjects(query);
    const project = result![0];
    expect(appData.currentProject).toBeUndefined();
    expect(appData.currentProjectId).toEqual(0);
    expect(appData.currentProjectName).toEqual('');
    expect(appData.projectMode).toBeFalsy();

    let setResult: Project;
    setResult = await appData.setCurrentProject(project.id);
    expect(setResult).toEqual(project);
    expect(appData.currentProject).toEqual(project);
    expect(appData.currentProjectId).toEqual(project.id);
    expect(appData.currentProjectName).toEqual(project.name);
    expect(appData.projectMode).toBeTruthy();

    // reset and test with name
    setResult = await appData.setCurrentProject();
    expect(setResult).toBeUndefined();
    expect(appData.currentProject).toBeUndefined();
    expect(appData.currentProjectId).toEqual(0);
    expect(appData.currentProjectName).toEqual('');
    expect(appData.projectMode).toBeFalsy();

    setResult = await appData.setCurrentProject(project.name);
    expect(setResult).toEqual(project);
    expect(appData.currentProject).toEqual(project);
    expect(appData.currentProjectId).toEqual(project.id);
    expect(appData.currentProjectName).toEqual(project.name);
    expect(appData.projectMode).toBeTruthy();

    // set again should not return a promise any more
    setResult = appData.setCurrentProject(project.name);
    expect(setResult instanceof Promise).toBeFalsy();
  });

  it('should handle the busy flag', () => {
    const appData = useAppDataStore();
    expect(appData.busyFlag).toBeFalsy();
    for (const value of [true, false]) {
      appData.setBusyFlag(value);
      expect(appData.busyFlag).toEqual(value);
      expect(appData.busyState).toEqual(value);
    }
    const busyLimit = 3;
    for (let i = 0; i < busyLimit; ++i) {
      appData.pushBusyState();
      const expectedCount = i + 1;
      expect(appData.busyCount).toEqual(expectedCount);
      expect(appData.busyState).toEqual(expectedCount > 0);
      expect(appData.busyFlag).toEqual(false);
    }
    for (let i = 0; i < busyLimit + 1; ++i) {
      appData.popBusyState();
      const expectedCount = busyLimit - i - 1;
      expect(appData.busyCount).toEqual(expectedCount);
      expect(appData.busyState).toEqual(expectedCount > 0);
      expect(appData.busyFlag).toEqual(false);
    }
  });
});
