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
import { ref, computed } from 'vue';
import Console from '../util/console.ts';

export class HistorySetupError extends Error {}

export const HistoryActionPush = 'push';
export const HistoryActionPop = 'pop';
export const HistoryActionReplace = 'replace';
export type HistoryAction = typeof HistoryActionPop|typeof HistoryActionPush|typeof HistoryActionReplace;

const storeId = 'history';

export interface RouterHistoryState {
  next: string|null,
  prev: string|null,
  key: string,
  post: Record<string, any>,
  position: number|null,
}

export default defineStore(storeId, () => {
  const consoleRef = ref(new Console(storeId));
  const console = consoleRef.value;

  const initialHistoryIndex = window?.history?.state?.key
  if (!initialHistoryIndex) {
    throw new HistorySetupError('Window history state has not been setup up: ' + window?.history);
  }

  const routerHistory = ref<Record<string, RouterHistoryState> >({
    [initialHistoryIndex]: {
      prev: null,
      next: null,
      post: {},
      key: initialHistoryIndex,
      position: window?.history?.length,
    },
  });
  console.info('INITIAL ROUTER HISTORY', { ...routerHistory.value });
  const currentHistoryIndex = ref(initialHistoryIndex);
  const pendingHistoryData = ref<null|object>(null);
  const pendingHistoryAction = ref<null|HistoryAction>(null);
  const pendingHistoryKey = ref<null|string|number>('initial');
  const currentHistoryState = computed(() => routerHistory.value?.[currentHistoryIndex.value] || null);
  const prevHistoryIndex = computed(() => currentHistoryState.value.prev);
  const nextHistoryIndex = computed(() => currentHistoryState.value.next);
  const prevHistoryState = computed(() => routerHistory.value?.[prevHistoryIndex.value || ''] || null);
  const nextHistoryState = computed(() => routerHistory.value?.[nextHistoryIndex.value || ''] || null);

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
    console.info('scheduleHistoryAction()', {
      action,
      key,
      currentHistoryIndex: currentHistoryIndex.value,
      pendingHistoryKey: pendingHistoryKey.value,
      post,
      routerHistory: routerHistory.value,
    });
    if (currentHistoryIndex.value !== 'initial' && pendingHistoryKey.value !== currentHistoryIndex.value) {
      console.trace('SCHEDULE HISTORY KEY MISTMATCH', pendingHistoryKey.value, currentHistoryIndex.value);
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
    console.info('cancelHistoryAction()', routerHistory.value);
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
    console.info('ON HISTORY FINISH', {
      key,
      keyType: typeof key,
      currentHistoryIndex: currentHistoryIndex.value,
      pendingHistoryAction: pendingHistoryAction.value,
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
      console.trace('EXPLICIT HISTORY REPLACE REQUESTED, BUT CURRENT HISTORY IS GONE', {
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
      console.info('TWEAKED HISTORY ACTION IS', {
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
      let nextKey = history[currentHistoryIndex.value].next;
      while (nextKey) {
        const removeKey = nextKey
        try {
          nextKey = history[nextKey].next
          delete history[removeKey]
        } catch (error: any) {
          console.error('Exception while removing orphan tail on history push', {
            nextKey,
            removeKey,
            history: { ...history },
          })
          break;
        }
      }
      history[currentHistoryIndex.value].next = key;
      currentHistoryIndex.value = key;
    } else if (pendingHistoryAction.value === 'replace') {
      if (key !== currentHistoryIndex.value) {
        console.info('BEFORE ADJUST KEYS', key, currentHistoryIndex.value, { ...history[currentHistoryIndex.value] }, history?.[key]);
        history[key] = history[currentHistoryIndex.value];
        console.info('CURRENT STATE 0', { ...history[key] });
        delete history[currentHistoryIndex.value];
        console.info('CURRENT STATE 1', { ...history[key] });
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
          console.error('Exception during history replace', {
            history: { ...history },
            key,
            next,
            prev,
          })
        }
        console.info('AFTER ADJUST KEYS', history);
      }
      history[key].post = pendingHistoryData.value || {};
    } else {
      currentHistoryIndex.value = window.history?.state?.key || 'initial';
    }
    for (const [key, record] of Object.entries(routerHistory.value)) {
      if (key !== record.key) {
        console.trace('SELF INCONSISTENCY', key, record, routerHistory.value);
      }
      if ((record.next || record.prev)
        && (record.next === record.prev || record.next === record.key || record.prev === record.key)) {
        console.trace('EQUAL KEYS', key, { ...record }, { ...routerHistory.value });
      }
    }
    pendingHistoryData.value = null;
    pendingHistoryAction.value = null;
    pendingHistoryKey.value = null;
    console.info('finishHistoryAction()', {
      currentHistoryIndex: currentHistoryIndex.value,
      currentHistoryState: { ...currentHistoryState.value },
      routerHistory: { ...routerHistory.value },
      windowHistoryState: window?.history?.state,
    });
  }

  return {
    console: consoleRef,
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
  };
});
