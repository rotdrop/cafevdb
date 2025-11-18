/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2022, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { jq } from './jquery.ts';
import {
  sys as pmeSys,
  sysNameSelector as pmeSysNameSelector,
  classSelector as pmeClassSelector,
} from './pme-selectors.ts';
import camelCase from 'camelcase';

/**
 * Find the record-id for the given column
 *
 * @param $container jQuery wrapper.
 *
 * @param column Name
 *
 * @returns The id value or false if not found.
 */
const pmeRecordValue = function($container: JQuery, column: string) {
  // PME_sys_rec[COLUMN]
  // PME_sys_groupby_rec[COLUMN]
  const recordNames = ['rec', 'groupby_rec'];

  column = column.replace(/[A-Z]/g, m => '_' + m.toLowerCase());

  let idValue = -1;
  const formSelector = pmeClassSelector('form', 'form');
  const $form = $container.is(formSelector)
    ? $container
    : $container.find(formSelector);
  for (const name of recordNames) {
    const idValueProvider = $form.find(pmeSysNameSelector('input', name + '[' + column + ']'));
    idValue = idValueProvider.length === 1 ? parseInt(idValueProvider.val() as string) : -1;
    if (idValue > 0) {
      return idValue; // just take the first one found
    }
  }
  return idValue;
};

/**
 * Find the record id inside the given selector or jQuery collection.
 *
 * @param selector TBD.
 */
const pmeRec = (selector: string|JQuery) => {
  const options = { pascalCase: false };
  const records = jq(selector).find('input[name^="' + pmeSys('rec') + '"]').serializeArray();
  const result: string|Record<string, string> = {};
  for (const rec of records) {
    const key = rec.name.match(/[^[]+\[([^\]]+)\]/);
    if (key && key.length === 2) {
      result[camelCase(key[1], options)] = rec.value;
    } else {
      return rec.value;
    }
  }
  return result;
};

export {
  pmeRecordValue as recordValue,
  pmeRec as rec,
};
