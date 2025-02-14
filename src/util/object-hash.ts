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
import type { NormalOption, NotUndefined } from 'object-hash';

const COMPONENT_NAME = 'generateObjectHash';

const logger = new Console(COMPONENT_NAME);

type NormalOptionsExt = NormalOption & {
  exclude: ((key: string, value: any) => boolean)|undefined;
};

const generateObjectHash = (instance: NotUndefined):string => {
  const options: NormalOptionsExt = {
    unorderedArrays: true, // sort arrays
    exclude: (key, value) =>
      key === '__ob__' // vue reactivity hook
        || value === undefined // exclude undefined
        || value === null, // or better debug the code ...
  };
  const result = objectHash(instance, options);
  logger.debug('HASH @ OBJECT', result, instance);
  return result;
};

export default generateObjectHash;
