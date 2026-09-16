/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { vi } from 'vitest';
import {
  BASE_PATH as controllerBasePath,
  GET_REQUEST_TIMESTAMPS as getTimestamps,
} from '~/build/ts-types/php-modules/Controller/WebBrowserHistoryController.ts';
import generateAppUrl from '~/src/toolkit/util/generate-url.ts';

const baseAppUrl = generateAppUrl('');

const axiosGetters: Record<string, () => unknown> = {};

vi.mock(import('@nextcloud/axios'), async (originalImport) => {
  const originalModule = await originalImport();

  return {
    ...originalModule,
    default: {
      ...originalModule.default,
      get: async (url: string) => {
        if (!url.startsWith(baseAppUrl)) {
          throw new Error(`Url ${url} does not start with app-prefix ${baseAppUrl}`);
        }
        url = url.slice(baseAppUrl.length);
        return {
          data: axiosGetters[url]?.(),
        };
      },
    } as typeof originalModule['default'],
  };
});

export const registerAxiosGetter = (url: string, handler: () => void) => {
  axiosGetters[url] = handler;
};

export const registerHistoryTimestampsGetter = () => {
  registerAxiosGetter(`${controllerBasePath}/${getTimestamps}`, () => []);
};
