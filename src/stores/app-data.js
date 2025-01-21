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
  const pushBusyState = () => { ++busyCount.value; state.info('BUSY STATE PUSH', busyCount.value); return busyCount.value; };
  const popBusyState = () => { --busyCount.value; state.info('BUSY STATE POP', busyCount.value); return busyCount.value; };
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
  const pendingHistoryData = ref(null);
  const pendingHistoryAction = ref(null);
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
   * @param {string} action One of 'push', 'replace', 'pop'. 'pop'
     will leave the 'post' property untouched, replace will replace
     the 'post' property.
   *
   * @param {object|undefined} post TBD
   */
  function scheduleHistoryAction(action, post) {
    pendingHistoryAction.value = action;
    pendingHistoryData.value = post || {};
    state.trace('scheduleHistoryAction()', action, post, routerHistory.value);
  }

  function scheduleHistoryPush(post) {
    scheduleHistoryAction('push', post);
  }

  function scheduleHistoryReplace(post) {
    scheduleHistoryAction('replace', post);
  }

  function cancelHistoryAction() {
    pendingHistoryAction.value = null;
    pendingHistoryData.value = null;
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
    state.info('ON FINISH', key, typeof key, { ...history }, history?.['' + key], [...Object.keys(history)]);

    // Guard against router-links as their replace/push calls are not
    // interceptable. The following check will fail if the first
    // navigation is initiated by a router-link in replace mode as
    // unfortunately history.state.key is undefined until after the
    // first navigation.
    if (!pendingHistoryAction.value) {
      if (key === currentHistoryIndex.value) {
        // replace action from RouterLink
        pendingHistoryAction.value = 'replace';
      } else if (history[key]) {
        // 'pop' action, back or forward
        pendingHistoryAction.value = 'pop';
      } else {
        // assume 'push'
        pendingHistoryAction.value = 'push';
      }
      state.info('TWEAKED HISTORY ACTION IS', pendingHistoryAction.value, key, history?.[key], { ...history });
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
        history[key] = history[currentHistoryIndex.value];
        delete history[currentHistoryIndex.value];
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
      }
      history[key].post = pendingHistoryData.value || {};
    } else {
      currentHistoryIndex.value = window.history?.state?.key || 'initial';
    }
    pendingHistoryData.value = null;
    pendingHistoryAction.value = null;
    state.info('finishHistoryAction()', currentHistoryIndex.value, currentHistoryState.value, { ...routerHistory.value }, window?.history?.state);
  }

  return {
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
    projects,
  };
});
