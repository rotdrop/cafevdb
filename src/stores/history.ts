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

import type { AxiosResponse } from '@nextcloud/axios';
import type {
  HistoryState,
  RouteLocationGeneric,
  RouteLocationNormalizedGeneric,
  TransitionType,
} from 'vue-router';
import type { TemplatePostData } from '../util/legacy-post-data.ts';

import axios from '@nextcloud/axios';
import { showError, showInfo, showMessage } from '@nextcloud/dialogs';
import { translate as t } from '@nextcloud/l10n';
import moment from '@nextcloud/moment';
import { StatusCodes as HttpStatusCodes } from 'http-status-codes';
import { defineStore } from 'pinia';
import { v4 as uuidv4 } from 'uuid';
import {
  computed,
  reactive,
  ref,
  watch,
} from 'vue';
import {
  isNavigationFailure,
  NavigationFailureType,
  useRoute,
} from 'vue-router';
import {
  BASE_PATH as controllerBasePath,
  GET_REQUEST_TIMESTAMPS as getTimestamps,
} from '../../build/ts-types/php-modules/Controller/WebBrowserHistoryController.ts';
import { appName } from '../config.ts';
import router, { history as vueRouterHistory } from '../router/app-router.ts';
import { isAxiosError } from '../toolkit/types/axios-type-guards.ts';
import { AppError } from '../toolkit/types/errors.ts';
import generateAppUrl from '../toolkit/util/generate-url.ts';
import getInitialState from '../toolkit/util/initial-state.ts';
import Console from '../util/console.ts';
import { generatePostHash, sanitizePostData } from '../util/legacy-post-data.ts';
import * as SessionStorage from '../util/session-storage.ts';
import useErrorHandler from './error-handler.ts';

export const HistoryActionPush = 'push';
export const HistoryActionPop = 'pop';
export const HistoryActionReplace = 'replace';
export const HistoryActionUnknown = 'unknown';

export type HistoryActionPush = typeof HistoryActionPush;
export type HistoryActionPop = typeof HistoryActionPop;
export type HistoryActionReplace = typeof HistoryActionReplace;
export type HistoryActionUnknown = typeof HistoryActionUnknown;

export type HistoryAction = HistoryActionPush|HistoryActionReplace|HistoryActionPop|HistoryActionUnknown;

export interface VueRouterHistoryState extends HistoryState {
  position: number;
  // replaced: boolean;
  // back: string | null;
  // current: string;
  // forward: string | null;
}

export const isVueRouterHistoryState = (arg: HistoryState): arg is VueRouterHistoryState => !!arg?.position;

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
  state: VueRouterHistoryState;
  hash: string;
  windowHistoryPosition: number|null; // window.history.length at creation time.
  path: string;
  post: Mode extends 'deep' ? TemplatePostData : undefined|TemplatePostData;
}

interface HistoryInitialState {
  post?: Record<string, TemplatePostData>;
  queryHash?: string;
  lastUrlHash?: string;
  lastUrlPath?: string;
}

export interface HistoryPersistenceRecord<Mode extends FetchMode = 'deep'> {
  modificationTime?: number;
  position: number; // current history state.position
  history: Record<number, RouterHistoryState<Mode>>;
  requestData: Mode extends 'deep' ? Record<string, TemplatePostData> : undefined|Record<string, TemplatePostData>;
}

type LoadHistoryDataType<T extends FetchAll|number, M extends string> =
  T extends FetchAll
    ? (M extends FetchMode ? Record<number, HistoryPersistenceRecord<M>> : never)
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

  // post-data indexed by its hash
  const requestData = reactive<Record<string, TemplatePostData>>({});

  class RouterHistoryRecord implements RouterHistoryState {

    constructor(arg: {
      state: VueRouterHistoryState;
      hash?: string;
      post?: TemplatePostData;
      path: string;
    }) {
      this.replaceState(arg.state);
      this.path = arg.path;
      this.windowHistoryPosition = window.history.length;
      this.replaceHash(arg);
    }

    _state: VueRouterHistoryState = { position: -1 };
    private _hash: string = '';
    windowHistoryPosition: number;
    path: string;
    get state() { return this._state; }
    get post() { return requestData[this._hash]; }
    get hash() { return this._hash; }
    replaceState(state?: HistoryState) {
      this._state = state ? { ...state } : { position: -1, ...window.history.state };
    }

    replaceHash(arg: {
      hash?: string;
      post?: TemplatePostData;
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
      logger.debug('REPLACE HASH REQUEST DATA', { post: this.post });
    }

    // just convert _hash to hash.
    toJSON() {
      return Object.fromEntries(
        Object.entries(this).map(([key, value]) => key === '_hash' ? ['hash', value] : [key, value]),
      );
    }

  }

  const routerHistory = ref<Record<number, RouterHistoryRecord>>({});

  const currentRoute = useRoute();

  const saveTime = ref<number>(0);
  const modificationTime = ref<number>(0);
  const updateModificationTime = () => {
    modificationTime.value = Date.now() / 1000.0;
  };

  const initialHistoryState = window?.history?.state;
  const currentHistoryPosition = ref<number>(initialHistoryState?.position ?? -1);
  const routerHistoryPositions = computed(() => Object.keys(routerHistory.value).map((a) => +a).sort((a, b) => a - b));

  const pendingHistoryData = ref<undefined|TemplatePostData>(undefined);
  const pendingHistoryHash = ref<undefined|string>(undefined); // optimization, do not compute twice
  const pendingHistoryAction = ref<undefined|HistoryAction>(undefined);
  const pendingHistoryPosition = ref<undefined|number>(-1);
  const currentHistoryState = computed(() => routerHistory.value?.[currentHistoryPosition.value ?? -1] ?? null);
  const currentHistoryIndex = computed(() => routerHistoryPositions.value.indexOf(currentHistoryPosition.value));
  const atHistoryBase = computed(() => currentHistoryIndex.value === 0);
  const atHistoryTop = computed(() => currentHistoryIndex.value === routerHistoryPositions.value.length - 1);

  const initialState: null|HistoryInitialState = getInitialState<HistoryInitialState>({
    section: 'historyPostData',
    defaults: null,
  });
  logger.info('INITIAL POST DATA STATE', initialState);

  let initialPostHash: undefined|string;
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
    if (document.visibilityState === 'hidden' && routerHistoryPositions.value.length > 1) {
      const historySaveRecord = prepareHistorySaveRecord();
      SessionStorage.setItem(sessionStorageHistoryKey, historySaveRecord);
      // logger.debug('SESSION STORAGE HISTORY', JSON.stringify(historySaveRecord, undefined, 2));
    }
  };

  const getSessionStorageHistoryData = (): HistoryPersistenceRecord|null => {
    try {
      const historyData = SessionStorage.getItem(sessionStorageHistoryKey);
      logger.debug('GOT HISTORY DATA', JSON.stringify(historyData, undefined, 2));
      // SessionStorage.removeItem(sessionStorageHistoryKey);
      return historyData;
    } catch (error) {
      logger.error('Unable to retrieve history data from the session storage', error);
    }
    return null;
  };

  const defineInitialHistory = (initialHistoryState: VueRouterHistoryState) => {
    routerHistory.value = {
      [initialHistoryState.position]: new RouterHistoryRecord({
        post: initialPostData,
        hash: initialPostHash,
        state: initialHistoryState,
        path: currentRoute.fullPath,
      }),
    };
    currentHistoryPosition.value = initialHistoryState.position;
    updateModificationTime();
    logger.debug('INITIAL ROUTER HISTORY', currentHistoryPosition.value, { ...routerHistory.value[initialHistoryState.position] });
  };

  let pendingInitTransition: TransitionType|undefined;

  if (isVueRouterHistoryState(initialHistoryState)) {
    defineInitialHistory(initialHistoryState);
  } else {
    logger.info('HISTORY YET EMPTY, INSTALLING WATCH ON CURRENT ROUTE', {
      currentRoute,
      historyState: { ...vueRouterHistory.state },
    });
    const stop = watch(
      currentRoute,
      (newValue, oldValue) => {
        stop();
        logger.debug('INITIAL ROUTE WATCHER', newValue, oldValue);
        const initialHistoryState = window?.history?.state;
        if (!isVueRouterHistoryState(initialHistoryState)) {
          let historyString: string;
          try {
            historyString = JSON.stringify(window?.history, null, 2);
          } catch {
            historyString = '';
          }
          logger.error('Window history state has not been set up.', { ...(window?.history || {}) });
          const error = new HistoryStoreSetupError('Window history state has not been set up: ' + historyString);
          error.context.type = storeId;
          return errorHandler(error);
        }
        defineInitialHistory(initialHistoryState);
        if (pendingInitTransition !== undefined) {
          finishHistoryAction(currentRoute);
          pendingInitTransition = undefined;
        } else if (pendingHistoryPosition.value === -1) {
          pendingHistoryPosition.value = currentHistoryPosition.value; // ?? really
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
   *
   * @param route The current route.
   */
  function adjustDocumentTitle(route: RouteLocationGeneric) {
    const titleElement = document.querySelector('head title')!;
    const originalTitleAttribute = appName + '-original-title';
    if (!titleElement.getAttribute(originalTitleAttribute)) {
      titleElement.setAttribute(originalTitleAttribute, titleElement.textContent!.trim());
    }
    const originalTitle = titleElement.getAttribute(originalTitleAttribute);

    let titleSupplement: string;
    if (route.path === '/') {
      titleSupplement = t(appName, 'Home');
    } else {
      const routeParams = sanitizePostData({ ...route.params });
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
    const key = window?.history?.state?.position || 'initial';
    pendingHistoryAction.value = action;
    pendingHistoryData.value = post || {};
    hash = pendingHistoryHash.value = hash || generatePostHash(pendingHistoryData.value);
    pendingHistoryPosition.value = key;
    logger.debug('scheduleHistoryAction()', {
      action,
      key,
      currentHistoryPosition: currentHistoryPosition.value,
      pendingHistoryPosition: pendingHistoryPosition.value,
      post,
      hash,
      routerHistory: { ...routerHistory.value },
      requestData: { ...requestData },
    });
    if (action !== 'pop'
        && currentHistoryPosition.value !== -1
        && pendingHistoryPosition.value !== currentHistoryPosition.value) {
      logger.trace('SCHEDULE HISTORY KEY MISMATCH', pendingHistoryPosition.value, currentHistoryPosition.value);
    }
    return pendingHistoryHash.value;
  }

  /**
   * @param post TBD.
   *
   * @param hash TBD.
   */
  function scheduleHistoryPush(post: object, hash?: string): string {
    return scheduleHistoryAction(HistoryActionPush, post, hash);
  }

  /**
   * @param post TBD.
   *
   * @param hash TBD.
   */
  function scheduleHistoryReplace(post: object, hash?: string): string {
    return scheduleHistoryAction(HistoryActionReplace, post, hash);
  }

  /**
   * @param resolve TBD.
   */
  function terminateHistoryAction<E extends AppError>(resolve: boolean|E) {
    pendingHistoryAction.value = undefined;
    pendingHistoryData.value = undefined;
    pendingHistoryHash.value = undefined;
    pendingHistoryPosition.value = undefined;
    logger.debug('terminateHistoryAction()', {
      resolve,
      history: JSON.parse(JSON.stringify(routerHistory.value)),
      currentHistoryPosition: currentHistoryPosition.value,
      currentHistoryState: { ...(currentHistoryState.value || { undefined }) },
    });
    settleMutationPromise(resolve);
  }

  /** TBD. */
  function clearHistoryAction() {
    terminateHistoryAction(true);
  }

  /**
   * @param error TBD.
   */
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
        keys: [...routerHistoryPositions.value],
        history: { ...routerHistory },
        key: currentHistoryPosition.value,
        index: currentHistoryIndex.value,
      });
      errorHandler(new HistoryStoreConsistencyError(
        t(appName, 'Unable to find key {key} in current history stack.', { key: currentHistoryPosition.value }),
      ));
      return false;
    }
    const keysToDelete = routerHistoryPositions.value.slice(currentHistoryIndex.value + 1);
    logger.debug('Removing history tail', {
      currentHistoryPosition: currentHistoryPosition.value,
      currentHistoryIndex: currentHistoryIndex.value,
      history: { ...routerHistory.value },
      keysToDelete,
      routerHistoryPositions: [...routerHistoryPositions.value],
    });
    for (const key of keysToDelete) {
      logger.debug('DELETING HISTORY ENTRY', {
        key,
        entry: { ...routerHistory.value[key] },
      });
      delete routerHistory.value[key];
    }
  }

  let mutationLock = Promise.withResolvers<void>();
  mutationLock.resolve();

  const aquireMutationLock = async () => {
    let promise: Promise<void>;
    let count = 0;
    do {
      logger.debug(3, 'ATTEMPT TO AQUIRE MUTATION LOCK', {
        count: ++count,
        mutationLock,
      });
      await (promise = mutationLock.promise);
    } while (promise !== mutationLock.promise);
    logger.debug(3, 'AQUIRED MUTATION LOCK', {
      count,
      mutationLock,
    });
    mutationLock = Promise.withResolvers<void>();
    return mutationLock;
  };

  const releaseMutationLock = () => {
    logger.debug(3, 'RELEASE MUTATION LOCK');
    mutationLock.resolve();
  };

  let mutationPromise: undefined|PromiseWithResolvers<void>;

  const scheduleMutationPromise = async () => {
    if (mutationPromise) {
      let promise: Promise<void>;
      do {
        await (promise = mutationPromise.promise);
      } while (mutationPromise && promise !== mutationPromise.promise);
    }
    logger.debug('Schedule mutation promise');
    mutationPromise = Promise.withResolvers<void>();
    return mutationPromise;
  };

  /**
   * @param arg TBD.
   */
  function settleMutationPromise<E extends AppError>(arg: boolean|E = true) {
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
  }

  /**
   * Called after route completion. Unfortunately the RouterLink Vue
   * component does not provide means to propagate the kind of
   * history-state action -- push or replace -- to the available
   * callback handlers. Hence the logic is:
   *
   * - if pendingHistoryAction is defined, use its value else look at
   * - window.history.state.position,if defined and equal to the current *
   *   (i.e. previous) key, then assume that the history state has
   *   been replaced, otherwise assume a push.
   *
   * @param route Current root.
   *
   * @param from Originating route, if any.
   */
  function finishHistoryAction(route: RouteLocationNormalizedGeneric, from?: RouteLocationNormalizedGeneric) {
    const transition = route.transition;
    const windowHistoryState = { ...(window?.history?.state ?? {}) };
    const position = windowHistoryState.position ?? -1;
    const history = routerHistory.value;
    logger.debug('ON HISTORY FINISH', {
      windowHistoryState,
      position,
      transition,
      to: { ...route },
      from: from ? { ...from } : undefined,
      currentHistoryIndex: currentHistoryIndex.value,
      currentHistoryPosition: currentHistoryPosition.value,
      pendingHistoryAction: pendingHistoryAction.value,
      pendingHistoryPosition: pendingHistoryPosition.value,
      pendingHistoryData: pendingHistoryData.value,
      pendingHistoryHash: pendingHistoryHash.value,
      currentHistoryState: { ...currentHistoryState.value },
      history: { ...history },
      historyOfPosition: { ...(history?.['' + position] || { undefined: true }) },
      historyPositions: [...routerHistoryPositions.value],
      requestData: { ...requestData },
    });
    if (routerHistoryPositions.value.length === 0) {
      logger.info('POSTPONING HISTORY FINISH CALL UNTIL AFTER INIT');
      pendingInitTransition = transition;
      return;
    }
    if (!isVueRouterHistoryState(windowHistoryState)) {
      return errorHandler(
        new HistoryStoreSetupError(t(appName, 'Window history state seems uninitialized')),
      );
    }
    adjustDocumentTitle(currentRoute);
    if (transition === 'replace' && pendingHistoryPosition.value === -1) {
      // just replace the positions and be gone.
      if (routerHistoryPositions.value.length > 1) {
        return errorHandler(
          new HistoryStoreSetupError(t(appName, 'History already contains more than the initial setup item.')),
        );
      }
      if (currentHistoryPosition.value !== position) {
        const currentState = currentHistoryState.value;
        delete history[currentHistoryPosition.value];
        history[position] = currentState;
        currentState.replaceState(windowHistoryState);
        currentHistoryPosition.value = position;
        // logger.debug('Adjusted initial history position', JSON.parse(JSON.stringify(history)), { ...currentHistoryState.value });
      }
      if (currentHistoryState.value.path !== route.fullPath) {
        logger.info('Replacing route path', currentHistoryState.value.path, route.fullPath);
        currentHistoryState.value.path = route.fullPath;
      }
      updateModificationTime();
      clearHistoryAction();

      return;
    }

    if (pendingHistoryAction.value && pendingHistoryAction.value !== transition) {
      logger.error('PENDING HISTORY ACTION DOES NOT MATCH ACTUAL TRANSITION', {
        pendingHistoryAction: pendingHistoryAction.value,
        transition,
      });
      // reset ...
      clearHistoryAction();
    }
    // TODO: is this still needed?
    if (pendingHistoryAction.value === 'replace' && position !== currentHistoryPosition.value && currentHistoryPosition.value !== -1) {
      logger.trace('EXPLICIT HISTORY REPLACE REQUESTED, BUT CURRENT HISTORY IS GONE', {
        position,
        pendingHistoryPosition: pendingHistoryPosition.value,
        currentHistoryPosition: currentHistoryPosition.value,
        history: { ...history },
      });
      cancelHistoryAction();
    }
    if (!pendingHistoryAction.value) {
      if (transition !== HistoryActionUnknown) {
        pendingHistoryAction.value = transition;
        pendingHistoryData.value = {};
      } else if (position === pendingHistoryPosition.value) {
        // replace action from RouterLink
        pendingHistoryAction.value = HistoryActionReplace;
      } else if (history[position]) {
        // 'pop' action, back or forward
        pendingHistoryAction.value = HistoryActionPop;
      } else {
        // assume 'push'
        pendingHistoryAction.value = HistoryActionPush;
      }
      logger.debug('TWEAKED HISTORY ACTION IS', {
        pendingHistoryAction: pendingHistoryAction.value,
        position,
        pendingHistoryPosition: pendingHistoryPosition.value,
        currentHistoryPosition: currentHistoryPosition.value,
        historyAtPosition: history?.[position],
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
        const position = window.history.state.position;
        history[position] = new RouterHistoryRecord({
          hash: pendingHistoryHash.value,
          post: pendingHistoryData.value,
          state: windowHistoryState,
          path: route.fullPath,
        });
        currentHistoryPosition.value = position;
        updateModificationTime();
        break;
      }
      case HistoryActionReplace: {
        if (position !== currentHistoryPosition.value) {
          logger.debug('BEFORE ADJUST POSITIONS', {
            position,
            currentHistoryPosition: { ...history[currentHistoryPosition.value] },
            historyAtPosition: history?.[position],
          });
          history[position] = history[currentHistoryPosition.value];
          logger.debug('CURRENT STATE 0', { ...history[position] });
          delete history[currentHistoryPosition.value];
          logger.debug('CURRENT STATE 1', { ...history[position] });
          currentHistoryPosition.value = position;
          history[position].replaceState(windowHistoryState);
          logger.debug('AFTER ADJUST POSITIONS', history);
        }
        history[position].replaceHash({
          hash: pendingHistoryHash.value,
          post: pendingHistoryData.value,
        });
        history[position].path = route.fullPath;
        updateModificationTime();
        break;
      }
      case HistoryActionPop: {
        currentHistoryPosition.value = window.history?.state?.position ?? -1;
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
    for (const [position, record] of Object.entries(routerHistory.value)) {
      if (+position !== +record.state.position) {
        logger.trace('SELF INCONSISTENCY', position, { ...record }, { ...routerHistory });
      }
    }
    clearHistoryAction();
    logger.debug('finishHistoryAction()', {
      atBase: atHistoryBase.value,
      atTop: atHistoryTop.value,
      currentHistoryIndex: currentHistoryIndex.value,
      currentHistoryPosition: currentHistoryPosition.value,
      currentHistoryState: { ...currentHistoryState.value },
      requestData: { ...requestData },
      currentPostData: { ...(currentHistoryState.value?.post || {}) },
      routerHistory: { ...routerHistory.value },
      routerHistoryPositions: [...routerHistoryPositions.value],
      windowHistoryState: window?.history?.state,
    });
  }

  /**
   * Flat array of available history states in the database. Entries
     are the time-stamps.
   */
  const savedHistoryStates = ref<number[]>([]);
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  axios.get<any, AxiosResponse<number[]>>(generateAppUrl(`${controllerBasePath}/${getTimestamps}`))
    .then((response) => {
      savedHistoryStates.value = response.data.map((stamp) => +(+stamp).toFixed(3));
      logger.info('SAVE HISTORY STATES', { savedHistoryStates: savedHistoryStates.value });
    })
    .catch((e) => {
      const error = new HistoryStorePersistenceError(
        t(appName, 'Unable to load the timestamps of the available history states.'),
        { cause: e },
      );
      errorHandler(error);
    });

  const loadHistoryData = async <T extends FetchAll|number, M extends string>(timestamp: T, modeOrPosition: M): Promise<undefined|LoadHistoryDataType<T, M>> => {
    const url = generateAppUrl(`${controllerBasePath}/{timestamp}/{modeOrPosition}`, {
      timestamp,
      modeOrPosition,
    });
    try {
      const response: AxiosResponse<LoadHistoryDataType<T, M>> = await axios.get(url);
      return response.data;
    } catch (e) {
      let message: string;
      if (timestamp === 'all') {
        message = t(appName, 'Unable to load the available history states.');
      } else if (modeOrPosition === 'shallow' || modeOrPosition === 'deep') {
        message = t(appName, 'Unable to load the history states at time {time} (timestamp: {timestamp}).', {
          time: moment((timestamp as number) * 1000).format('LLL'),
          timestamp,
        });
      } else {
        message = t(appName, 'Unable to load the history state data for "{position}" at time {time} (timestamp: {timestamp}).', {
          position: modeOrPosition,
          time: moment((timestamp as number) * 1000).format('LLL'),
          timestamp,
        });
      }
      errorHandler(new HistoryStorePersistenceError(message, { cause: e }));
    }
  };

  const loadHistoryState = (timestamp: number, modeOrPosition: FetchMode = 'shallow') => loadHistoryData(timestamp, modeOrPosition);

  function loadHistoryStates(): ReturnType<typeof loadHistoryData<'all', 'shallow'>>;
  function loadHistoryStates<M extends FetchMode>(mode: M): ReturnType<typeof loadHistoryData<'all', M>>;
  /**
   * @param mode TBD.
   */
  function loadHistoryStates(mode: FetchMode = 'shallow') {
    return loadHistoryData('all', mode);
  }
  const loadHistoryEntry = (timestamp: number, position: string) => loadHistoryData(timestamp, position);

  /**
   * Collect the current history state into one JSON serializatble
   * object for persisting into databases or the browser's
   * localStorage area. localStorage is for one machine and one local
   * user, storing it in the database enables a restore from another
   * client machine.
   */
  function prepareHistorySaveRecord(): HistoryPersistenceRecord {
    // logger.debug('PREPARE HISTORY SAVE', JSON.stringify({
    //   position: currentHistoryPosition.value,
    //   currentState: { ...currentHistoryState.value },
    //   currentRequestData: { ...requestData[currentHistoryState.value.hash] },
    // }, undefined, 2));
    return {
      position: currentHistoryPosition.value,
      requestData, // the post data proper
      history: routerHistory.value,
    };
  }

  /**
   * Save the current history data to the DB.
   */
  const saveHistoryData = async () => {
    // logger.info('PREPARED HISTORY DATA', JSON.stringify(prepareHistorySaveRecord(), undefined, 2));
    const historySaveData = prepareHistorySaveRecord();
    const url = generateAppUrl(
      `${controllerBasePath}/{timestamp}`,
      {
        timestamp: modificationTime.value,
      },
    );
    try {
      const response: AxiosResponse<{ message?: string }> = await axios.put(url, historySaveData);
      if (response.data.message) {
        showMessage(response.data.message);
      }
      saveTime.value = modificationTime.value;
      // if (!savedHistoryStates.value.includes(modificationTime.value)) {
      //   ???
      // }
    } catch (e: unknown) {
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
   *
   * @param timestamp Unix timestamp in seconds.
   */
  const deleteHistoryState = async (timestamp: number) => {
    const url = generateAppUrl(`${controllerBasePath}/{timestamp}`, { timestamp });
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
    chain: Record<string, RouterHistoryState<'deep'>>,
    posPosition: number,
  ) => {
    const posEntry = chain[posPosition];
    if (!posEntry) {
      errorHandler(
        new HistoryStoreMutationError(
          t(appName, 'The position "{posPosition}" of the final destination does not point into the provided history chain.', {
            posPosition,
          }),
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
    });
    if (inhibitRouterTransition) {
      // Note: just calling next(false) would still initiate either a
      // replace or a push transition which we do not want
      // here. Instead we throw a special error which simply aborts
      // the navigation and react on it in the error handler below
      logger.debug('INHIBIT ROUTER TRANSITION');
      throw new HistoryStoreNavigationInhibitRequest();
    }
    next();
  });

  // onError does catch anything __except__ routing errors.
  router.onError((error) => {
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
  });

  /**
   * Gracefully "allow" duplicated navigation on pop. The router still
   * aborts the navigation, however, we still have to update our
   * history stack state. The history transition after a 'pop' event
   * has no other abort handler, so this handler does not interfere
   * with any other abort handlers.
   */
  // router.onNavigationFailure((error: NavigationFailure) => {
  router.afterEach((to, from, error) => {
    if (to.transition === HistoryActionPop && isNavigationFailure(error, NavigationFailureType.duplicated)) {
      logger.debug('Finish history action on duplicated navigation.', { error });
      finishHistoryAction(to, from);
    }
  });

  /**
   * Push the given history chain to on top of the current state and
   * navigate then to the given location.
   *
   * In order to keep the states consistent we have to adjust the positions
   * to start just after the current index.
   *
   * @param chain History data to append, including post-data.
   *
   * @param posPosition Position to finally go to.
   *
   * @param params Parameters.
   *
   * @param params.replaceCurrent Whether to replace the current history
   * state by the first state of chain.
   *
   * @param params.mutationLock Already aquired mutation lock from caller
   */
  const pushHistoryStack = async (
    chain: Record<number, RouterHistoryState<'deep'>>,
    posPosition: number,
    params: { replaceCurrent?: boolean; mutationLock?: PromiseWithResolvers<void> } = { replaceCurrent: false },
  ) => {
    if (!validateHistoryMutation(chain, posPosition)) {
      return;
    }
    // the check above also ensures that chain contains at least one state
    const { replaceCurrent, mutationLock } = params;
    if (!mutationLock) {
      await aquireMutationLock();
    }
    removeHistoryTail();
    const history = routerHistory.value;

    // sort the positions ascending
    const positions = Object.keys(chain).map((a) => +a).sort((a, b) => a - b);

    if (replaceCurrent) {
      logger.debug('REPLACE CURRENT STATE BY FIRST STATE');
      const firstPosition = positions.shift()!; // we know the array is non-empty at this point
      const entry = chain[firstPosition];

      if (positions.length === 0) {
        // edge case: just replace using the regular vue router
        // replace functionality and skip the remainder of this
        // function.
        const resolved = router.resolve(entry.path, 'unknown');
        const params = sanitizePostData({ ...entry.post, ...resolved.params });
        const hash = entry.hash;
        const force = uuidv4();
        const location = {
          name: resolved.name!, // @todo error handling for route.name
          params,
          query: { hash, [force]: 'force' },
        };
        try {
          scheduleHistoryReplace(params);
          await router.replace(location);
          delete location.query[force];
          scheduleHistoryReplace(params);
          await router.replace(location);
        } catch (error) {
          errorHandler(
            new HistoryStoreMutationError(
              t(appName, 'Unable to replace the current view.'),
              { cause: error },
            ),
          );
        }
        releaseMutationLock();
        return;
      }

      // To there is at least one additional state. Just install the
      // post-data and path into the current history state.
      currentHistoryState.value.path = entry.path;
      currentHistoryState.value.replaceHash(entry);
      // bypass routing
      const url = generateAppUrl(entry.path.replace(/^\/+/, ''));
      logger.debug('REPLACE HISTORY STATE', { state: window.history.state, url });
      window.history.replaceState(window.history.state, '', url);
    }

    // Compute the offset from the tail to the desired position
    const jump = positions.indexOf(posPosition) + 1 - positions.length;
    logger.debug('JUMP COMPUTATION', jump, positions.indexOf(posPosition), positions.length);
    if (jump === 0) {
      // if jump is 0 we finally have to do a router.push(), as go(0) reloads the page
      positions.pop();
    }

    if (positions.length > 0) {
      // we need to tweak the positions of the given chain
      let counter = 0;
      const offset = currentHistoryPosition.value;
      const positionMap = Object.fromEntries(positions.map((position) => [position, (+offset + ++counter)])) as Record<number, number>;
      logger.debug('POSITIONMAP', positionMap);
      for (const position of positions) {
        const entry = chain[position];
        const windowHistoryState = { ...entry.state, position: positionMap[position] };
        history[positionMap[position]] = new RouterHistoryRecord({
          state: windowHistoryState,
          hash: entry.hash,
          post: entry.post,
          path: entry.path,
        });
        logger.debug('HISTORY DURING MUTATION', position, positionMap[position], { ...history });
        currentHistoryPosition.value = positionMap[position];
      }

      logger.debug('HISTORY AFTER INTERNAL STATE MUTATION', { ...history });

      // First push to the window history ignoring the vue-router
      for (const [position, mappedPosition] of Object.entries(positionMap)) {
        const entry = chain[position];
        const url = generateAppUrl(entry.path.replace(/^\/+/, ''));
        const windowHistoryState = { ...entry.state, position: mappedPosition };
        window.history.pushState(windowHistoryState, '', url);
        const resolved = router.resolve(entry.path, 'unknown');
        adjustDocumentTitle(resolved);
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
        // Push the final state throught the vue-router to avoid a
        // reload by go(0).  This ignores the stored old window
        // history state as the vue router will generate a new one.
        logger.debug('PUSH FINAL STATE AS REQUESTED POS IS LAST ONE', { entry: { ...chain[posPosition] } });
        const entry = chain[posPosition];
        const resolved = router.resolve(entry.path, 'unknown');
        const params = sanitizePostData({ ...entry.post, ...resolved.params });
        const hash = entry.hash;
        const force = uuidv4();
        const location = {
          name: resolved.name!, // @todo error handling
          params,
          query: { hash, [force]: 'force' },
        };
        // push replace combo in order to avoid duplicated navigation exceptions.
        scheduleHistoryPush(params);
        await router.push(location);
        delete location.query[force];
        scheduleHistoryReplace(params);
        await router.replace(location);
      } catch (error) {
        errorHandler(
          new HistoryStoreMutationError(
            t(appName, 'Unable to push the desired view.'),
            { cause: error },
          ),
        );
      }
    }
    releaseMutationLock();
  };

  /**
   * Like pushHistoryStack(), but first navigate to the end of the stored history, if necessary.
   *
   * @param chain History stack.
   *
   * @param posPosition Position to finally go to.
   */
  const appendHistoryStack = async (chain: Record<number, RouterHistoryState<'deep'>>, posPosition: number) => {
    if (!validateHistoryMutation(chain, posPosition)) {
      return;
    }
    const mutationLock = await aquireMutationLock();
    // navigate first to the top of the stack.
    const counter = routerHistoryPositions.value.length - currentHistoryIndex.value - 1;
    if (counter > 0) {
      try {
        const mutationPromise = await scheduleMutationPromise();
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
    return pushHistoryStack(chain, posPosition, { mutationLock });
  };

  /**
   * Like pushHistoryStack(), but first navigate to the start of the stored history, if necessary.
   *
   * @param chain History stack.
   *
   * @param posPosition Position to finally go to.
   */
  const replaceHistoryStack = async (chain: Record<number, RouterHistoryState<'deep'>>, posPosition: number) => {
    validateHistoryMutation(chain, posPosition);
    const mutationLock = await aquireMutationLock();
    // navigate first to the start of the stack.
    const counter = -currentHistoryIndex.value;
    if (counter < 0) {
      try {
        const mutationPromise = await scheduleMutationPromise();
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
    return pushHistoryStack(chain, posPosition, { replaceCurrent: true, mutationLock });
  };

  // If available
  //
  // - verify the page URL matches the URL saved in the session storage
  // - use replaceHistoryStack -- maybe tweak that ...
  // logger.debug('HISTORY DATA FROM SESSION STORAGE', getSessionStorageHistoryData());

  router.isReady().then(() => {
    logger.debug('ON ROUTER READY HOOK', {
      router,
      historyState: { ...vueRouterHistory.state },
    });

    // try load history from session storage ...
    const historyData = getSessionStorageHistoryData();
    if (historyData
        && historyData.history[historyData.position]
        && historyData.history[historyData.position].path === currentRoute.fullPath) {
      logger.debug('Try load history data from browser session');
      for (const entry of Object.values(historyData.history)) {
        entry.post = historyData.requestData[entry.hash];
      }
      replaceHistoryStack(historyData.history, historyData.position);
    }
  });

  return {
    logger: loggerRef,
    errorHandler,
    pushErrorHandler: errorHandlerProvider.pushHandler,
    popErrorHandler: errorHandlerProvider.popHandler,
    currentRoute,
    routerHistory,
    routerHistoryPositions,
    currentHistoryPosition,
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
