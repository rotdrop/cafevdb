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

import { appName } from '../config.ts';
import globalState from '../app/globalstate.ts';
import { translate as t } from '@nextcloud/l10n';
import { defineStore } from 'pinia';
import {
  reactive,
  ref,
  watch,
  set as vueSet,
} from 'vue';
import { tooltips } from '../util/tooltips.ts';
import Console from '../util/console.ts';
import { DEBUG_TOOLTIPS } from '../debug-modes.ts';

const storeId = 'tooltips';

const logger = new Console(storeId);

export default defineStore(storeId, () => {

  const tooltipsData: Record<string, string> = reactive({});

  const failedKeys = ref<string[]>([]);
  const pendingKeys = ref<string[]>([]);
  const loading = ref(false);

  let lock = Promise.resolve(true);

  /**
   * Runs synchronously and provides empty strings for all missing
   * keys. The missing keys are watched for and loaded asynchronously.
   *
   * @param keys String keys to request.
   */
  const provideTooltips = (keys: string[]) => {

    const pending: string[] = [];
    for (const key of keys) {
      if (tooltipsData[key] === undefined) {
        vueSet(tooltipsData, key, '');
        pending.push(key);
      }
    }

    if (pending.length > 0) {
      loading.value = true;
      const { promise, resolve } = Promise.withResolvers<boolean>();
      lock = promise;
      pendingKeys.value.splice(pendingKeys.value.length, 0, ...pending);
      resolve(true);
    }
  };

  const failedTooltipMessage = (key: string) => (globalState.debugMode & DEBUG_TOOLTIPS)
    ? t(appName, 'No information available, fetching the tooltip with key "{key}" failed.', { key })
    : ''; // shut up

  watch(
    () => globalState.debugMode,
    () => {
      for (const key of failedKeys.value) {
        if (tooltipsData[key] !== undefined) {
          vueSet(tooltipsData, key, failedTooltipMessage(key));
        }
      }
    },
  );

  watch(
    () => pendingKeys,
    async () => {
      if (pendingKeys.value.length === 0) {
        loading.value = false;
        return;
      }
      const requestedTooltips = [...pendingKeys.value];
      await lock;
      pendingKeys.value = [];
      const newTooltips = await tooltips(requestedTooltips);
      for (const [key, tooltip] of Object.entries(newTooltips)) {
        if (!tooltip) {
          failedKeys.value.push(key);
          // tooltipsData[key] = failedTooltipMessage(key);
          vueSet(tooltipsData, key, failedTooltipMessage(key));
        } else {
          // tooltipsData[key] = tooltip;
          vueSet(tooltipsData, key, tooltip);
        }
      }
      logger.debug('TOOLTIPS AFTER FETCHING', { tooltips: { ...tooltipsData } });
    },
    { deep: true },
  );

  return {
    provideTooltips,
    tooltipsData,
    failedKeys,
    pendingKeys,
    loading,
  };
});
