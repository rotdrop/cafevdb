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
import router from '../router/app-router.ts';

export const HistoryActionPush = 'push';
export const HistoryActionPop = 'pop';
export const HistoryActionReplace = 'replace';
export const HistoryActionUnknown = 'unknown';

export type HistoryActionPush = typeof HistoryActionPush;
export type HistoryActionPop = typeof HistoryActionPop;
export type HistoryActionReplace = typeof HistoryActionReplace;
export type HistoryActionUnknown = typeof HistoryActionUnknown;

export type HistoryAction = HistoryActionPush|HistoryActionReplace|HistoryActionPop|HistoryActionUnknown;

const storeId = 'history';

export class HistoryStorePersistenceError extends AppError {
  constructor(...p: ConstructorParameters<ErrorConstructor>) {
    super({ component: storeId + '-store', type: storeId }, ...p);
  }
}

export class HistoryStoreMutationError extends AppError {
  constructor(...p: ConstructorParameters<ErrorConstructor>) {
    super({ component: storeId + '-store', type: storeId }, ...p);
  }
}

export class HistoryStoreSetupError extends AppError {
  constructor(...p: ConstructorParameters<ErrorConstructor>) {
    super({ component: storeId + '-store', type: storeId }, ...p);
  }
}

export class HistoryStoreNavigationError extends AppError {
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
  const errorHandler = <E extends AppError>(error: E) => {
    errorHandlerProvider.errorHandler(error);
  };

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
        return errorHandler(new AppError({ arg }, t(appName, 'Either "hash" or "post" have to be specified.')));
      }
      if (arg.post && !requestData[this._hash]) {
        requestData[this._hash] = sanitizePostData(arg.post, true /* excludeUrlParams */);
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

  const pendingHistoryData = ref<undefined|TemplatePostData>(undefined);
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
          return errorHandler(error);
        }
        defineInitialHistory(initialHistoryIndex);
        if (pendingInitTransition !== undefined) {
          finishHistoryAction(currentRoute)
          pendingInitTransition = undefined;
        } else if (pendingHistoryKey.value === 'initial') {
          pendingHistoryKey.value = currentHistoryIndex.value; // ?? really
        }
      },
    );
  }

  /**
   * Adjust the document title in order to provide more useful
   * informations in the history menus of the web-browser. The goal is to replace
   *
   * blah
   * blah
   * blah
   *
   * by
   *
   * blah - useful info 1
   * blah - useful info 2
   * blah - useful info 3
   */
  function adjustDocumentTitle(route: Route) {
    const titleElement = document.querySelector('head title')!;
    const originalTitleAttribute = appName + '-original-title';
    if (!titleElement.getAttribute(originalTitleAttribute)) {
      titleElement.setAttribute(originalTitleAttribute, titleElement.textContent!.trim())
    }
    const originalTitle = titleElement.getAttribute(originalTitleAttribute);

    let titleSupplement = '';
    if (route.path === '/') {
      titleSupplement = t(appName, 'Home');
    } else {
      const routeParams = sanitizePostData(Object.assign({}, route.params));
      titleSupplement = Object.values(routeParams).join('/');
    }
    document.title = originalTitle + ' - ' + titleSupplement;
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
  function scheduleHistoryAction(action: HistoryAction, post: TemplatePostData, hash?: string): string {
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
    return scheduleHistoryAction(HistoryActionPush, post, hash);
  }

  function scheduleHistoryReplace(post: object, hash?: string):string {
    return scheduleHistoryAction(HistoryActionReplace, post, hash);
  }

  function terminateHistoryAction<E extends AppError>(resolve: boolean|E) {
    pendingHistoryAction.value = undefined;
    pendingHistoryData.value = undefined;
    pendingHistoryHash.value = undefined;
    pendingHistoryKey.value = undefined;
    logger.debug('cancelHistoryAction()', { resolve }, { ...routerHistory.value });
    settleMutationPromise(resolve);
  }

  function clearHistoryAction() {
    terminateHistoryAction(true);
  }

  function cancelHistoryAction<E extends AppError>(error?: E) {
    terminateHistoryAction(error || new HistoryStoreNavigationError('History action has been cancelled'));
  }

  /**
   * Remove the history chain following the current index. This is
   * needed when pushing a new state at a position which is not the
   * final position in the current stack.
   */
  function removeHistoryTail() {
    const history = routerHistory.value;
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
    history[currentHistoryIndex.value].next = null;
  }

  let mutationPromise: undefined|PromiseWithResolvers<void> = undefined;

  const scheduleMutationPromise = async () => {
    if (mutationPromise) {
      let promise: Promise<void>;
      do {
        await (promise = mutationPromise.promise);
      } while (mutationPromise && promise !== mutationPromise.promise);
    }
    logger.info('Schedule mutation promise');
    return mutationPromise = Promise.withResolvers<void>();
  };

  const settleMutationPromise = <E extends AppError>(arg: boolean|E = true) => {
    if (mutationPromise) {
      logger.info('Settle mutation promise', { arg });
      if (arg === true) {
        mutationPromise.resolve();
      } else {
        mutationPromise.reject(
          new HistoryStoreMutationError(
            t(appName, 'Mutation promise has been rejected.'),
            arg !== false ? { cause: arg } : undefined,
          ),
        );
      }
      mutationPromise = undefined;
    } else if (arg instanceof Error) {
      errorHandler(arg);
    }
  };

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
      requestData: { ...requestData },
    });
    if (Object.keys(routerHistory.value).length === 0) {
      logger.info('POSTPONING HISTORY FINISH CALL UNTIL AFTER INIT');
      pendingInitTransition = transition;
      return;
    }
    adjustDocumentTitle(currentRoute);
    if (transition === 'replace' && pendingHistoryKey.value === 'initial') {
      // just replace the keys and be gone.
      if (Object.keys(history).length > 1) {
        return errorHandler(
          new HistoryStoreSetupError(t(appName, 'History already contains more than the initial setup item.'))
        );
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
        pendingHistoryAction.value = HistoryActionReplace;
      } else if (history[key]) {
        // 'pop' action, back or forward
        pendingHistoryAction.value = HistoryActionPop;
      } else {
        // assume 'push'
        pendingHistoryAction.value = HistoryActionPush;
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

    switch (pendingHistoryAction.value) {
      case HistoryActionPush: {
        const key = window.history.state.key;
        history[key] = new RouterHistoryRecord({
          prev: currentHistoryIndex.value,
          next: null,
          hash: pendingHistoryHash.value,
          post: pendingHistoryData.value,
          key,
          path: route.fullPath,
        });
        removeHistoryTail();
        history[currentHistoryIndex.value].next = key;
        currentHistoryIndex.value = key;
        updateModificationTime();
        break;
      }
      case HistoryActionReplace: {
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
        break;
      }
      case HistoryActionPop: {
        currentHistoryIndex.value = window.history?.state?.key || 'initial';
        updateModificationTime();
        break;
      }
      case HistoryActionUnknown:
        // fallthrough
      default:
        cancelHistoryAction(new HistoryStoreNavigationError(t(appName, 'Unexpected transition type: {transition}.', {
          transition: pendingHistoryAction.value || 'undefined',
        })));
        return;
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
      errorHandler(error);
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
      errorHandler(new HistoryStorePersistenceError(message, { cause: e }));
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
        errorHandler(error);
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
        errorHandler(error);
      }
      return false; // maybe not reached
    }
  };

  const validateHistoryMutation = (
    chain: Record<string, RouterHistoryState<'deep'> >,
    posKey: string) => {
      const posEntry = chain[posKey];
      if (!posEntry) {
        return errorHandler(
          new HistoryStoreMutationError(
            t(appName, 'The key "{posKey}" of the final destination does not point into the provided history chain.', {
              posKey,
            })
          ),
        );
      }
      // the check above also ensures that chain contains at least one state
    };

  /**
   * Push the given history chain to on top of the current state and
   * navigate then to the given location.
   *
   * In order to keep the states consistent we have to adjust the keys
   * to start just after the current index.
   *
   * @param chain History data to append, including post-data.
   *
   * @param posKey Position to finally go to.
   *
   * @param replaceCurrent Whether to replace the current history
     state by the first state of chain.
   *
   * @todo We should probably check that the history data is sorted in
   * ascending order. Actually, we could get rid of the rather
   * complicated prev/next list by just using the key, which is a
   * timestamp and hence always increasing.
   */
  const pushHistoryStack = async (
    chain: Record<string, RouterHistoryState<'deep'> >,
    posKey: string,
    replaceCurrent = false,
  ) => {
    validateHistoryMutation(chain, posKey);
    // the check above also ensures that chain contains at least one state
    mutationPromise = await scheduleMutationPromise();
    removeHistoryTail();
    const history = routerHistory.value;
    const keys = Object.keys(chain).sort((a, b) => +a - +b);
    if (replaceCurrent) {
      const firstKey = keys.shift() as string;
      logger.debug('REPLACE CURRENT STATE BY FIRST STATE');
      // otherwise replace as go(0) triggers an active reload of the current page.
      const entry = chain[firstKey];
      if (keys.length > 0) {
        chain[keys[0]].prev = null; // unchain the first element
      }
      const resolved = router.resolve(entry.path);
      const params = sanitizePostData(Object.assign({}, entry.post, resolved.location.params))
      const location = {
        name: resolved.route.name!, // @todo error handling for route.name
        params,
      }
      settleMutationPromise();
      try {
        await router.replace(location);
      } catch (error) {
        return errorHandler(
          new HistoryStoreMutationError(
            t(appName, 'Unable to replace the current view.'),
          ),
        );
      }
    }

    // we need to tweak the keys of the given chain
    const offset = currentHistoryIndex.value;
    let counter = 0;
    // sort the keys ascending

    const keyMap = Object.fromEntries(keys.map(key => [key, '' + (+offset + (++counter) / 1000.0)]));
    logger.debug('KEYMAP', keyMap);
    for (const key of keys) {
      const entry = chain[key];
      const prev = entry.prev ? keyMap[entry.prev] : currentHistoryIndex.value;
      const next = entry.next ? keyMap[entry.next] : null;
      history[keyMap[key]] = new RouterHistoryRecord({
        next,
        prev,
        key: keyMap[key],
        hash: entry.hash,
        post: entry.post,
        path: entry.path,
      })
      logger.debug('HISTORY DURING MUTAION', key, keyMap[key], { ...history });
      if (!entry.prev) {
        currentHistoryState.value.next = keyMap[key];
      }
    };

    logger.debug('HISTORY AFTER INTERNAL STATE MUTATION', { ...history });

    // First push to the window history ignoring the vue-router
    for (const [key, mappedKey] of Object.entries(keyMap)) {
      const entry = chain[key];
      const url = generateAppUrl(entry.path.replace(/^\/+/, ''));
      window.history.pushState({ key: mappedKey }, '', url);

      const resolved = router.resolve(entry.path);
      adjustDocumentTitle(resolved.route);
    }
    // Compute the offset from the tail to the desired position
    const jump = keys.indexOf(posKey) + 1 - keys.length;
    logger.debug('JUMP COMPUTATION', jump, keys.indexOf(posKey), keys.length);
    try {
      if (jump < 0) {
        logger.debug('JUMPING', jump);
        router.go(jump);
        await mutationPromise.promise;
      } else {
        logger.debug('REPLACE AS REQUEST POS IS LAST ONE');
        // otherwise replace as go(0) triggers an active reload of the current page.
        const entry = chain[posKey];
        const resolved = router.resolve(entry.path);
        const params = sanitizePostData(Object.assign({}, entry.post, resolved.location.params))
        const location = {
          name: resolved.route.name!, // @todo error handling
          params,
        }
        currentHistoryIndex.value = keyMap[posKey];
        settleMutationPromise();
        await router.replace(location);
      }
    } catch (error) {
      errorHandler(
        new HistoryStoreMutationError(
          t(appName, 'Unable to establish desired view.'),
          { cause: error },
        ),
      );
    }
  }

  /**
   * Like pushHistoryStack(), but first navigate to the end of the stored history, if necessary.
   */
  const appendHistoryStack = async (chain: Record<string, RouterHistoryState<'deep'> >, posKey: string) => {
    validateHistoryMutation(chain, posKey);
    // navigate first to the top of the stack.
    let counter = 0;
    const history = routerHistory.value;
    let state = history[currentHistoryIndex.value]
    while (state.next) {
      ++counter;
      state = history[state.next];
    }
    if (counter > 0) {
      try {
        const mutationPromise = await scheduleMutationPromise()
        router.go(counter);
        await mutationPromise.promise;
      } catch (e) {
        return errorHandler(
          new HistoryStoreMutationError(
            t(appName, 'Unable to go to the end of the history stack.'),
            { cause: e },
          ),
        );
      }
    }
    return pushHistoryStack(chain, posKey);
  };

  /**
   * Like pushHistoryStack(), but first navigate to the start of the stored history, if necessary.
   */
  const replaceHistoryStack = async (chain: Record<string, RouterHistoryState<'deep'> >, posKey: string) => {
    validateHistoryMutation(chain, posKey);
    // navigate first to the top of the stack.
    let counter = 0;
    const history = routerHistory.value;
    let state = history[currentHistoryIndex.value]
    while (state.prev) {
      --counter;
      state = history[state.prev];
    }
    if (counter < 0) {
      try {
        const mutationPromise = await scheduleMutationPromise()
        router.go(counter);
        await mutationPromise.promise;
      } catch (e) {
        return errorHandler(
          new HistoryStoreMutationError(
            t(appName, 'Unable to go to the start of the history stack.'),
            { cause: e },
          ),
        );
      }
    }
    return pushHistoryStack(chain, posKey, true /* replaceCurrent */);
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
    pushHistoryStack,
    appendHistoryStack,
    replaceHistoryStack,
    adjustDocumentTitle,
  };
});
