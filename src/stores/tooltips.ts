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
import { translate as t } from '@nextcloud/l10n';
import { defineStore } from 'pinia';
import {
  reactive,
  ref,
  set as vueSet,
  watch,
} from 'vue';
import type { Ref } from 'vue';
import { tooltips } from '../util/tooltips.ts';
import Console from '../util/console.ts';

const storeId = 'tooltips';

const logger = new Console(storeId);

export default defineStore(storeId, () => {

  const tooltipsData: Record<string, string> = reactive({});

  const failedKeys = ref<string[]>([]);
  const pendingKeys = ref<string[]>([]);

  let lock = Promise.resolve(true);

  const provideTooltips = (keys: string[]) => {

    const pending: string[] = [];
    for (const key of keys) {
      if (tooltipsData[key] === undefined) {
        tooltipsData[key] = '';
        pending.push(key);
      }
    }

    lock = new Promise<boolean>((resolve) => {
      pendingKeys.value.splice(pendingKeys.value.length, 0, ...pending);
      resolve(true)
    })
  };

  watch(() => pendingKeys, async () =>
    {
      if (pendingKeys.value.length === 0) {
        return;
      }
      await lock;
      const requestedTooltips = [...pendingKeys.value];
      pendingKeys.value = [];
      logger.info('PENDING KEYS CHANGED', { requestedTooltips });
      const newTooltips = await tooltips(requestedTooltips);
      for (const [key, tooltip] of Object.entries(newTooltips)) {
        if (!tooltip) {
          failedKeys.value.push(key);
          tooltipsData[key] = t(appName, 'No information available'); // not for production, this would annoy the user
        } else {
          tooltipsData[key] = tooltip;
        }
      }
      logger.info('TOOLTIPS AFTER FETCHING', tooltipsData);
    },
    { deep: true },
  );

  return {
    provideTooltips,
    tooltipsData,
    failedKeys,
    pendingKeys,
  };
});
