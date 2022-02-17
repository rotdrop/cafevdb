/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import $ from './jquery.js';
import {
  sys as pmeSys,
  sysNameSelector as pmeSysNameSelector,
  classSelector as pmeClassSelector,
} from './pme-selectors.js';

const camelCase = require('camelcase');

/**
 * Find the record-id for the given column
 *
 * @param {Object} container Selector or jquery object.
 *
 * @param {string} column Name
 *
 * @returns {mixed} The id value or false if not found.
 */
const pmeRecordValue = function(container, column) {
  // PME_sys_rec[COLUMN]
  // PME_sys_groupby_rec[COLUMN]
  const recordNames = ['rec', 'groupby_rec'];

  column = column.replace(/[A-Z]/g, m => '_' + m.toLowerCase());

  let idValue = -1;
  const formSelector = pmeClassSelector('form', 'form');
  const form = container.is(formSelector)
    ? container
    : container.find(formSelector);
  for (const name of recordNames) {
    idValue = form.find(pmeSysNameSelector('input', name + '[' + column + ']'));
    idValue = idValue.length === 1 ? parseInt(idValue.val()) : -1;
    if (idValue > 0) {
      return idValue; // just take the first one found
    }
  }
  return idValue;
};

/**
 * Find the record id inside the given selector or jQuery collection.
 *
 * @param {String} selector TBD.
 *
 * @param {Object} options TBD.
 *
 * @returns {Object}
 */
const pmeRec = function(selector, options) {
  options = options || { pascalCase: false };
  let munge;
  if (options.camelCase === false) {
    munge = function(key) { return key; };
  } else {
    munge = function(key) { return camelCase(key, options); };
  }
  const records = $(selector).find('input[name^="' + pmeSys('rec') + '"]').serializeArray();
  let result = {};
  for (const rec of records) {
    const key = rec.name.match(/[^[]+\[([^\]]+)\]/);
    if (key && key.length === 2) {
      result[munge(key[1])] = rec.value;
    } else {
      result = rec.value;
    }
  }
  return result;
};

export {
  pmeRecordValue as recordValue,
  pmeRec as rec,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
