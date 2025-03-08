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
import { PUSH_BUSY_STATE, POP_BUSY_STATE, SET_BUSY_FLAG } from '../event-bus-events.ts';
import { subscribe as asyncSubscribe } from '../services/async-event-bus.ts'
import Console from '../util/console.ts';
import { AppError } from '../types/errors.ts';
import type { ErrorContext, ErrorHandler } from '../types/errors.ts';
import { appName } from '../config.ts';
import { translate as t } from '@nextcloud/l10n';
import useErrorHandler from './error-handler.ts';

const storeId = 'app-data';
const logger = new Console(storeId);

export class AppDataStoreError extends AppError {
  constructor(context: ErrorContext, ...p: ConstructorParameters<ErrorConstructor>) {
    super({ ...context, type: storeId, component: storeId + '-store' }, ...p);
  }
};

const abortController = new AbortController();

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

interface ProjectEventEntity {
  id: number,
  projectId: number,
  calendarId: number,
  calendarUri: string,
  eventUid: string,
  seriesUid: null|string,
  eventUri: string,
  recurrenceId: number,
  sequence: number,
  type: 'VEVENT'|'VTODO'|'VJOURNAL'|'VCARD',
  absenceFieldId: null|number,
}

// Date from PHP
interface SerializedPHPDate {
  date: string, // e.g 2014-03-29 11:30:00.000000
  timezone_type: number, // e.g. 3
  timezone: string, // e.g. UTC
}

interface EventTimes {
  timezone: string,
  locale: string,
  allday: boolean,
  start: {
    stamp: number, // timestamp
    date: string, // short date string
    time: string,
  },
  end: {
    stamp: number,
    date: string, // end date at last day of allday events
    time: string, // time with 00:00 -> 24:00
  },
}

export interface EventMatrixEvent {
  instanceId: string,
  projectId: number,
  deleted?: string|null,
  uri: string,
  uid: string,
  calendarId: number,
  calendarUri: string,
  sequence: number,
  recurrenceId: number,
  seriesUid: string,
  absenceField: number|null|undefined,
  allday: boolean,
  summary: string,
  description: string,
  location: string,
  categories: string[],
  urlPath: string,
  start: SerializedPHPDate,
  end: SerializedPHPDate,
  seriesStart: SerializedPHPDate,
  times: EventTimes,
}

export type CalendarUris = 'concerts'|'rehearsals'|'other'|'management'|'finance';

export interface EventMatrixEntry {
  name: string, // displayName
  uri: CalendarUris|'',
  calendarId: number,
  urlPath: string, // local url-path
  events: EventMatrixEvent[],
}

export interface SpecialCategories {
  recordAbsence: string,
  projectRegistration: string,
}

export interface ProjectEventMatrix {
  calendars: {
    [Key in CalendarUris]: {
      uri: Key,
      public: boolean,
    };
  },
  categories: {
    // some meta data ...
    C: SpecialCategories,
    L10N: SpecialCategories,
  },
  matrix: {
    [key: number]: EventMatrixEntry,
  }
}

export interface Project {
  id: number,
  name: string,
  year: number,
  wikiPage: string,
  type: ProjectTemporalType,
  folders?: ProjectFolders,
  calendarEvents?: ProjectEventEntity[],
  eventMatrix?: ProjectEventMatrix,
  getFolders: (errorHandler?: ErrorHandler) => Promise<undefined|ProjectFolders>,
  getCalendarEvents: (errorHandler?: ErrorHandler) => Promise<undefined|ProjectEventEntity[]>,
  getEventMatrix: (errorHandler?: ErrorHandler) => Promise<undefined|ProjectEventMatrix>,
}

const usePrivateState = defineStore(storeId + '-private', {
  state: () => ({
    projects: {} as Record<number, Project>,
    loadingPromise: Promise.resolve(true) as Promise<any>,
  }),
  actions: {
    handleError<E extends Error>(error: E|any, context: ErrorContext, errorHandler?: ErrorHandler) {
      logger.error(context, error);
      const message = typeof context.message === 'string'
        ? context.message : t(appName, 'An error occurred in the app-data store.')
      error = new AppDataStoreError(context, message, { cause: error });
      if (typeof errorHandler === 'function') {
        errorHandler(error);
      } else {
        throw error;
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
    async getProject(projectId: number, errorHandler?: ErrorHandler): Promise<undefined|Project> {
      await this.awaitLoadingPromise();
      if (!this.projects[projectId]) {
        await (this.loadingPromise = this.findProject(projectId, errorHandler));
      }
      return this.projects?.[projectId] || undefined;
    },
    async getProjectEvents(project: Project, errorHandler?: ErrorHandler) {
      const projectId = project.id;
      const url = generateAppUrl('projects/{projectId}/calendar-events', { projectId });
      try {
        const response: AxiosResponse<ProjectEventEntity[]> = await axios.get(url, { signal: abortController.signal });
        logger.debug('FETCH PROJECT EVENTS RESPONSE', response);
        vueSet(project, 'calendarEvents', response.data);
        return response.data;
      } catch (e) {
        this.handleError(e, { action: 'getProjectEvents', projectId, url }, errorHandler);
        return undefined;
      }
    },
    async getEventMatrix(project: Project, errorHandler?: ErrorHandler) {
      const projectId = project.id;
      const url = generateAppUrl('projects/{projectId}/event-matrix', { projectId });
      try {
        const response: AxiosResponse<ProjectEventMatrix> = await axios.get(url, { signal: abortController.signal });
        logger.debug('FETCH EVENT MATRIX RESPONSE', response);
        vueSet(project, 'eventMatrix', response.data);
        for (const entry of Object.values(project.eventMatrix!.matrix)) {
          for (const event of entry.events) {
            event.instanceId = event.uri + (event.recurrenceId ? '@' + event.recurrenceId : '');
          }
        }
        return response.data;
      } catch (e) {
        this.handleError(e, { action: 'getEventMatrix', projectId, url }, errorHandler);
        return undefined;
      }
    },
    async getProjectFolders(project: Project, errorHandler?: ErrorHandler) {
      const projectId = project.id;
      const url = generateAppUrl('projects/{projectId}/folder/all', { projectId });
      try {
        const response: AxiosResponse<ProjectFolders> = await axios.get(url, { signal: abortController.signal });
        logger.debug('FETCH PROJECT FOLDERS RESPONSE', response);
        vueSet(project, 'folders', response.data);
        return response.data;
      } catch (e) {
        this.handleError(e, { action: 'getProjectFolders', projectId, url }, errorHandler);
        return undefined;
      }
    },
    async putProject(project: Project, errorHandler?: ErrorHandler) {
      const projectId = project.id;
      if (this.projects[projectId]) {
        return this.projects[projectId];
      }
      project.getFolders = (handler?: ErrorHandler) => this.getProjectFolders(project, handler || errorHandler);
      project.getCalendarEvents = (handler?: ErrorHandler) => this.getProjectEvents(project, handler || errorHandler);
      project.getEventMatrix = (handler?: ErrorHandler) => this.getEventMatrix(project, handler || errorHandler);
      vueSet(this.projects, projectId, project);
      return this.projects[projectId];
    },
    async findProject(projectId: number, errorHandler?: ErrorHandler) {
      let url = generateAppUrl('projects/{projectId}', { projectId });
      try {
        const response: AxiosResponse<Project> = await axios.get(url, { signal: abortController.signal });
        logger.info('FIND PROJECT RESPONSE', response);
        const project = await this.putProject(response.data, errorHandler);
        return project;
      } catch (e) {
        this.handleError(e, { action: 'findProject', projectId, url }, errorHandler);
        return undefined;
      }
    },
    async findProjectIds(errorHandler?: ErrorHandler) {
      let url = generateAppUrl('projects');
      try {
        const response: AxiosResponse<number[]> = await axios.get(url, { signal: abortController.signal });
        logger.info('FIND PROJECT IDS RESPONSE', response);
        return response.data;
      } catch (e) {
        this.handleError(e, {
          message: t(appName, 'Unable to fetch the poject-ids from the database.'),
          action: 'findProjectIds',
          url,
        }, errorHandler);
        return undefined;
      }
    },
    async searchProjects(query: string, errorHandler?: ErrorHandler) {
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
        return undefined;
      }
    },
    deleteProject(projectId: number) {
      vueDelete(this.projects, projectId);
    },
  },
});

export default defineStore(storeId, () => {
  const errorHandlerProvider = useErrorHandler();
  const state = usePrivateState();
  const loggerRef = ref(logger);

  const busyCount = ref(0);
  const busyFlag = ref(false);
  const busyState = computed(() => busyCount.value > 0 || busyFlag.value);

  const setBusyFlag = (value: boolean) => { const oldValue = busyFlag.value; busyFlag.value = value; return oldValue; };
  const pushBusyState = () => { ++busyCount.value; logger.info('BUSY STATE PUSH', busyCount.value); return busyCount.value; };
  const popBusyState = () => { --busyCount.value; logger[(busyCount.value < 0) ? 'trace' : 'info']('BUSY STATE POP', busyCount.value); return busyCount.value; };

  // receive updates from the legacy code.
  asyncSubscribe(SET_BUSY_FLAG, ({ value }) => setBusyFlag(value))
  asyncSubscribe(PUSH_BUSY_STATE, () => pushBusyState())
  asyncSubscribe(POP_BUSY_STATE, () => popBusyState())

  const currentProjectId = ref(0);
  const errorHandler = computed(() => errorHandlerProvider.errorHandler);
  const evaluating = ref(false);
  const projectMode = computed(() => currentProjectId.value > 0);
  const currentProject = computedAsync(
    async (/* onCancel */) => {
      if (!projectMode.value) {
        return null;
      }
      // onCancel(() => state.abort());
      const project = await state.getProject(currentProjectId.value, errorHandlerProvider.getHandler());
      logger.debug('CURRENT PROJECT', project);
      return project;
    },
    null,
    { lazy: true, evaluating },
  );
  const currentProjectName = computed(() => currentProject.value?.name || '');

  const projectIds = ref<number[]>([]);
  state.findProjectIds(errorHandlerProvider.getHandler())
    .then((value) => { if (value) { projectIds.value = value; } })
    .catch((error) => { projectIds.value = []; logger.error('Fetching the project ids failed', error, errorHandlerProvider.getHandler(), errorHandlerProvider.errorHandler); });
  const projects = computed(() => state.projects);
  watch(projects, (value, oldValue) => logger.info('PROJECTS WATCHER', value, oldValue));

  async function getProject(projectId: number, handler?: ErrorHandler) {
    const result = await state.getProject(projectId, handler || errorHandlerProvider.getHandler());
    if (result && !(projectId in projectIds.value)) {
      projectIds.value!.push(projectId);
    }
    return result;
  }

  async function searchProjects(query: string, handler?: ErrorHandler) {
    const result = await state.searchProjects(query, handler || errorHandlerProvider.getHandler()) || [];
    for (const project of result) {
      if (!(project.id in projectIds.value)) {
        projectIds.value!.push(project.id);
      }
    }
  }

  return {
    logger: loggerRef,
    errorHandler,
    pushErrorHandler: errorHandlerProvider.pushHandler,
    popErrorHandler: errorHandlerProvider.popHandler,
    busyFlag,
    setBusyFlag,
    busyCount,
    busyState,
    pushBusyState,
    popBusyState,
    currentProject,
    currentProjectId,
    currentProjectName,
    projectMode,
    getProject,
    searchProjects,
    projects,
  };
});
