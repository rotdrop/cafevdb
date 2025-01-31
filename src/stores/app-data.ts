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

import { defineStore } from 'pinia';
import { set as vueSet, del as vueDelete, ref, computed, watch } from 'vue';
import { computedAsync } from '@vueuse/core';
import axios from '@nextcloud/axios';
import generateAppUrl from '../toolkit/util/generate-url.js';
import type { AxiosResponse } from 'axios';
import Console from '../util/console.ts';

const storeId = 'app-data';
const console = new Console(storeId);

const abortController = new AbortController();

export class HistorySetupError extends Error {}

type ErrorHandler = <E extends Error>(error: E|any, context: object) => void;

export const HistoryActionPush = 'push';
export const HistoryActionPop = 'pop';
export const HistoryActionReplace = 'replace';
export type HistoryAction = typeof HistoryActionPop|typeof HistoryActionPush|typeof HistoryActionReplace;

export interface RouterHistoryState {
  next: string|null,
  prev: string|null,
  key: string,
  post: Record<string, any>,
  position: number|null,
}

export type ProjectTypeTemporary = 'temporary';
export type ProjectTypePermanent = 'permanent';
export type ProjectTypeTemplate = 'template';
export type ProjectTypeInvalid = ''|null|undefined;
export type ProjectTemporalType = ProjectTypeTemporary|ProjectTypePermanent|ProjectTypeTemplate|ProjectTypeInvalid;

interface ProjectFolders {
  projectsfolder: string,
  projectparticipantsfolder: string,
  projectpostersfolder: string,
  projectpublicdownloadsfolder: string,
  balancesfolder: string,
}

export interface Project {
  id: number,
  name: string,
  year: number,
  folders?: ProjectFolders,
  wikiPage: string,
  type: ProjectTemporalType,
}

const usePrivateState = defineStore(storeId + '-private', {
  state: () => ({
    projects: {} as Record<number, Project>,
    loadingPromise: Promise.resolve(true) as Promise<any>,
  }),
  actions: {
    handleError<E extends Error>(error: E|any, context: object, errorHandler: ErrorHandler|null) {
      console.error(context, error);
      if (typeof errorHandler === 'function') {
        errorHandler(error, context);
      }
    },
    abort() {
      abortController.abort();
    },
    async awaitLoadingPromise() {
      let promise: Promise<any>;
      do {
        await (promise = this.loadingPromise);
      } while (promise !== this.loadingPromise);
    },
    async getProject(projectId: number, errorHandler: ErrorHandler|null): Promise<undefined|Project> {
      await this.awaitLoadingPromise();
      if (!this.projects[projectId]) {
        await (this.loadingPromise = this.findProject(projectId, errorHandler));
      }
      return this.projects?.[projectId] || undefined;
    },
    async putProject(project: Project, errorHandler: ErrorHandler|null) {
      const projectId = project.id;
      if (this.projects[projectId]) {
        return this.projects[projectId];
      }
      const url = generateAppUrl('projects/{projectId}/folder/all', { projectId });
      vueSet(this.projects, projectId, project);
      try {
        const response: AxiosResponse<ProjectFolders> = await axios.get(url, { signal: abortController.signal });
        console.info('FETCH PROJECT FOLDERS RESPONSE', response);
        vueSet(this.projects[projectId], 'folders', response.data);
        return this.projects[projectId];
      } catch (e) {
        this.deleteProject(projectId);
        this.handleError(e, { action: 'findProject', projectId, url }, errorHandler);
        return undefined;
      }
    },
    async findProject(projectId: number, errorHandler: ErrorHandler|null) {
      let url = generateAppUrl('projects/{projectId}', { projectId });
      try {
        const response: AxiosResponse<Project> = await axios.get(url, { signal: abortController.signal });
        console.info('FIND PROJECT RESPONSE', response);
        const project = await this.putProject(response.data, errorHandler);
        return project;
      } catch (e) {
        this.handleError(e, { action: 'findProject', projectId, url }, errorHandler);
        return undefined;
      }
    },
    async findProjectIds(errorHandler: ErrorHandler|null) {
      let url = generateAppUrl('projects');
      try {
        const response: AxiosResponse<number[]> = await axios.get(url, { signal: abortController.signal });
        console.info('FIND PROJECT IDS RESPONSE', response);
        return response.data;
      } catch (e) {
        this.handleError(e, { action: 'findProjectIds', url }, errorHandler);
        return undefined;
      }
    },
    async searchProjects(query: string, errorHandler: ErrorHandler|null) {
      query = encodeURI(query);
      if (query !== '') {
        query = '/' + query;
      }
      let promise: Promise<any>;
      do {
        await (promise = this.loadingPromise);
      } while (promise !== this.loadingPromise);
      try {
        const response: AxiosResponse<Project[]> = await axios.get(generateAppUrl(`projects/search${query}`), {
          params: { limit: 10 },
        })
        if (response.data.length > 0) {
          const promises = [] as Promise<undefined|Project>[];
          for (const project of response.data) {
            promises.push(this.putProject(project, errorHandler));
          }
          const projects = await Promise.allSettled(promises);
          return projects.filter(result => result.status === 'fulfilled').map(result => result.value as Project);
        }
        return response.data;
      } catch (e) {
        this.handleError(e, { action: 'searchProjects', query }, errorHandler);
      }
    },
    deleteProject(projectId: number) {
      vueDelete(this.projects, projectId);
    },
  },
});

export default defineStore(storeId, () => {
  const state = usePrivateState();

  const debugMode = ref(0);
  const appError = ref(false);
  const busyCount = ref(0);
  const busyFlag = ref(false);
  const busyState = computed(() => busyCount.value > 0 || busyFlag.value);
  const setBusyFlag = (value: boolean) => { const oldValue = busyFlag.value; busyFlag.value = value; return oldValue; };
  const pushBusyState = () => { ++busyCount.value; console.info('BUSY STATE PUSH', busyCount.value); return busyCount.value; };
  const popBusyState = () => { --busyCount.value; console[(busyCount.value < 0) ? 'trace' : 'info']('BUSY STATE POP', busyCount.value); return busyCount.value; };
  const currentProjectId = ref(0);
  const errorHandler = ref(null);
  const evaluating = ref(false);
  const projectMode = computed(() => currentProjectId.value > 0);
  const currentProject = computedAsync(
    async (/* onCancel */) => {
      if (!projectMode.value) {
        return null;
      }
      // onCancel(() => state.abort());
      const project = await state.getProject(currentProjectId.value, errorHandler.value);
      console.debug('CURRENT PROJECT', project);
      return project;
    },
    null,
    { lazy: true, evaluating },
  );
  const currentProjectName = computed(() => currentProject.value?.name || '');

  const projectIds = ref<number[]>([]);
  state.findProjectIds(errorHandler.value)
    .then((value) => { if (value) { projectIds.value = value; } })
    .catch((error) => { projectIds.value = []; console.error('Fetching the project ids failed', error); });
  const projects = computed(() => state.projects);
  watch(projects, (value, oldValue) => console.info('PROJECTS WATCHER', value, oldValue));

  async function getProject(projectId: number, handler?: ErrorHandler) {
    const result = await state.getProject(projectId, handler || errorHandler.value);
    if (result && !(projectId in projectIds.value)) {
      projectIds.value!.push(projectId);
    }
    return result;
  }

  async function searchProjects(query: string, handler?: ErrorHandler) {
    const result = await state.searchProjects(query, handler || errorHandler.value) || [];
    for (const project of result) {
      if (!(project.id in projectIds.value)) {
        projectIds.value!.push(project.id);
      }
    }
  }

  return {
    busyFlag,
    setBusyFlag,
    debugMode,
    appError,
    busyCount,
    busyState,
    pushBusyState,
    popBusyState,
    currentProject,
    currentProjectId,
    currentProjectName,
    projectMode,
    errorHandler,
    getProject,
    searchProjects,
    projects,
  };
});
