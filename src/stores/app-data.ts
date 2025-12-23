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
import {
  set as vueSet,
  // del as vueDelete,
  reactive,
  ref,
  computed,
  watch,
} from 'vue';
import axios from '@nextcloud/axios';
import generateAppUrl from '../toolkit/util/generate-url.ts';
import type { AxiosResponse } from 'axios';
import {
  PUSH_BUSY_STATE,
  POP_BUSY_STATE,
  SET_BUSY_FLAG,
} from '../event-bus-events.ts';
import { subscribe as asyncSubscribe } from '../services/async-event-bus.ts';
import Console from '../util/console.ts';
import { AppError } from '../types/errors.ts';
import type { ErrorContext, ErrorHandler } from '../types/errors.ts';
import { appName } from '../config.ts';
import { translate as t } from '@nextcloud/l10n';
import useErrorHandler from './error-handler.ts';
import type { AnyPromise } from '../types/promise.d.ts';
import type { EventMatrixEvent } from '../../build/ts-types/php-modules/Service/DTO.ts';

export { type EventMatrixEvent };

const storeId = 'app-data';
const logger = new Console(storeId);

export class AppDataStoreError extends AppError {

  constructor(
    context: ErrorContext,
    ...p: ConstructorParameters<ErrorConstructor>
  ) {
    super({ ...context, type: storeId, component: storeId + '-store' }, ...p);
  }

}

const abortController = new AbortController();

export type ProjectTypeTemporary = 'temporary';
export type ProjectTypePermanent = 'permanent';
export type ProjectTypeTemplate = 'template';
export type ProjectTypeInvalid = '' | null | undefined;
export type ProjectTemporalType =
  | ProjectTypeTemporary
  | ProjectTypePermanent
  | ProjectTypeTemplate
  | ProjectTypeInvalid;

interface ProjectFolders {
  projectsfolder: string;
  projectparticipantsfolder: string;
  projectpostersfolder: string;
  projectpublicdownloadsfolder: string;
  balancesfolder: string;
}

interface ProjectEventEntity {
  id: number;
  projectId: number;
  calendarId: number;
  calendarUri: string;
  eventUid: string;
  seriesUid: null | string;
  eventUri: string;
  recurrenceId: number;
  sequence: number;
  type: 'VEVENT' | 'VTODO' | 'VJOURNAL' | 'VCARD';
  absenceFieldId: null | number;
}

export type CalendarUris =
  | 'concerts'
  | 'rehearsals'
  | 'other'
  | 'management'
  | 'finance';

export interface EventMatrixEntry {
  name: string; // displayName
  uri: CalendarUris | '';
  calendarId: number;
  urlPath: string; // local url-path
  events: EventMatrixEvent[];
}

export type ProjectEventMatrix = Record<number, EventMatrixEntry>;

export interface Project {
  id: number;
  name: string;
  year: number;
  wikiPage: string;
  type: ProjectTemporalType;
  folders?: ProjectFolders;
  calendarEvents?: ProjectEventEntity[];
  eventMatrix?: ProjectEventMatrix;
  getFolders: (
    errorHandler?: ErrorHandler,
  ) => Promise<undefined | ProjectFolders>;
  getCalendarEvents: (
    errorHandler?: ErrorHandler,
  ) => Promise<undefined | ProjectEventEntity[]>;
  getEventMatrix: (
    errorHandler?: ErrorHandler,
  ) => Promise<undefined | ProjectEventMatrix>;
}

export default defineStore(storeId, () => {
  const errorHandlerProvider = useErrorHandler();
  const loggerRef = ref(logger);

  const busyCount = ref(0);
  const busyFlag = ref(false);
  const busyState = computed(() => busyCount.value > 0 || busyFlag.value);

  const setBusyFlag = (value: boolean) => {
    const oldValue = busyFlag.value;
    busyFlag.value = value;
    return oldValue;
  };
  const pushBusyState = () => {
    ++busyCount.value;
    logger.info('BUSY STATE PUSH', busyCount.value);
    return busyCount.value;
  };
  const popBusyState = () => {
    --busyCount.value;
    logger[busyCount.value < 0 ? 'trace' : 'info'](
      'BUSY STATE POP',
      busyCount.value,
    );
    return busyCount.value;
  };

  // receive updates from the legacy code.
  asyncSubscribe(SET_BUSY_FLAG, ({ value }) => setBusyFlag(value));
  asyncSubscribe(PUSH_BUSY_STATE, () => pushBusyState());
  asyncSubscribe(POP_BUSY_STATE, () => popBusyState());

  const errorHandler = computed(() => errorHandlerProvider.errorHandler);

  /*****************************************************************************
   *
   * THE ACTUAL DATA STORAGE.
   *
   */

  const state = reactive({
    projects: {} as Record<number, Project>,
    projectsByName: {} as Record<string, Project>,
    loadingPromise: Promise.resolve(true) as AnyPromise,
  });

  /*
   *****************************************************************************
   *
   * DATA FETCHING FUNCTIONS.
   *
   */

  const stateHandleError = <E extends Error>(
    error: E | unknown,
    context: ErrorContext,
    errorHandler?: ErrorHandler,
  ) => {
    logger.error(context, error);
    const message =
      typeof context.message === 'string'
        ? context.message
        : t(appName, 'An error occurred in the app-data store.');
    const appError = new AppDataStoreError(context, message, { cause: error });
    if (typeof errorHandler === 'function') {
      errorHandler(appError);
    } else {
      throw appError;
    }
  };

  const stateAwaitLoadingPromise = async () => {
    let promise: AnyPromise;
    do {
      await (promise = state.loadingPromise);
    } while (promise !== state.loadingPromise);
  };
  const stateGetProject = async (
    projectKey: string | number,
    errorHandler?: ErrorHandler,
  ): Promise<undefined | Project> => {
    await stateAwaitLoadingPromise();
    const projectId = parseInt('' + projectKey);
    if (projectId !== +projectKey) {
      const projectName = '' + projectKey;
      if (!state.projectsByName[projectName]) {
        await (state.loadingPromise = stateDoSearchProjects(
          '^' + projectName + '$',
          errorHandler,
        ));
      }
      return state.projectsByName?.[projectName] || undefined;
    } else {
      if (!state.projects[projectId]) {
        await (state.loadingPromise = stateFindProject(
          projectId,
          errorHandler,
        ));
      }
      return state.projects?.[projectId] || undefined;
    }
  };
  const stateGetProjectEvents = async (
    project: Project,
    errorHandler?: ErrorHandler,
  ) => {
    const projectId = project.id;
    const url = generateAppUrl('projects/{projectId}/calendar-events', {
      projectId,
    });
    try {
      const response: AxiosResponse<ProjectEventEntity[]> = await axios.get(
        url,
        { signal: abortController.signal },
      );
      logger.debug('FETCH PROJECT EVENTS RESPONSE', response);
      vueSet(project, 'calendarEvents', response.data);
      return response.data;
    } catch (e) {
      stateHandleError(
        e,
        { action: 'getProjectEvents', projectId, url },
        errorHandler,
      );
      return undefined;
    }
  };
  const stateGetEventMatrix = async (
    project: Project,
    errorHandler?: ErrorHandler,
  ) => {
    const projectId = project.id;
    const url = generateAppUrl('projects/{projectId}/event-matrix', {
      projectId,
    });
    try {
      const response: AxiosResponse<ProjectEventMatrix> = await axios.get(url, {
        signal: abortController.signal,
      });
      logger.debug('FETCH EVENT MATRIX RESPONSE', response);
      vueSet(project, 'eventMatrix', response.data);
      return response.data;
    } catch (e) {
      stateHandleError(
        e,
        { action: 'getEventMatrix', projectId, url },
        errorHandler,
      );
      return undefined;
    }
  };
  const stateGetProjectFolders = async (
    project: Project,
    errorHandler?: ErrorHandler,
  ) => {
    const projectId = project.id;
    const url = generateAppUrl('projects/{projectId}/folder/all', {
      projectId,
    });
    try {
      const response: AxiosResponse<ProjectFolders> = await axios.get(url, {
        signal: abortController.signal,
      });
      logger.debug('FETCH PROJECT FOLDERS RESPONSE', response);
      vueSet(project, 'folders', response.data);
      return response.data;
    } catch (e) {
      stateHandleError(
        e,
        { action: 'getProjectFolders', projectId, url },
        errorHandler,
      );
      return undefined;
    }
  };
  const statePutProject = async (
    project: Project,
    errorHandler?: ErrorHandler,
  ) => {
    const projectId = project.id;
    if (state.projects[projectId]) {
      return state.projects[projectId];
    }
    project.getFolders = (handler?: ErrorHandler) =>
      stateGetProjectFolders(project, handler || errorHandler);
    project.getCalendarEvents = (handler?: ErrorHandler) =>
      stateGetProjectEvents(project, handler || errorHandler);
    project.getEventMatrix = (handler?: ErrorHandler) =>
      stateGetEventMatrix(project, handler || errorHandler);
    vueSet(state.projects, projectId, project);
    vueSet(state.projectsByName, project.name, project);
    return state.projects[projectId];
  };
  const stateFindProject = async (
    projectId: number,
    errorHandler?: ErrorHandler,
  ) => {
    const url = generateAppUrl('projects/{projectId}', { projectId });
    try {
      const response: AxiosResponse<Project> = await axios.get(url, {
        signal: abortController.signal,
      });
      logger.info('FIND PROJECT RESPONSE', response);
      const project = await statePutProject(response.data, errorHandler);
      return project;
    } catch (e) {
      stateHandleError(
        e,
        { action: 'findProject', projectId, url },
        errorHandler,
      );
      return undefined;
    }
  };
  const stateSearchProjects = async (
    query: string,
    errorHandler?: ErrorHandler,
  ) => {
    stateAwaitLoadingPromise();
    const result = await (state.loadingPromise = stateDoSearchProjects(
      query,
      errorHandler,
    ));
    return result;
  };
  const stateDoSearchProjects = async (
    query: string,
    errorHandler?: ErrorHandler,
  ) => {
    query = encodeURI(query);
    if (query !== '') {
      query = '/' + query;
    }
    try {
      const response: AxiosResponse<Project[]> = await axios.get(
        generateAppUrl(`projects/search${query}`),
        {
          params: { limit: 10 },
        },
      );
      if (response.data.length > 0) {
        const promises = [] as Promise<undefined | Project>[];
        for (const project of response.data) {
          promises.push(statePutProject(project, errorHandler));
        }
        const projects = await Promise.allSettled(promises);
        return projects
          .filter((result) => result.status === 'fulfilled')
          .map((result) => result.value as Project);
      }
      return response.data;
    } catch (e) {
      stateHandleError(e, { action: 'searchProjects', query }, errorHandler);
      return undefined;
    }
  };

  /*
   * END OF DATA FETCHING FUNCTIONS.
   *
   ****************************************************************************/

  const projects = computed(() => state.projects);
  watch(projects, (value, oldValue) =>
    logger.info('PROJECTS WATCHER', value, oldValue),
  );

  const getProject = async (
    projectKey: string | number,
    handler?: ErrorHandler,
  ) => {
    const result = await stateGetProject(
      projectKey,
      handler || errorHandlerProvider.getHandler(),
    );
    return result;
  };

  const searchProjects = (query: string, handler?: ErrorHandler) =>
    stateSearchProjects(query, handler || errorHandlerProvider.getHandler());

  const currentProject = ref<undefined | Project>(undefined);
  const projectMode = computed(() => !!currentProject.value);
  const currentProjectId = computed<number>(
    () => currentProject.value?.id || 0,
  );
  const currentProjectName = computed<string>(
    () => currentProject.value?.name || '',
  );

  const setCurrentProject = async (
    projectKey: string | number,
    handler?: ErrorHandler,
  ) => {
    if (projectKey === currentProjectId.value
        || projectKey === currentProjectName.value) {
      return currentProject.value;
    }
    if (projectKey) {
      currentProject.value = await getProject(projectKey, handler);
    } else {
      currentProject.value = undefined;
    }
    return currentProject.value;
  };

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
    setCurrentProject,
    currentProject,
    currentProjectId,
    currentProjectName,
    projectMode,
    getProject,
    searchProjects,
    projects,
  };
});
