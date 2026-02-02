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

import { defineStore } from 'pinia';
import globalState from '../app/globalstate.ts';
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
import { AppError } from '../toolkit/types/errors.ts';
import type { ErrorContext, ErrorHandler } from '../toolkit/types/errors.ts';
import { appName } from '../config.ts';
import { translate as t } from '@nextcloud/l10n';
import useErrorHandler from './error-handler.ts';
import type { AnyPromise } from '../types/promise.d.ts';
import type { EventMatrixEvent, EventMatrixRow } from '../../build/ts-types/php-modules/Service/DTO.ts';
import type { CALENDARS } from '../../build/ts-types/php-modules/Settings/ConfigConstants.ts';
import useDatabaseEntities from './database-entities.ts';
import type { FrontEndEntity } from '../toolkit/services/entity-factory.ts';
import type { EntityReference } from '../../build/ts-types/php-modules/Toolkit/Doctrine/ORM/EntitySerializer.ts';
import { WILDCARD_QUERY_OPTIONS } from '../../build/ts-types/php-modules/Database/Constants.ts';
import type { ProjectFoldersResponse as ProjectFolders } from '../../build/ts-types/php-modules/Controller/DTO.ts';

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

export type CalendarUris = keyof typeof CALENDARS;

export type ProjectEventMatrix = Record<number, EventMatrixRow>;

export interface Project extends FrontEndEntity<'Project'> {
  wikiPage: string,
  folders?: ProjectFolders,
  eventMatrix?: ProjectEventMatrix,
  getFolders: (
    errorHandler?: ErrorHandler,
  ) => Promise<undefined | ProjectFolders>,
  getEventMatrix: (
    errorHandler?: ErrorHandler,
  ) => Promise<undefined | ProjectEventMatrix>,
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

  const databaseEntities = useDatabaseEntities();

  /*****************************************************************************
   *
   * THE ACTUAL DATA STORAGE.
   *
   */

  const state = reactive({
    projects: {} as Record<number, Project>,
    projectsByName: {} as Record<string, Project>,
    projectFolders: {} as Record<number, ProjectFolders>,
    projectEvents: {} as Record<number, ProjectEventMatrix>,
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
  const stateGetEventMatrix = async (
    projectId: number,
    errorHandler?: ErrorHandler,
  ) => {
    const url = generateAppUrl('projects/{projectId}/event-matrix', {
      projectId,
    });
    try {
      const response: AxiosResponse<ProjectEventMatrix> = await axios.get(url);
      logger.debug('FETCH EVENT MATRIX RESPONSE', response);
      vueSet(state.projectEvents, projectId, response.data);
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
    projectId: number,
    errorHandler?: ErrorHandler,
  ) => {
    const url = generateAppUrl('projects/{projectId}/folder/all', {
      projectId,
    });
    try {
      const response: AxiosResponse<ProjectFolders> = await axios.get(url);
      logger.debug('FETCH PROJECT FOLDERS RESPONSE', response);
      vueSet(state.projectFolders, projectId, response.data);
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
  const statePutProject = (
    project: FrontEndEntity<'Project'>,
    errorHandler?: ErrorHandler,
  ): Project => {
    const projectId = '' + project.id;
    if (state.projects[projectId]) {
      return state.projects[projectId];
    }
    const projectReference: EntityReference<'Project'> = {
      entityClassName: 'Project',
      flatIdentifier: projectId,
    };
    const proxyHandler: ProxyHandler<EntityReference<'Project'> > = {
      get: (entityReference, field, _receiver) => {
        switch (field) {
          case 'wikiPage': {
            // see ProjectService::projectWikiLink()
            const project = databaseEntities.find('Project', projectId)!;
            return `${globalState.wikiNameSpace ?? ''}:${globalState.projectsFolder ?? ''}:${project.name ?? ''}`;
          }
          case 'folders':
            return state.projectFolders[projectId] ?? undefined;
          case 'getFolders':
            return (handler?: ErrorHandler) => stateGetProjectFolders(+projectId, handler || errorHandler);
          case 'eventMatrix':
            return state.projectEvents[projectId] ?? undefined;
          case 'getEventMatrix':
            return (handler?: ErrorHandler) => stateGetEventMatrix(+projectId, handler || errorHandler);
          case '__ob__':
          case '__v_skip':
            return Reflect.get(entityReference, field);
          default: {
            const project = databaseEntities.find('Project', projectId)!;
            if (field === 'hasOwnProperty') {
              return function(key: string|symbol) {
                return proxyHandler.has!(entityReference, key);
              };
            }
            return Reflect.get(project, field);
          }
        }
      },
      has: (entityReference, field) => {
        switch (field) {
          case 'wikiPage':
          case 'folders':
          case 'getFolders':
          case 'eventMatrix':
          case 'getEventMatrix':
            return true;
          case '__ob__':
          case '___v_skip':
            return Reflect.has(entityReference, field);
          default: {
            const project = databaseEntities.find('Project', projectId)!;
            return field in project;
          }
        }
      },
      ownKeys: (entityReference) => {
        const project = databaseEntities.find('Project', projectId)!;
        return ['wikiPage', 'folders', 'getFolders', 'eventMatrix', 'getEventMatrix', ...Object.keys(project), ...Reflect.ownKeys(entityReference).filter(key => key === '__ob__' || key === '__v_skip')];
      },
      set: (entityReference, field, value) => {
        if (field === '__ob__' || field === '__v_skip') {
          return Reflect.set(entityReference, field, value);
        }
        throw new AppDataStoreError(
          { entityReference, field, value },
          t(appName, 'App-store projects may not be modified.'),
        );
      },
      getOwnPropertyDescriptor: (entityRefererence, key) => {
        if (key === '__ob__' || key === '__v_skip') {
          return Reflect.getOwnPropertyDescriptor(entityRefererence, key);
        } else {
          switch (key) {
            case 'wikiPage':
            case 'folders':
            case 'getFolders':
            case 'eventMatrix':
            case 'getEventMatrix':
              // non-existing properties must be configurable ...
              return { enumerable: true, configurable: true, value: proxyHandler.get!(entityRefererence, key, undefined) };
            default: {
              const project = databaseEntities.find('Project', projectId)!;
              if (key in project) {
                return Reflect.getOwnPropertyDescriptor(project, key);
              }
              return undefined;
            }
          }
        }
      },
      defineProperty: (entityReference, key, descriptor) => {
        // console.trace('DEFINE PROP', { entityReference, key, descriptor });
        // return Reflect.defineProperty(entityReference, key, descriptor);
        if (key === '__ob__' || key === '__v_skip') {
          return Reflect.defineProperty(entityReference, key, descriptor);
        }
        return true;
      },
    };
    const stateProject = new Proxy<EntityReference<'Project'> >(
      projectReference,
      proxyHandler,
    ) as unknown as Project;
    vueSet(state.projects, projectId, stateProject);
    vueSet(state.projectsByName, project.name, stateProject);
    return state.projects[projectId];
  };
  const stateFindProject = async (
    projectId: number,
    errorHandler?: ErrorHandler,
  ) => {
    try {
      const data = await databaseEntities.fetch({
        entityName: 'Project',
        identifier: { id: projectId },
      });
      logger.info('FIND PROJECT RESPONSE', data);
      const project = statePutProject(data.Project[projectId], errorHandler);
      return project;
    } catch (e) {
      stateHandleError(
        e,
        { action: 'findProject', projectId },
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
    query = query.replace(/\*/g, '%');
    if (!query.match('%')) {
      if (!query.startsWith('^')) {
        query = '%' + query;
      } else {
        query = query.slice(1);
      }
      if (!query.endsWith('$')) {
        query = query + '%';
      } else {
        query = query.slice(0, -1);
      }
    }
    const findBy = { '(|name': query, id: query, ')': true };
    try {
      const data = await databaseEntities.search({
        entityName: 'Project',
        findBy: query.match('%') ? { ...WILDCARD_QUERY_OPTIONS, ...findBy } : findBy,
        limit: 10,
      });
      const result: Project[] = [];
      for (const project of Object.values(data.Project)) {
        result.push(statePutProject(project, errorHandler));
      }
      return result;
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

  const setCurrentProject = (
    projectKey?: string | number,
    handler?: ErrorHandler,
  ) => {
    if (projectKey === currentProjectId.value
        || projectKey === currentProjectName.value) {
      return currentProject.value;
    }
    if (projectKey) {
      return getProject(projectKey, handler).then(project => {
        currentProject.value = project;
        return Promise.resolve(currentProject.value);
      });
    } else {
      currentProject.value = undefined;
    }
    return currentProject.value;
  };

  return {
    busyCount,
    busyFlag,
    busyState,
    currentProject,
    currentProjectId,
    currentProjectName,
    databaseEntities,
    errorHandler,
    getProject,
    logger: loggerRef,
    popBusyState,
    popErrorHandler: errorHandlerProvider.popHandler,
    projectMode,
    projects,
    pushBusyState,
    pushErrorHandler: errorHandlerProvider.pushHandler,
    searchProjects,
    setBusyFlag,
    setCurrentProject,
  };
});
