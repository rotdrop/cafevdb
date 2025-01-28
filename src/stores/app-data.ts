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
import type { AxiosResponse } from 'axios';

const storeId = 'app-data';
const abortController = new AbortController();

export class ReadOnlyProxyWriteError extends Error {}
type ErrorHandler = <E extends Error>(error: E|any, context: object) => void;

export const HistoryActionPush = 'push';
export const HistoryActionPop = 'pop';
export const HistoryActionReplace = 'replace';
export type HistoryAction = typeof HistoryActionPop|typeof HistoryActionPush|typeof HistoryActionReplace;

export type ProjectTypeTemporary = 'temporary';
export type ProjectTypePermanent = 'permanent';
export type ProjectTypeTemplate = 'template';
export type ProjectTemporalType = ProjectTypeTemporary|ProjectTypePermanent|ProjectTypeTemplate;

export interface Project {
  id: number,
  name: string,
  year: number,
  folders?: {
    projectsfolder: string,
  },
  wikiPage: string,
  type: ProjectTemporalType,
}

const usePrivateState = defineStore(storeId + '-private', {
  state: () => ({
    projects: {} as Record<number, Project>,
    loadingPromise: Promise.resolve(true) as Promise<any>,
  }),
  actions: {
    debug(...args: any[]) {
      console.debug(storeId, ...args);
    },
    info(...args: any[]) {
      console.info(storeId, ...args);
    },
    error(...args: any[]) {
      console.error(storeId, ...args);
    },
    trace(...args: any[]) {
      console.trace(storeId, ...args);
    },
    handleError<E extends Error>(error: E|any, context: object, errorHandler: ErrorHandler|null) {
      this.error(context, error);
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
        const response = await axios.get(url, { signal: abortController.signal });
        this.info('FETCH PROJECT FOLDERS RESPONSE', response);
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
        const response = await axios.get(url, { signal: abortController.signal });
        this.info('FIND PROJECT RESPONSE', response);
        const project = await this.putProject(response.data, errorHandler);
        return project;
      } catch (e) {
        this.handleError(e, { action: 'findProject', projectId, url }, errorHandler);
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
          await Promise.allSettled(promises);
        }
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
  const pushBusyState = () => { ++busyCount.value; state.info('BUSY STATE PUSH', busyCount.value); return busyCount.value; };
  const popBusyState = () => { --busyCount.value; state[(busyCount.value < 0) ? 'trace' : 'info']('BUSY STATE POP', busyCount.value); return busyCount.value; };
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
      state.debug('CURRENT PROJECT', project);
      return project;
    },
    null,
    { lazy: true, evaluating },
  );
  const currentProjectName = computed(() => currentProject.value?.name);

  async function getProject(projectId: number, handler?: ErrorHandler) {
    return await state.getProject(projectId, handler || errorHandler.value);
  }
  async function searchProjects(query: string, handler?: ErrorHandler) {
    return await state.searchProjects(query, handler || errorHandler.value);
  }

  function validateProjectId(name: PropertyKey) {
    try {
      name = '' + (name as string);
    } catch (e) {
      state.error('Property is not stringable', name);
      return undefined;
    }
    const projectId = parseInt(name);
    if (projectId !== +name) {
      return undefined;
    }
    return projectId;
  };

  const projects = ref(new Proxy(state.projects, {
    async get(target, name: PropertyKey, receiver) {
      state.debug('PROXY GET', target, name, receiver);
      if (!Reflect.has(target, name)) {
        const projectId = validateProjectId(name);
        if (!projectId) {
          return undefined;
        }
        state.debug('Fetching project with id ', name);
        const project = await state.getProject(projectId, errorHandler.value);
        return Reflect.set(target, name, project, receiver);
      }
      return Reflect.get(target, name, receiver);
    },
    set(_target, key: PropertyKey) {
      throw new ReadOnlyProxyWriteError('"projects" property is read-only, cannot assign to "' + key.toString() + '".');
    },
    deleteProperty(target, name) {
      if (!Reflect.has(target, name)) {
        return true;
      }
      const projectId = validateProjectId(name);
      if (!projectId) {
        return false;
      }
      state.deleteProject(projectId);
      return true;
    },
  }));

  const routerHistory = ref({
    initial: {
      prev: null,
      next: null,
      post: {},
      key: 'initial',
      position: window?.history?.length,
    },
  });
  const currentHistoryIndex = ref('initial');
  const pendingHistoryData = ref<null|object>(null);
  const pendingHistoryAction = ref<null|HistoryAction>(null);
  const pendingHistoryKey = ref<null|string|number>('initial');
  const currentHistoryState = computed(() => routerHistory.value?.[currentHistoryIndex.value] || null);
  const prevHistoryIndex = computed(() => currentHistoryState.value.prev);
  const nextHistoryIndex = computed(() => currentHistoryState.value.next);
  const prevHistoryState = computed(() => routerHistory.value?.[prevHistoryIndex.value] || null);
  const nextHistoryState = computed(() => routerHistory.value?.[nextHistoryIndex.value] || null);

  /**
   * This is called before routing in order to record that a
   * history-state action will be initiated. After completion the
   * provided data will be install at the proper position in the
   * history stack.
   *
   * @param action One of 'push', 'replace', 'pop'. 'pop'
     will leave the 'post' property untouched, replace will replace
     the 'post' property.
   *
   * @param post TBD
   */
  function scheduleHistoryAction(action: HistoryAction, post: object) {
    const key = window?.history?.state?.key || 'initial';
    pendingHistoryAction.value = action;
    pendingHistoryData.value = post || {};
    pendingHistoryKey.value = key;
    state.info('scheduleHistoryAction()', {
      action,
      key,
      currentHistoryIndex: currentHistoryIndex.value,
      pendingHistoryKey: pendingHistoryKey.value,
      post,
      routerHistory: routerHistory.value,
    });
    if (currentHistoryIndex.value !== 'initial' && pendingHistoryKey.value !== currentHistoryIndex.value) {
      state.trace('SCHEDULE HISTORY KEY MISTMATCH', pendingHistoryKey.value, currentHistoryIndex.value);
    }
  }

  function scheduleHistoryPush(post: object) {
    scheduleHistoryAction('push', post);
  }

  function scheduleHistoryReplace(post: object) {
    scheduleHistoryAction('replace', post);
  }

  function cancelHistoryAction() {
    pendingHistoryAction.value = null;
    pendingHistoryData.value = null;
    pendingHistoryKey.value = null;
    state.info('cancelHistoryAction()', routerHistory.value);
  }

  /**
   * Called after route completion. Unfortunately the RouterLink Vue
   * component does not provide means to propagate the kind of
   * history-state action -- push or replace -- to the available
   * callback handlers. Hence the logic is:
   *
   * - if pendingHistoryAction is defined, use its value else look at
   * - window.history.state.key,if defined and equal to the current *
   *   (i.e. previous) key, then assume that the history state has
   *   been replaced, otherwise assume a push.
   */
  function finishHistoryAction() {
    const key = window?.history?.state?.key || 'initial';
    const history = routerHistory.value;
    state.info('ON HISTORY FINISH', {
      key,
      keyType: typeof key,
      currentHistoryIndex: currentHistoryIndex.value,
      pendingHistoryKey: pendingHistoryKey.value,
      currentHistoryState: { ...currentHistoryState.value },
      history: { ...history },
      historyOfKey: history?.['' + key],
      historyKeys: [...Object.keys(history)],
    });

    // Guard against router-links as their replace/push calls are not
    // interceptable. The following check will fail if the first
    // navigation is initiated by a router-link in replace mode as
    // unfortunately history.state.key is undefined until after the
    // first navigation.
    if (pendingHistoryAction.value === 'replace' && key !== currentHistoryIndex.value && currentHistoryIndex.value !== 'initial') {
      state.trace('EXPLICIT HISTORY REPLACE REQUESTED, BUT CURRENT HISTORY IS GONE', {
        key,
        pendingHistoryKey: pendingHistoryKey.value,
        currentHistoryIndex: currentHistoryIndex.value,
        history: { ...history },
      });
      pendingHistoryAction.value = null;
      pendingHistoryData.value = null;
      pendingHistoryKey.value = null;
    }
    if (!pendingHistoryAction.value) {
      if (key === pendingHistoryKey.value) {
        // replace action from RouterLink
        pendingHistoryAction.value = 'replace';
      } else if (history[key]) {
        // 'pop' action, back or forward
        pendingHistoryAction.value = 'pop';
      } else {
        // assume 'push'
        pendingHistoryAction.value = 'push';
      }
      state.info('TWEAKED HISTORY ACTION IS', {
        pendingHistoryAction: pendingHistoryAction.value,
        key,
        pendingHistoryKey: pendingHistoryKey.value,
        currentHistoryIndex: currentHistoryIndex.value,
        historyAtKey: history?.[key],
        history: { ...history },
      });
    }

    if (pendingHistoryAction.value === 'push') {
      const key = window.history.state.key;
      history[key] = {
        prev: currentHistoryIndex.value,
        next: null,
        post: pendingHistoryData.value || {},
        key,
        position: window.history.length,
      };
      history[currentHistoryIndex.value].next = key;
      currentHistoryIndex.value = key;
    } else if (pendingHistoryAction.value === 'replace') {
      if (key !== currentHistoryIndex.value) {
        state.info('BEFORE ADJUST KEYS', key, currentHistoryIndex.value, { ...history[currentHistoryIndex.value] }, history?.[key]);
        history[key] = history[currentHistoryIndex.value];
        console.info('CURRENT STATE 0', { ...history[key] });
        delete history[currentHistoryIndex.value];
        console.info('CURRENT STATE 1', { ...history[key] });
        currentHistoryIndex.value = key;
        history[key].key = key;
        const prev = history[key].prev;
        const next = history[key].next;
        if (history[prev]) {
          history[prev].next = key;
        }
        if (history[next]) {
          history[next].prev = key;
        }
        state.info('AFTER ADJUST KEYS', history);
      }
      history[key].post = pendingHistoryData.value || {};
    } else {
      currentHistoryIndex.value = window.history?.state?.key || 'initial';
    }
    for (const [key, record] of Object.entries(routerHistory.value)) {
      if (key !== record.key) {
        state.trace('SELF INCONSISTENCY', key, record, routerHistory.value);
      }
      if ((record.next || record.prev)
        && (record.next === record.prev || record.next === record.key || record.prev === record.key)) {
        state.trace('EQUAL KEYS', key, { ...record }, { ...routerHistory.value });
      }
    }
    pendingHistoryData.value = null;
    pendingHistoryAction.value = null;
    state.info('finishHistoryAction()', {
      currentHistoryIndex: currentHistoryIndex.value,
      currentHistoryState: { ...currentHistoryState.value },
      routerHistory: { ...routerHistory.value },
      windowHistoryState: window?.history?.state,
    });
  }

  return {
    busyFlag,
    setBusyFlag,
    routerHistory,
    currentHistoryIndex,
    currentHistoryState,
    pendingHistoryAction,
    prevHistoryIndex,
    prevHistoryState,
    nextHistoryIndex,
    nextHistoryState,
    scheduleHistoryPush,
    scheduleHistoryReplace,
    cancelHistoryAction,
    finishHistoryAction,
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
