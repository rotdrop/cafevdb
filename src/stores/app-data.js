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
import { set as vueSet /* , del as vueDelete */ } from 'vue';
import axios from '@nextcloud/axios';
import generateAppUrl from '../toolkit/util/generate-url.js';

const storeId = 'app-data';

export const useAppDataStore = defineStore(storeId, {
  state: () => ({
    projects: {},
    currentProjectId: -1,
    errorHandler: null,
    loadingPromise: Promise.resolve(true),
  }),
  getters: {
    projectMode(state) {
      return state.projectId > 0;
    },
    currentProject(state) {
      if (!this.projectMode) {
        return null;
      }
      return this.getProject(state.projectId, state.errorHandler);
    },
    currentProjectName(state) {
      if (!this.projectMode) {
        return null;
      }
      const project = this.currentProject(state);
      return project?.entity?.name;
    },
  },
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
    setErrorHandler(errorHandler) {
      const old = this.errorHandler;
      this.errorHandler = errorHandler;
      return old;
    },
    async getProject(id, errorHandler) {
      let promise;
      do {
        await (promise = this.loadingPromise);
      } while (promise !== this.loadingPromise);

      if (!this.projects[id]) {
        await (this.loadingPromise = this.findProject(id, errorHandler));
      }
      return this.projects[id];
    },
    async findProject(projectId, errorHandler) {
      let url = generateAppUrl('projects/{projectId}', { projectId });
      try {
        const response = await axios.get(url);
        this.info('FIND PROJECT RESPONSE', response);
        this.projects[projectId] = {};
        vueSet(this.projects[projectId], 'entity', response.data);
        url = generateAppUrl('projects/{projectId}/folder/all', { projectId });
        try {
          const response = await axios.get(url);
          this.info('FETCH PROJECT FOLDERS RESPONSE', response);
          vueSet(this.projects[projectId], 'folders', response.data);
        } catch (e) {
          this.handleError(e, { action: 'findProject', projectId, url }, errorHandler);
        }
      } catch (e) {
        this.handleError(e, { action: 'findProject', projectId, url }, errorHandler);
      }
    },
  },
});
