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

import Console from '../util/console.ts';
import axios, { type AxiosResponse } from '@nextcloud/axios';
import generateAppUrl from '../toolkit/util/generate-url.js';
import getInitialState from '../toolkit/services/InitialStateService.js';
import moment from '@nextcloud/moment';
import type { Route, TransitionType } from 'vue-router';
import type { TemplatePostData } from '../util/legacy-post-data.ts';
import useErrorHandler from './error-handler.ts';
import { AppError } from '../types/errors.ts';
import { StatusCodes as HttpStatusCodes } from 'http-status-codes';
import { appName } from '../config.ts';
import { defineStore } from 'pinia';
import { generatePostHash, sanitizePostData } from '../util/legacy-post-data.ts';
import { isAxiosError } from '../types/ajax/axios-type-guards.ts';
import { ref, computed, watch, reactive } from 'vue';
import { showError, showInfo, showMessage } from '@nextcloud/dialogs';
import { translate as t } from '@nextcloud/l10n';
import { useRoute } from 'vue-router/composables';

export const HistoryActionPush = 'push';
export const HistoryActionPop = 'pop';
export const HistoryActionReplace = 'replace';
export type HistoryAction = TransitionType;

const storeId = 'history';

export class HistoryStorePersistenceError extends AppError {
  constructor(...p: ConstructorParameters<ErrorConstructor>) {
    super({ component: storeId + '-store', type: storeId }, ...p);
  }
}

export class HistoryStoreSetupError extends AppError {
  constructor(...p: ConstructorParameters<ErrorConstructor>) {
    super({ component: storeId + '-store', type: storeId }, ...p);
  }
}

export type FetchMode = 'deep'|'shallow';
export type FetchAll = 'all';

export interface RouterHistoryState<Mode extends FetchMode = 'deep'> {
  next: string|number|null,
  prev: string|number|null,
  key: string,
  hash: string,
  position: number|null,
  path: string,
  post: Mode extends 'deep' ? TemplatePostData : undefined|TemplatePostData;
}

interface HistoryInitialState {
  post?: Record<string, TemplatePostData>,
  queryHash?: string,
  lastUrlHash?: string,
  lastUrlPath?: string,
}

export interface HistoryPersistenceRecord<Mode extends FetchMode = 'deep'> {
  modificationTime?: number,
  position: string, // current history key
  history: Record<string, RouterHistoryState<Mode> >,
  requestData: Mode extends 'deep' ? Record<string, TemplatePostData> : undefined|Record<string, TemplatePostData>,
}

type LoadHistoryDataType<T extends FetchAll|number, M extends string> =
 T extends FetchAll
 ? (M extends FetchMode ? Record<number, HistoryPersistenceRecord<M> > : never)
 : (M extends FetchMode
    ? HistoryPersistenceRecord<M>
    : RouterHistoryState<'deep'>);

export default defineStore(storeId, () => {
  const errorHandlerProvider = useErrorHandler();
  const errorHandler = ref(errorHandlerProvider.errorHandler);

  const loggerRef = ref(new Console(storeId));
  const logger = loggerRef.value;

  logger.debug('HISTORY STORE INIT');

  const requestData = reactive<Record<string, TemplatePostData> >({});

  class RouterHistoryRecord implements RouterHistoryState {
    constructor(arg: {
      next?: string|number|null,
      prev?: string|number|null,
      key: string|number,
      hash?: string,
      post?: TemplatePostData,
      path: string,
    }) {
      this.next = arg.next || null;
      this.prev = arg.prev || null;
      this.key = arg.key || window.history.state.key;
      this.path = arg.path;
      this.position = window.history.length;
      this.replaceHash(arg);
    }

    next: string|number|null;
    prev: string|number|null;
    key: string;
    private _hash: string = '';
    position: number;
    path: string;
    get post() { return requestData[this._hash]; };
    get hash() { return this._hash; };
    replaceHash(arg: {
      hash?: string,
      post?: TemplatePostData,
    }) {
      logger.debug('REPLACE HASH ARGS', arg);
      if (arg.hash) {
        this._hash = arg.hash;
      } else if (arg.post) {
        this._hash = generatePostHash(arg.post);
      } else {
        throw new AppError({ arg }, t(appName, 'Either "hash" or "post" have to be specified.'));
      }
      if (arg.post && !requestData[this._hash]) {
        requestData[this._hash] = sanitizePostData(arg.post);
        logger.info('REQUEST DATA MAP', [...Object.entries(requestData)]);
      }
    };
    // just convert _hash to hash.
    toJSON() {
      return Object.fromEntries(
        Object.entries(this).map(([key, value]) => key === '_hash' ? ['hash', value] : [key, value]),
      )
    };
  }

  const routerHistory = ref<Record<string, RouterHistoryRecord> >({});
  const currentRoute = useRoute();

  const saveTime = ref<number>(0);
  const modificationTime = ref<number>(0);
  const updateModificationTime = () => { modificationTime.value = Date.now() / 1000.0; };

  const initialHistoryIndex: undefined|string = window?.history?.state?.key;
  const currentHistoryIndex = ref(initialHistoryIndex || '');

  const pendingHistoryData = ref<undefined|object>(undefined);
  const pendingHistoryHash = ref<undefined|string>(undefined); // optimization, do not compute twice
  const pendingHistoryAction = ref<undefined|HistoryAction>(undefined);
  const pendingHistoryKey = ref<undefined|string|number>('initial');
  const currentHistoryState = computed(() => routerHistory.value?.[currentHistoryIndex.value || ''] || null);
  const prevHistoryIndex = computed(() => currentHistoryState.value?.prev);
  const nextHistoryIndex = computed(() => currentHistoryState.value?.next);
  const prevHistoryState = computed(() => routerHistory.value?.[prevHistoryIndex.value || ''] || null);

  const nextHistoryState = computed(() => routerHistory.value?.[nextHistoryIndex.value || ''] || null);

  const initialState: HistoryInitialState = getInitialState('historyPostData', null);
  logger.info('INITIAL POST DATA STATE', initialState);

  let initialPostHash: undefined|string = undefined;
  let initialPostData: TemplatePostData = {};
  const lastUrlPath = ref<undefined|string>(undefined);
  const lastUrlHash = ref<undefined|string>(undefined);
  const lastUrlData = ref<undefined|TemplatePostData>(undefined);

  if (initialState) {
    if (initialState?.post) {
      for (const [hash, post] of Object.entries(initialState.post)) {
        requestData[hash] = sanitizePostData(post);
      }
    }
    const queryHash = initialState.queryHash;
    if (queryHash && requestData[queryHash]) {
      initialPostData = requestData[queryHash];
      initialPostHash = queryHash;
    }
    const lastHash = initialState.lastUrlHash;
    if (lastHash && requestData[lastHash] && initialState.lastUrlPath) {
      lastUrlPath.value = initialState.lastUrlPath;
      lastUrlHash.value = lastHash;
      lastUrlData.value = requestData[lastHash];
    }
  }

  const defineInitialHistory = (initialHistoryIndex: string) => {
    routerHistory.value = {
      [initialHistoryIndex]: new RouterHistoryRecord({
        post: initialPostData,
        hash: initialPostHash,
        key: initialHistoryIndex,
        path: currentRoute.fullPath,
      }),
    };
    currentHistoryIndex.value = initialHistoryIndex;
    updateModificationTime();
    logger.debug('INITIAL ROUTER HISTORY', currentHistoryIndex, { ...routerHistory.value[initialHistoryIndex] });
  };

  let pendingInitTransition: TransitionType|undefined = undefined;

  if (initialHistoryIndex) {
    defineInitialHistory(initialHistoryIndex);
  } else {
    logger.info('HISTORY YET EMPTY, INSTALLING WATCH ON CURRENT ROUTE', currentRoute);
    const stop = watch(
      currentRoute,
      (newValue, oldValue) => {
        stop();
        logger.debug('INITIAL ROUTE WATCHER', newValue, oldValue);
        const initialHistoryIndex = window?.history?.state?.key;
        if (!initialHistoryIndex) {
          let historyString: string;
          try {
            historyString = JSON.stringify(window?.history, null, 2);
          } catch (e: any) {
            historyString = ''
          }
          logger.error('Window history state has not been set up.', { ...(window?.history || {}) });
          const error = new HistoryStoreSetupError('Window history state has not been set up: ' + historyString);
          error.context.type = storeId;
          if (errorHandler.value) {
            errorHandler.value(error);
          } else {
            throw error;
          }
        }
        defineInitialHistory(initialHistoryIndex);
        if (pendingHistoryKey.value === 'initial') {
          pendingHistoryKey.value = currentHistoryIndex.value;
        }
        if (pendingInitTransition !== undefined) {
          finishHistoryAction(currentRoute)
          pendingInitTransition = undefined;
        }
      },
    );
  }

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
   * @param post Request data to store.
   *
   * @param hash Hash value of request data. Recomputed if not provided.
   */
  function scheduleHistoryAction(action: HistoryAction, post: object, hash?: string): string {
    const key = window?.history?.state?.key || 'initial';
    pendingHistoryAction.value = action;
    pendingHistoryData.value = post || {};
    hash = pendingHistoryHash.value = hash || generatePostHash(pendingHistoryData.value);
    pendingHistoryKey.value = key;
    logger.debug('scheduleHistoryAction()', {
      action,
      key,
      currentHistoryIndex: currentHistoryIndex.value,
      pendingHistoryKey: pendingHistoryKey.value,
      post,
      hash,
      routerHistory: routerHistory.value,
      requestData,
    });
    if (action !== 'pop'
        && currentHistoryIndex.value !== 'initial'
        && pendingHistoryKey.value !== currentHistoryIndex.value
    ) {
      logger.trace('SCHEDULE HISTORY KEY MISMATCH', pendingHistoryKey.value, currentHistoryIndex.value);
    }
    return pendingHistoryHash.value;
  }

  function scheduleHistoryPush(post: object, hash?: string):string {
    return scheduleHistoryAction('push', post, hash);
  }

  function scheduleHistoryReplace(post: object, hash?: string):string {
    return scheduleHistoryAction('replace', post, hash);
  }

  function clearHistoryAction() {
    pendingHistoryAction.value = undefined;
    pendingHistoryData.value = undefined;
    pendingHistoryHash.value = undefined;
    pendingHistoryKey.value = undefined;
    logger.debug('clearHistoryAction()', routerHistory.value);
  }

  function cancelHistoryAction() {
    clearHistoryAction();
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
  function finishHistoryAction(route: Route) {
    const transition = route.transition;
    const key = window?.history?.state?.key || 'initial';
    const history = routerHistory.value;
    logger.debug('ON HISTORY FINISH', {
      transition,
      key,
      keyType: typeof key,
      currentHistoryIndex: currentHistoryIndex.value,
      pendingHistoryAction: pendingHistoryAction.value,
      pendingHistoryKey: pendingHistoryKey.value,
      pendingHistoryData: pendingHistoryData.value,
      pendingHistoryHash: pendingHistoryHash.value,
      currentHistoryState: { ...currentHistoryState.value },
      history: { ...history },
      historyOfKey: { ...(history?.['' + key] || { undefined: true })},
      historyKeys: [...Object.keys(history)],
    });
    if (Object.keys(routerHistory.value).length === 0) {
      logger.info('POSTPONING HISTORY FINISH CALL UNTIL AFTER INIT');
      pendingInitTransition = transition;
      return;
    }
    if (transition === 'replace' && pendingHistoryKey.value === 'initial') {
      // just replace the keys and be gone.
      if (Object.keys(history).length > 1) {
        throw new HistoryStoreSetupError(t(appName, 'History already contains more than the initial setup item.'));
      }
      if (currentHistoryIndex.value !== key) {
        const currentState = currentHistoryState.value;
        delete history[currentHistoryIndex.value];
        history[key] = currentState;
        currentState.key = key;
        currentHistoryIndex.value = key;
        logger.info('Adjusted initial history key', { ...history }, { ...currentHistoryState.value });
      }
      if (currentHistoryState.value.path !== route.fullPath) {
        logger.info('Replacing route path', currentHistoryState.value.path, route.fullPath);
        currentHistoryState.value.path = route.fullPath;
      }
      updateModificationTime();
      clearHistoryAction();
      return;
    }

    // TODO: is this still needed?
    if (pendingHistoryAction.value === 'replace' && key !== currentHistoryIndex.value && currentHistoryIndex.value !== 'initial') {
      logger.trace('EXPLICIT HISTORY REPLACE REQUESTED, BUT CURRENT HISTORY IS GONE', {
        key,
        pendingHistoryKey: pendingHistoryKey.value,
        currentHistoryIndex: currentHistoryIndex.value,
        history: { ...history },
      });
      cancelHistoryAction();
    }
    if (pendingHistoryAction.value && pendingHistoryAction.value !== transition) {
      logger.error('PENDING HISTORY ACTION DOES NOT MATCH ACTUAL TRANSITION', {
        pendingHistoryAction: pendingHistoryAction.value,
        transition,
      });
    }
    if (!pendingHistoryAction.value) {
      if (transition !== 'unknown') {
        pendingHistoryAction.value = transition;
      } else if (key === pendingHistoryKey.value) {
        // replace action from RouterLink
        pendingHistoryAction.value = 'replace';
      } else if (history[key]) {
        // 'pop' action, back or forward
        pendingHistoryAction.value = 'pop';
      } else {
        // assume 'push'
        pendingHistoryAction.value = 'push';
      }
      logger.debug('TWEAKED HISTORY ACTION IS', {
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
      history[key] = new RouterHistoryRecord({
        prev: currentHistoryIndex.value,
        next: null,
        hash: pendingHistoryHash.value,
        post: pendingHistoryData.value,
        key,
        path: route.fullPath,
      });
      let nextKey = history[currentHistoryIndex.value].next;
      while (nextKey) {
        const removeKey = nextKey
        try {
          nextKey = history[nextKey].next
          delete history[removeKey]
        } catch (error: any) {
          logger.error('Exception while removing orphan tail on history push', {
            nextKey,
            removeKey,
            history: { ...history },
          })
          break;
        }
      }
      history[currentHistoryIndex.value].next = key;
      currentHistoryIndex.value = key;
      updateModificationTime();
    } else if (pendingHistoryAction.value === 'replace') {
      if (key !== currentHistoryIndex.value) {
        logger.debug('BEFORE ADJUST KEYS', key, currentHistoryIndex.value, { ...history[currentHistoryIndex.value] }, history?.[key]);
        history[key] = history[currentHistoryIndex.value];
        logger.debug('CURRENT STATE 0', { ...history[key] });
        delete history[currentHistoryIndex.value];
        logger.debug('CURRENT STATE 1', { ...history[key] });
        currentHistoryIndex.value = key;
        history[key].key = key;
        const prev = history[key].prev;
        const next = history[key].next;
        try {
          if (prev) {
            history[prev].next = key;
          }
          if (next) {
            history[next].prev = key;
          }
        } catch (error) {
          logger.error('Exception during history replace', {
            history: { ...history },
            key,
            next,
            prev,
          })
        }
        logger.debug('AFTER ADJUST KEYS', history);
      }
      history[key].replaceHash({
        hash: pendingHistoryHash.value,
        post: pendingHistoryData.value,
      })
      history[key].path = route.fullPath;
      updateModificationTime();
    } else {
      currentHistoryIndex.value = window.history?.state?.key || 'initial';
      updateModificationTime();
    }
    for (const [key, record] of Object.entries(routerHistory.value)) {
      if (key !== record.key) {
        logger.trace('SELF INCONSISTENCY', key, record, routerHistory.value);
      }
      if ((record.next || record.prev)
        && (record.next === record.prev || record.next === record.key || record.prev === record.key)) {
        logger.trace('EQUAL KEYS', key, { ...record }, { ...routerHistory.value });
      }
    }
    clearHistoryAction();
    logger.debug('finishHistoryAction()', {
      currentHistoryIndex: currentHistoryIndex.value,
      currentHistoryState: { ...currentHistoryState.value },
      routerHistory: { ...routerHistory.value },
      windowHistoryState: window?.history?.state,
    });
  }

  /**
   * Flat array of available history state in the database. Entries
     are the time-stamps.
   */
  const savedHistoryStates = ref<number[]>([]);
  axios.get<any, AxiosResponse<number[]>, any>(generateAppUrl('a/browser/history/timestamps'))
    .then((response) => {
      savedHistoryStates.value = response.data.map(stamp => +(+stamp).toFixed(3));
      logger.info('SAVE HISTORY STATES', { savedHistoryStates: savedHistoryStates.value });
    })
    .catch(e => {
      const error = new HistoryStorePersistenceError(
        t(appName, 'Unable to load the timestamps of the available history states.'),
        { cause: e },
      );
      if (errorHandler.value) {
        errorHandler.value(error);
      } else {
        throw error;
      }
    });

  const loadHistoryData = async <T extends FetchAll|number, M extends string>(timestamp: T, modeOrKey: M)
    : Promise<undefined|LoadHistoryDataType<T, M> > => {
    const url = generateAppUrl('a/browser/history/{timestamp}/{modeOrKey}', {
      timestamp,
      modeOrKey,
    });
    try {
      const response: AxiosResponse<LoadHistoryDataType<T, M> > = await axios.get(url);
      return response.data;
    } catch (e) {
      let message: string;
      if (timestamp === 'all') {
        message = t(appName, 'Unable to load the available history states.');
      } else if (modeOrKey === 'shallow' || modeOrKey === 'deep') {
        message = t(appName, 'Unable to load the history states at time {time} (timestamp: {timestamp}).', {
          time: moment((timestamp as number) * 1000).format('LLL'),
          timestamp,
        });
      } else {
        message = t(appName, 'Unable to load the history state data for "{key}" at time {time} (timestamp: {timestamp}).', {
          key:  modeOrKey,
          time: moment((timestamp as number) * 1000).format('LLL'),
          timestamp,
        });
      }
      const error = new HistoryStorePersistenceError(message, { cause: e });
      if (errorHandler.value) {
        errorHandler.value(error);
      } else {
        throw error;
      }
    }
  };

  const loadHistoryState = (timestamp: number, modeOrKey: FetchMode = 'shallow') => loadHistoryData(timestamp, modeOrKey);

  function loadHistoryStates(): ReturnType<typeof loadHistoryData<'all', 'shallow'> >;
  function loadHistoryStates<M extends FetchMode>(mode: M): ReturnType<typeof loadHistoryData<'all', M> >;
  function loadHistoryStates(mode: FetchMode = 'shallow') {
    return loadHistoryData('all', mode);
  }
  const loadHistoryEntry = (timestamp: number, key: string) => loadHistoryData(timestamp, key);

  /**
   * Collect the current history state into one JSON serializatble
   * object for persisting into databases or the browser's
   * localStorage area. localStorage is for one machine and one local
   * user, storing it in the database enables a restore from another
   * client machine.
   */
  const prepareHistorySaveRecord = () => {
    return {
      position: currentHistoryIndex.value,
      requestData, // the post data proper
      history: routerHistory.value,
    }
  };

  /**
   * Save the current history data to the DB.
   */
  const saveHistoryData = async () => {
    // logger.info('PREPARED HISTORY DATA', JSON.stringify(prepareHistorySaveRecord(), undefined, 2));
    const historySaveData = prepareHistorySaveRecord();
    const url = generateAppUrl(
      'a/browser/history/{timestamp}', {
        timestamp: modificationTime.value,
      });
    try {
      const response: AxiosResponse<{ message?: string }> = await axios.put(url, historySaveData);
      if (response.data.message) {
        showMessage(response.data.message);
      }
      saveTime.value = modificationTime.value;
      if (!savedHistoryStates.value.includes(modificationTime.value)) {

      }
    } catch (e: any) {
      if (isAxiosError(e) && e.status === HttpStatusCodes.CONFLICT) {
        showError(t(appName, 'The current history state has already been saved.'));
      } else {
        const error = new HistoryStorePersistenceError(
          t(appName, 'Unable to persist current history state to the database.'),
          { cause: e },
        );
        if (errorHandler.value) {
          errorHandler.value(error);
        } else {
          throw error;
        }
      }
    }
  };

  /**
   * Just delete the history record at the given time.
   */
  const deleteHistoryState = async (timestamp: number) => {
    const url = generateAppUrl('a/browser/history/{timestamp}', { timestamp });
    const time = moment(timestamp * 1000).format('LLL');

    try {
      await axios.delete(url);
      showInfo(t(appName, 'The history state at time {time} has been deleted.', { time }));
      return true;
    } catch (e) {
      if (isAxiosError(e) && e.status === HttpStatusCodes.NOT_FOUND) {
        showError(t(appName, 'The history state at time {time} could not be delete as it does not seem to exist on the server.', { time }));
      } else {
        const error = new HistoryStorePersistenceError(
          t(appName, 'Unable to delete the history state at time {time} from the database.', { time }),
          { cause: e },
        );
        if (errorHandler.value) {
          errorHandler.value(error);
        } else {
          throw error;
        }
      }
      return false; // maybe not reached
    }
  };

  return {
    logger: loggerRef,
    errorHandler,
    pushErrorHandler: errorHandlerProvider.pushHandler,
    popErrorHandler: errorHandlerProvider.popHandler,
    currentRoute,
    routerHistory,
    currentHistoryIndex,
    currentHistoryState,
    pendingHistoryAction,
    prevHistoryIndex,
    prevHistoryState,
    nextHistoryIndex,
    nextHistoryState,
    scheduleHistoryAction,
    scheduleHistoryPush,
    scheduleHistoryReplace,
    cancelHistoryAction,
    finishHistoryAction,
    requestData,
    lastUrlPath,
    lastUrlHash,
    lastUrlData,
    saveHistoryData,
    modificationTime,
    saveTime,
    savedHistoryStates,
    loadHistoryData,
    loadHistoryState,
    loadHistoryStates,
    loadHistoryEntry,
    deleteHistoryState,
  };
});
