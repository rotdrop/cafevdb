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

import objectHash from 'object-hash';
import Console from './console.ts';
import type { NormalOption } from 'object-hash';
import type { TemplatePostData } from '@rotdrop/async-nextcloud-event-bus'

const COMPONENT_NAME = 'generateObjectHash';

const logger = new Console(COMPONENT_NAME);

export const HASH_KEY = '__post_data_hash__';
export const FRONTEND_URL_PATH_KEY = '__frontend_url_path__';

type NormalOptionsExt = NormalOption & {
  exclude: ((key: string, value: any, level: number) => boolean)|undefined;
};

const EXCLUDED_KEYS = [
  '__ob__', // vue reactivity hook
];

const POST_DATA_EXCLUDED_KEYS = [
  HASH_KEY, // reserved to hold the hash value
  FRONTEND_URL_PATH_KEY, // the frontend url path
  'hash',
  '_route',
  'renderAs',
];
const TOP_LEVEL_EXCLUDED_KEYS = [
  'template', // url-parameter
  'projectId', // url-parameter
  'projectName', // url-parameter
  ...POST_DATA_EXCLUDED_KEYS,
];

/**
 * Generate a hash from the given data, excluding entries which
 * "belong to the system" or are part of the route.
 */
export const generatePostHash = (postData: TemplatePostData):string => {
  const options: NormalOptionsExt = {
    unorderedArrays: true, // sort arrays
    exclude: (key, value, level) =>
      (level === 0 && TOP_LEVEL_EXCLUDED_KEYS.includes(key))
        || EXCLUDED_KEYS.includes(key)
        || value === undefined // exclude undefined
        || value === null // or better debug the code ...
        || (key === 'musicianId' && +(value as string) === 0),
  };
  const result = objectHash(postData, options);
  logger.debug('HASH @ OBJECT', result, postData);
  return result;
};

const EMPTY_VALUE_KEYS = [
  'projectId',
  'projectName',
  'musicianId',
];

/**
 * Sanitize template parameters
 *
 * - remove 'template'.
 * - remove null and undefined values.
 * - remove value which evaluate to 0 for database ids.
 * - remove empty projectName.
 *
 * @param  params Template / post parameters.
 */
export const sanitizeTemplateParams = (params: TemplatePostData) => Object.fromEntries(
  Object.entries(params).filter(
    ([key, value]) => key !== 'template'
      && value !== null
      && value !== undefined
      && (!EMPTY_VALUE_KEYS.includes(key) || +(value as string) !== 0)
    ,
  ),
);

/**
 * Remove empty values for projectId, projectName and musicianId,
 * remove also all null and undefined values.
 *
 * @param params
 */
export const sanitizePostData = (params: TemplatePostData): TemplatePostData => Object.fromEntries(
  Object.entries(params).filter(
    ([key, value]) => value !== null
      && value !== undefined
      && (!EMPTY_VALUE_KEYS.includes(key) || +(value as string) !== 0)
      && !POST_DATA_EXCLUDED_KEYS.includes(key)
      && !EXCLUDED_KEYS.includes(key),
  ),
) as TemplatePostData;
