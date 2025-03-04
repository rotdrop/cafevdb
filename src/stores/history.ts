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

import type { Route, TransitionType } from 'vue-router';
import { StatusCodes as HttpStatusCodes } from 'http-status-codes';
import { defineStore } from 'pinia';
import {
  computed,
  del as vueDel,
  reactive,
  ref,
  set as vueSet,
  watch,
} from 'vue';
import { useRoute } from 'vue-router/composables';
import { isNavigationFailure, NavigationFailureType } from 'vue-router'

import axios, { type AxiosResponse } from '@nextcloud/axios';
import moment from '@nextcloud/moment';
import { showError, showInfo, showMessage } from '@nextcloud/dialogs';
import { translate as t } from '@nextcloud/l10n';
import * as SessionStorage from '../util/session-storage.ts';

import Console from '../util/console.ts';
import generateAppUrl from '../toolkit/util/generate-url.js';
import getInitialState from '../toolkit/services/InitialStateService.js';
import router from '../router/app-router.ts';
import type { TemplatePostData } from '../util/legacy-post-data.ts';
import useErrorHandler from './error-handler.ts';
import { AppError } from '../types/errors.ts';
import { appName } from '../config.ts';
import { generatePostHash, sanitizePostData } from '../util/legacy-post-data.ts';
import { isAxiosError } from '../types/ajax/axios-type-guards.ts';

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

const sessionStorageHistoryKey = appName + '-web-browser-history';

export class HistoryStoreConsistencyError extends AppError {
  constructor(...p: ConstructorParameters<ErrorConstructor>) {
    super({ component: storeId + '-store', type: storeId }, ...p);
  }
}

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

export class HistoryStoreNavigationInhibitRequest extends AppError {
  constructor(...p: ConstructorParameters<ErrorConstructor>) {
    super({ component: storeId + '-store', type: storeId }, ...p);
  }
}

export type FetchMode = 'deep'|'shallow';
export type FetchAll = 'all';

export interface RouterHistoryState<Mode extends FetchMode = 'deep'> {
  // The key assigned by the vue-router to the history state. The key
  // also determines the position in the history stack (numerical
  // ordering).
  key: number,
  hash: string,
  position: string|number|null, // window.history.length at creation time.
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
      key: string|number,
      hash?: string,
      post?: TemplatePostData,
      path: string,
    }) {
      this.key = parseFloat(arg.key || window.history.state.key);
      this.path = arg.path;
      this.position = window.history.length;
      this.replaceHash(arg);
    }

    key: number;
    private _hash: string = '';
    position: number;
    path: string;
    get post() { return requestData[this._hash]; };
    get hash() { return this._hash; };
    replaceKey(arg?: string|number) {
      this.key = parseFloat(arg || window.history.state.key);
    };
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
        requestData[this._hash] = Object.freeze(sanitizePostData(arg.post, true /* excludeUrlParams */));
      }
      logger.info('REPLACE HASH REQUEST DATA', { post: this.post });
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

  const initialHistoryKey: undefined|string = window?.history?.state?.key;
  const currentHistoryKey = ref(initialHistoryKey || '');
  const routerHistoryKeys = computed(() => Object.keys(routerHistory.value).sort((a, b) => +a - +b));

  const pendingHistoryData = ref<undefined|TemplatePostData>(undefined);
  const pendingHistoryHash = ref<undefined|string>(undefined); // optimization, do not compute twice
  const pendingHistoryAction = ref<undefined|HistoryAction>(undefined);
  const pendingHistoryKey = ref<undefined|string|number>('initial');
  const currentHistoryState = computed(() => routerHistory.value?.[currentHistoryKey.value || ''] || null);
  const currentHistoryIndex = computed(() => routerHistoryKeys.value.indexOf(currentHistoryKey.value));
  const atHistoryBase = computed(() => currentHistoryIndex.value === 0);
  const atHistoryTop = computed(() => currentHistoryIndex.value === routerHistoryKeys.value.length - 1);

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
        requestData[hash] = Object.freeze(sanitizePostData(post, true /* excludeUrlParams */));
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

  // addEventListener('DOMContentLoaded', (event) => {});
  // addEventListener("beforeunload", (event) => {
  //   logger.info('BEFORE UNLOAD EVENT', event);
  // });
  document.onvisibilitychange = (event) => {
    // no async code in this function, it will not be executed.
    logger.info('VISIBILITY CHANGE EVENT', { event, state: document.visibilityState });
    if (document.visibilityState === 'hidden' && routerHistoryKeys.value.length > 1) {
      const historySaveRecord = prepareHistorySaveRecord();
      SessionStorage.setItem(sessionStorageHistoryKey, historySaveRecord);
    }
  };

  const getSessionStorageHistoryData = ():HistoryPersistenceRecord|null => {
    try {
      const historyData = SessionStorage.getItem(sessionStorageHistoryKey);
      logger.debug('GOT HISTORY DATA', historyData);
      // SessionStorage.removeItem(sessionStorageHistoryKey);
      return historyData;
    } catch (error) {
      logger.error('Unable to retrieve history data from the session storage', error);
    }
    return null;
  };

  const defineInitialHistory = (initialHistoryKey: string) => {
    routerHistory.value = {
      [initialHistoryKey]: new RouterHistoryRecord({
        post: initialPostData,
        hash: initialPostHash,
        key: initialHistoryKey,
        path: currentRoute.fullPath,
      }),
    };
    currentHistoryKey.value = initialHistoryKey;
    updateModificationTime();
    logger.debug('INITIAL ROUTER HISTORY', currentHistoryKey.value, { ...routerHistory.value[initialHistoryKey] });
  };

  let pendingInitTransition: TransitionType|undefined = undefined;

  if (initialHistoryKey) {
    defineInitialHistory(initialHistoryKey);
  } else {
    logger.info('HISTORY YET EMPTY, INSTALLING WATCH ON CURRENT ROUTE', currentRoute);
    const stop = watch(
      currentRoute,
      (newValue, oldValue) => {
        stop();
        logger.debug('INITIAL ROUTE WATCHER', newValue, oldValue);
        const initialHistoryKey = window?.history?.state?.key;
        if (!initialHistoryKey) {
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
        defineInitialHistory(initialHistoryKey);
        if (pendingInitTransition !== undefined) {
          finishHistoryAction(currentRoute)
          pendingInitTransition = undefined;
        } else if (pendingHistoryKey.value === 'initial') {
          pendingHistoryKey.value = currentHistoryKey.value; // ?? really
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
      currentHistoryKey: currentHistoryKey.value,
      pendingHistoryKey: pendingHistoryKey.value,
      post,
      hash,
      routerHistory: { ...routerHistory.value },
      requestData: { ...requestData },
    });
    if (action !== 'pop'
        && currentHistoryKey.value !== 'initial'
        && pendingHistoryKey.value !== currentHistoryKey.value
    ) {
      logger.trace('SCHEDULE HISTORY KEY MISMATCH', pendingHistoryKey.value, currentHistoryKey.value);
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
    logger.debug('terminateHistoryAction()', { resolve }, { ...routerHistory.value });
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
   * final position in the current stack. History states are ordered
   * by the numerical value of their key.
   */
  function removeHistoryTail() {
    if (currentHistoryIndex.value < 0) {
      logger.debug('INDEX < 0', {
        keys: [...routerHistoryKeys.value],
        history: { ...routerHistory },
        key: currentHistoryKey.value,
        index: currentHistoryIndex.value,
      });
      errorHandler(new HistoryStoreConsistencyError(
        t(appName, 'Unable to find key {key} in current history stack.', { key: currentHistoryKey.value }),
      ));
      return false;
    }
    const keysToDelete = routerHistoryKeys.value.slice(currentHistoryIndex.value + 1);
    logger.debug('Removing history tail', {
      currentHistoryKey: currentHistoryKey.value,
      currentHistoryIndex: currentHistoryIndex.value,
      history: { ...routerHistory.value },
      keysToDelete,
      routerHistoryKeys: [...routerHistoryKeys.value],
    });
    for (const key of keysToDelete) {
      logger.debug('DELETING HISTORY ENTRY', {
        key,
        entry: { ...routerHistory.value[key] },
      });
      vueDel(routerHistory.value, key);
    }
  }

  let mutationLock = Promise.withResolvers<void>();
  mutationLock.resolve();

  const aquireMutationLock = async () => {
    let promise: Promise<void>;
    let count = 0;
    do {
      logger.debug(3, 'ATTEMPT TO AQUIRE MUTATION LOCK', ++count);
      await (promise = mutationLock.promise);
    } while (promise !== mutationLock.promise);
    logger.debug(3, 'AQUIRED MUTATION LOCK');
    return mutationLock = Promise.withResolvers<void>();
  };

  const releaseMutationLock = () => {
    logger.debug(3, 'RELEASE MUTATION LOCK');
    mutationLock.resolve();
  };

  let mutationPromise: undefined|PromiseWithResolvers<void> = undefined;

  const scheduleMutationPromise = async () => {
    if (mutationPromise) {
      let promise: Promise<void>;
      do {
        await (promise = mutationPromise.promise);
      } while (mutationPromise && promise !== mutationPromise.promise);
    }
    logger.debug('Schedule mutation promise');
    return mutationPromise = Promise.withResolvers<void>();
  };

  const settleMutationPromise = <E extends AppError>(arg: boolean|E = true) => {
    logger.debug('SETTLE MUTATION PROMISE', mutationPromise);
    if (mutationPromise) {
      const promise = mutationPromise;
      mutationPromise = undefined;
      logger.info('Settle mutation promise', { arg });
      if (arg === true) {
        promise.resolve();
      } else {
        promise.reject(
          new HistoryStoreMutationError(
            t(appName, 'Mutation promise has been rejected.'),
            arg !== false ? { cause: arg } : undefined,
          ),
        );
      }
    } else if (arg instanceof AppError) {
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
  function finishHistoryAction(route: Route, from?: Route) {
    const transition = route.transition;
    const key = window?.history?.state?.key || 'initial';
    const history = routerHistory.value;
    logger.debug('ON HISTORY FINISH', {
      key,
      transition,
      to: { ...route },
      from: from ? { ...from } : undefined,
      currentHistoryIndex: currentHistoryIndex.value,
      currentHistoryKey: currentHistoryKey.value,
      pendingHistoryAction: pendingHistoryAction.value,
      pendingHistoryKey: pendingHistoryKey.value,
      pendingHistoryData: pendingHistoryData.value,
      pendingHistoryHash: pendingHistoryHash.value,
      currentHistoryState: { ...currentHistoryState.value },
      history: { ...history },
      historyOfKey: { ...(history?.['' + key] || { undefined: true })},
      historyKeys: [...routerHistoryKeys.value],
      requestData: { ...requestData },
    });
    if (routerHistoryKeys.value.length === 0) {
      logger.info('POSTPONING HISTORY FINISH CALL UNTIL AFTER INIT');
      pendingInitTransition = transition;
      return;
    }
    adjustDocumentTitle(currentRoute);
    if (transition === 'replace' && pendingHistoryKey.value === 'initial') {
      // just replace the keys and be gone.
      if (routerHistoryKeys.value.length > 1) {
        return errorHandler(
          new HistoryStoreSetupError(t(appName, 'History already contains more than the initial setup item.'))
        );
      }
      if (currentHistoryKey.value !== key) {
        const currentState = currentHistoryState.value;
        vueDel(history, currentHistoryKey.value);
        vueSet(history, key, currentState);
        currentState.replaceKey(key);
        currentHistoryKey.value = key;
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
    if (pendingHistoryAction.value === 'replace' && key !== currentHistoryKey.value && currentHistoryKey.value !== 'initial') {
      logger.trace('EXPLICIT HISTORY REPLACE REQUESTED, BUT CURRENT HISTORY IS GONE', {
        key,
        pendingHistoryKey: pendingHistoryKey.value,
        currentHistoryKey: currentHistoryKey.value,
        history: { ...history },
      });
      cancelHistoryAction();
    }
    if (pendingHistoryAction.value && pendingHistoryAction.value !== transition) {
      logger.error('PENDING HISTORY ACTION DOES NOT MATCH ACTUAL TRANSITION', {
        pendingHistoryAction: pendingHistoryAction.value,
        transition,
      });
      // reset ...
      cancelHistoryAction();
    }
    if (!pendingHistoryAction.value) {
      if (transition !== HistoryActionUnknown) {
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
        currentHistoryKey: currentHistoryKey.value,
        historyAtKey: history?.[key],
        history: { ...history },
      });
    }

    switch (pendingHistoryAction.value) {
      case HistoryActionPush: {
        if (route.matched.length > 1 && route.query.hash && requestData?.[route.query.hash as string]) {
          pendingHistoryHash.value = undefined; // @todo: get rid of the pending history hash value
          pendingHistoryData.value = Object.assign(
            pendingHistoryData.value || {},
            requestData?.[route.query.hash as string],
          );
        }
        removeHistoryTail();
        const key = window.history.state.key;
        vueSet(
          history,
          key,
          new RouterHistoryRecord({
            hash: pendingHistoryHash.value,
            post: pendingHistoryData.value,
            key,
            path: route.fullPath,
          }),
        );
        currentHistoryKey.value = key;
        updateModificationTime();
        break;
      }
      case HistoryActionReplace: {
        if (key !== currentHistoryKey.value) {
          logger.debug('BEFORE ADJUST KEYS', key, currentHistoryKey.value, { ...history[currentHistoryKey.value] }, history?.[key]);
          vueSet(history, key, history[currentHistoryKey.value]);
          logger.debug('CURRENT STATE 0', { ...history[key] });
          vueDel(history, currentHistoryKey.value);
          logger.debug('CURRENT STATE 1', { ...history[key] });
          currentHistoryKey.value = key;
          history[key].key = key;
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
        currentHistoryKey.value = window.history?.state?.key || 'initial';
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
      if (key !== record.key.toFixed(3)) {
        logger.trace('SELF INCONSISTENCY', key, { ...record }, { ...routerHistory });
      }
    }
    clearHistoryAction();
    logger.debug('finishHistoryAction()', {
      atBase: atHistoryBase.value,
      atTop: atHistoryTop.value,
      currentHistoryIndex: currentHistoryIndex.value,
      currentHistoryKey: currentHistoryKey.value,
      currentHistoryState: { ...currentHistoryState.value },
      requestData: { ...requestData },
      currentPostData: { ...currentHistoryState.value.post },
      routerHistory: { ...routerHistory.value },
      routerHistoryKeys: [...routerHistoryKeys.value],
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
  const prepareHistorySaveRecord = ():HistoryPersistenceRecord => {
    return {
      position: currentHistoryKey.value,
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
    posKey: string,
  ) => {
    const posEntry = chain[posKey];
    if (!posEntry) {
      errorHandler(
        new HistoryStoreMutationError(
          t(appName, 'The key "{posKey}" of the final destination does not point into the provided history chain.', {
            posKey,
          })
        ),
      );
      return false;
    }
    // the check above also ensures that chain contains at least one state
    return true;
  };

  /**
   * This serves to inhibit router transitions during history stack
   * mutations in order to prevent unnecessary callback invocations
   * and AJAX calls.
   */
  let inhibitRouterTransition = false;

  router.beforeEach((to, from, next) => {
    logger.debug('GLOBAL BEFORE EACH ROUTE CHANGE', {
      to,
      from,
      windowHistory: window?.history?.state,
    })
    if (inhibitRouterTransition) {
      // Note: just calling next(false) would still initiate either a
      // replace or a push transition which we do not want
      // here. Instead we throw a special error which simply aborts
      // the navigation and react on it in the error handler below
      logger.debug('INHIBIT ROUTER TRANSITION');
      throw new HistoryStoreNavigationInhibitRequest();
    }
    next()
  })

  // onError does catch anything __except__ routing errors.
  router.onError(error => {
    logger.debug('ROUTER ON ERROR HOOK', { error }, window?.history?.state);
    if (error instanceof HistoryStoreNavigationInhibitRequest) {
      logger.debug('Honour inhibit navigation request.');
      settleMutationPromise(true);
    } else {
      const cancelError = (error instanceof AppError) && error.cause
        ? error
        : new HistoryStoreNavigationError(t(appName, 'Error during page transitions.'), { cause: error });
      cancelHistoryAction(cancelError);
    }
  })

  /**
   * Gracefully "allow" duplicated navigation on pop. The router still
   * aborts the navigation, however, we still have to update our
   * history stack state. The history transition after a 'pop' event
   * has no other abort handler, so this handler does not interfere
   * with any other abort handlers.
   */
  router.onNavigationFailure(error => {
    if (error.to.transition === HistoryActionPop
        && error.type === NavigationFailureType.duplicated) {
      logger.debug('Finish history action on duplicated navigation.', { error });
      finishHistoryAction(error.to, error.from);
    }
  });

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
   * state by the first state of chain.
   *
   * @param mutationLock Already aquired mutation lock from caller
   *
   * @todo We should probably check that the history data is sorted in
   * ascending order. Actually, we could get rid of the rather
   * complicated prev/next list by just using the key, which is a
   * timestamp and hence always increasing.
   */
  const pushHistoryStack = async (
    chain: Record<string, RouterHistoryState<'deep'> >,
    posKey: string,
    params: { replaceCurrent?: boolean, mutationLock?: PromiseWithResolvers<void> } = { replaceCurrent: false },
  ) => {
    if (!validateHistoryMutation(chain, posKey)) {
      return;
    }
    // the check above also ensures that chain contains at least one state
    const { replaceCurrent, mutationLock } = params;
    if (!mutationLock) {
      await aquireMutationLock();
    }
    removeHistoryTail();
    const history = routerHistory.value;

    // sort the keys ascending
    const keys = Object.keys(chain).sort((a, b) => +a - +b);

    if (replaceCurrent) {
      logger.debug('REPLACE CURRENT STATE BY FIRST STATE');
      const firstKey = keys.shift() as string;
      const entry = chain[firstKey];

      if (keys.length === 0) {
        // edge case: just replace using the regular vue router
        // replace functionality and skip the remainder of this
        // function.
        const resolved = router.resolve(entry.path);
        const params = sanitizePostData(Object.assign({}, entry.post, resolved.location.params))
        const location = {
          name: resolved.route.name!, // @todo error handling for route.name
          params,
        }
        try {
          scheduleHistoryReplace(params);
          await router.replace(location);
        } catch (error) {
          if (isNavigationFailure(error, NavigationFailureType.duplicated)) {
            logger.debug('Finish history action after duplicated navigation during history replace.', { error });
            finishHistoryAction(error.to, error.from);
          } else {
            errorHandler(
              new HistoryStoreMutationError(
                t(appName, 'Unable to replace the current view.'),
              ),
            );
          }
        }
        releaseMutationLock();
        return;
      }

      // To there is at least on additional state. Just install the
      // post-data and path into the current history state.
      currentHistoryState.value.path = entry.path;
      currentHistoryState.value.replaceHash(entry);
      // bypass routing
      const url = generateAppUrl(entry.path.replace(/^\/+/, ''));
      window.history.replaceState(window.history.state, '', url);
    }

    // Compute the offset from the tail to the desired position
    const jump = keys.indexOf(posKey) + 1 - keys.length;
    logger.debug('JUMP COMPUTATION', jump, keys.indexOf(posKey), keys.length);
    if (jump === 0) {
      // if jump is 0 we finally have to do a router.push(), as go(0) reloads the page
      keys.pop();
    }

    if (keys.length > 0) {
      // we need to tweak the keys of the given chain
      let counter = 0;
      const offset = currentHistoryKey.value;
      const keyMap = Object.fromEntries(keys.map(key => [key, (+offset + (++counter) / 1000.0).toFixed(3)]));
      logger.debug('KEYMAP', keyMap);
      for (const key of keys) {
        const entry = chain[key];
        vueSet(
          history,
          keyMap[key],
          new RouterHistoryRecord({
            key: keyMap[key],
            hash: entry.hash,
            post: entry.post,
            path: entry.path,
          }),
        );
        logger.debug('HISTORY DURING MUTAION', key, keyMap[key], { ...history });
        currentHistoryKey.value = keyMap[key];
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
    }

    if (jump < 0) {
      try {
        const mutationPromise = await scheduleMutationPromise();
        router.go(jump);
        await mutationPromise.promise;
      } catch (error) {
        errorHandler(
          new HistoryStoreMutationError(
            t(appName, 'Unable to go to the desired view.'),
            { cause: error },
          ),
        );
      }
    } else {
      try {
        // push the final state throught the vue-router to avoid a reload by go(0)
        logger.debug('PUSH FINAL STATE AS REQUESTED POS IS LAST ONE', { entry: { ...chain[posKey] } });
        const entry = chain[posKey];
        const resolved = router.resolve(entry.path);
        const params = sanitizePostData(Object.assign({}, entry.post, resolved.location.params));
        const hash = entry.hash;
        const location = {
          name: resolved.route.name!, // @todo error handling
          params,
          query: { hash },
        }
        scheduleHistoryPush(params);
        await router.push(location);
      } catch (error) {
        if (isNavigationFailure(error, NavigationFailureType.duplicated)) {
          logger.debug('Finish history action after duplicated navigation during history replace.', { error });
          finishHistoryAction(error.to, error.from);
        } else {
          errorHandler(
            new HistoryStoreMutationError(
              t(appName, 'Unable to push the desired view.'),
              { cause: error },
            ),
          );
        }
      }
    }
    releaseMutationLock();
  }

  /**
   * Like pushHistoryStack(), but first navigate to the end of the stored history, if necessary.
   */
  const appendHistoryStack = async (chain: Record<string, RouterHistoryState<'deep'> >, posKey: string) => {
    if (!validateHistoryMutation(chain, posKey)) {
      return;
    }
    const mutationLock = await aquireMutationLock();
    // navigate first to the top of the stack.
    const counter = routerHistoryKeys.value.length - currentHistoryIndex.value - 1;
    if (counter > 0) {
      try {
        const mutationPromise = await scheduleMutationPromise()
        inhibitRouterTransition = true;
        router.go(counter);
        await mutationPromise.promise;
        inhibitRouterTransition = false;
      } catch (e) {
        errorHandler(
          new HistoryStoreMutationError(
            t(appName, 'Unable to go to the end of the history stack.'),
            { cause: e },
          ),
        );
        releaseMutationLock();
        return;
      }
    }
    return pushHistoryStack(chain, posKey, { mutationLock });
  };

  /**
   * Like pushHistoryStack(), but first navigate to the start of the stored history, if necessary.
   */
  const replaceHistoryStack = async (chain: Record<string, RouterHistoryState<'deep'> >, posKey: string) => {
    validateHistoryMutation(chain, posKey);
    const mutationLock = await aquireMutationLock();
    // navigate first to the start of the stack.
    const counter = -currentHistoryIndex.value;
    if (counter < 0) {
      try {
        const mutationPromise = await scheduleMutationPromise()
        inhibitRouterTransition = true;
        router.go(counter);
        await mutationPromise.promise;
        inhibitRouterTransition = false;
      } catch (e) {
        errorHandler(
          new HistoryStoreMutationError(
            t(appName, 'Unable to go to the start of the history stack.'),
            { cause: e },
          ),
        );
        releaseMutationLock();
        return;
      }
    }
    return pushHistoryStack(chain, posKey, { replaceCurrent: true, mutationLock });
  };

  // If available
  //
  // - verify the page URL matches the URL saved in the session storage
  // - use replaceHistoryStack -- maybe tweak that ...
  // logger.debug('HISTORY DATA FROM SESSION STORAGE', getSessionStorageHistoryData());

  router.onReady(() => {
    logger.debug('ON ROUTER READY HOOK');

    // try load history from session storage ...
    const historyData = getSessionStorageHistoryData();
    if (historyData && historyData.history[historyData.position].path === currentRoute.fullPath) {
      logger.debug('Try load history data from browser session');
      for (const entry of Object.values(historyData.history)) {
        entry.post = historyData.requestData[entry.hash];
      }
      replaceHistoryStack(historyData.history, historyData.position)
    }
  });

  return {
    logger: loggerRef,
    errorHandler,
    pushErrorHandler: errorHandlerProvider.pushHandler,
    popErrorHandler: errorHandlerProvider.popHandler,
    currentRoute,
    routerHistory,
    routerHistoryKeys,
    currentHistoryKey,
    currentHistoryIndex,
    currentHistoryState,
    atHistoryBase,
    atHistoryTop,
    pendingHistoryAction,
    scheduleHistoryAction,
    scheduleHistoryPush,
    scheduleHistoryReplace,
    clearHistoryAction, // clear pending action without error
    cancelHistoryAction, // abort with error
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
    aquireMutationLock,
    releaseMutationLock,
  };
});
