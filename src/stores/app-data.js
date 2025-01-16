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
import { set as vueSet, del as vueDelete, ref, computed } from 'vue';
import { computedAsync } from '@vueuse/core';
import axios from '@nextcloud/axios';
import generateAppUrl from '../toolkit/util/generate-url.js';

const storeId = 'app-data';
const abortController = new AbortController();

const usePrivateState = defineStore(storeId + '-private', {
  state: () => ({
    projects: {},
    loadingPromise: Promise.resolve(true),
  }),
  actions: {
    debug(...args) {
      console.debug(storeId, ...args);
    },
    info(...args) {
      console.info(storeId, ...args);
    },
    error(...args) {
      console.error(storeId, ...args);
    },
    trace(...args) {
      console.trace(storeId, ...args);
    },
    handleError(error, context, errorHandler) {
      this.error(context, error);
      if (typeof errorHandler === 'function') {
        errorHandler(error, context);
      }
    },
    abort() {
      abortController.abort();
    },
    async getProject(projectId, errorHandler) {
      let promise;
      do {
        await (promise = this.loadingPromise);
      } while (promise !== this.loadingPromise);

      if (!this.projects[projectId]) {
        await (this.loadingPromise = this.findProject(projectId, errorHandler));
      }
      return this.projects?.[projectId];
    },
    async findProject(projectId, errorHandler) {
      let url = generateAppUrl('projects/{projectId}', { projectId });
      try {
        const response = await axios.get(url, { signal: abortController.signal });
        this.info('FIND PROJECT RESPONSE', response);
        vueSet(this.projects, projectId, response.data);
        url = generateAppUrl('projects/{projectId}/folder/all', { projectId });
        try {
          const response = await axios.get(url, { signal: abortController.signal });
          this.info('FETCH PROJECT FOLDERS RESPONSE', response);
          vueSet(this.projects[projectId], 'folders', response.data);
        } catch (e) {
          this.handleError(e, { action: 'findProject', projectId, url }, errorHandler);
        }
      } catch (e) {
        this.handleError(e, { action: 'findProject', projectId, url }, errorHandler);
      }
    },
    deleteProject(projectId) {
      vueDelete(this.projects, projectId);
    },
  },
});

export class ReadOnlyProxyWriteError extends Error {}

export default defineStore(storeId, () => {
  const state = usePrivateState();

  const debugMode = ref(0);
  const appError = ref(false);
  const busyCount = ref(0);
  const busyState = computed(() => busyCount.value > 0);
  const pushBusyState = () => { ++busyCount.value; state.debug('BUSY STATE PUSH', busyCount.value); return busyCount.value; };
  const popBusyState = () => { --busyCount.value; state.debug('BUSY STATE POP', busyCount.value); return busyCount.value; };
  const currentProjectId = ref(-1);
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
      state.debug('CURRENT PROJECT', project);
      return project;
    },
    null,
    { lazy: true, evaluating },
  );
  const currentProjectName = computedAsync(
    async (/* onCancel */) => {
      if (!projectMode.value) {
        return null;
      }
      // onCancel(() => state.abort());
      await currentProject.value;
      state.debug('CURRENT PROJECT', currentProject.value);

      return currentProject.value?.name || null;
    },
    null,
    { lazy: true, evaluating },
  );

  async function getProject(projectId) { return await state.getProject(projectId, errorHandler.value); }

  const projects = ref(new Proxy(state.projects, {
    async get(target, name, receiver) {
      state.debug('PROXY GET', target, name, receiver);
      if (!Reflect.has(target, name)) {
        try {
          name = '' + name;
        } catch (e) {
          state.error('Property is not stringable', name);
          return undefined;
        }
        if (parseInt(name) !== +name) {
          return undefined;
        }
        state.debug('Fetching project with id ', name);
        const project = await state.getProject(name);
        return Reflect.set(target, name, project, receiver);
      }
      return Reflect.get(target, name, receiver);
    },
    set: (target, key) => {
      throw new ReadOnlyProxyWriteError('"projects" property is read-only.');
    },
    deleteProperty: (target, key) => {
      state.deleteProject(key);
    },
  }));

  return {
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
    projects,
  };
});
