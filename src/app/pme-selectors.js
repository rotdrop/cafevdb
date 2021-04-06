/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * @file
 *
 * PME CSS selector support.
 */

import { $, jQuery } from './globals.js';
import * as PHPMyEdit from './pme-state.js';

const camelCase = require('camelcase');

const pmeDefaultSelector = PHPMyEdit.defaultSelector;
const pmePrefix = PHPMyEdit.prefix;
const PMEPrefix = PHPMyEdit.ucPrefix;

/**
 * Generate a string with PME_sys_.... prefix.
 *
 * @param {String} token TBD.
 *
 * @returns {String}
 */
const pmeSys = function(token) {
  return PMEPrefix + '_sys_' + token;
};

/**
 * Generate a string with PME_data_.... prefix.
 *
 * @param {String} token TBD.
 *
 * @returns {String}
 */
const pmeData = function(token) {
  return PMEPrefix + '_data_' + token;
};

/**
 * Generate a string with pme-.... prefix.
 *
 * @param {String} token TBD.
 *
 * @returns {String}
 */
const pmeToken = function(token) {
  return pmePrefix + '-' + token;
};

/**
 * Generate an id selector with pme-.... prefix.
 *
 * @param {String} token TBD.
 *
 * @returns {String}
 */
const pmeIdSelector = function(token) {
  return '#' + pmeToken(token);
};

/**
 * Generate a class selector with pme-.... prefix.
 *
 * @param {String} element TBD.
 *
 * @param {String} token TBD.
 *
 * @returns {String}
 */
const pmeClassSelector = function(element, token) {
  return element + '.' + pmeToken(token);
};

/**
 * Generate a compound class selector with pme-.... prefix.
 *
 * @param {String} element TBD.
 *
 * @param {String} tokens TBD.
 *
 * @returns {String}
 */
const pmeClassSelectors = function(element, tokens) {
  const elements = tokens.map(function(token) {
    return pmeClassSelector(element, token);
  });
  return elements.join(',');
};

/**
 * Generate a name selector with PME_sys_.... prefix.
 *
 * @param {String} element TBD.
 *
 * @param {String} token TBD.
 *
 * @param {String} modifier TBD.
 *
 * @returns {String}
 */
const pmeSysNameSelector = function(element, token, modifier) {
  if (modifier === undefined) {
    modifier = '';
  }
  return element + '[name' + modifier + '="' + pmeSys(token) + '"]';
};

/**
 * Generate a compound name selector with PME_sys_.... prefix.
 *
 * @param {String} element TBD.
 *
 * @param {String} tokens TBD.
 *
 * @returns {String}
 */
const pmeSysNameSelectors = function(element, tokens) {
  const elements = tokens.map(function(token) {
    return pmeSysNameSelector(element, token);
  });
  return elements.join(',');
};

/**
 * Generate a navigation selector with pme-.... prefix.
 *
 * @param {String} token TBD.
 *
 * @returns {String}
 */
const pmeNavigationSelector = function(token) {
  return '.' + pmeToken('navigation') + '  ' + pmeClassSelector('input', token);
};

/**
 * Selector for main form
 *
 * @returns {String}
 */
const pmeFormSelector = function() {
  return 'form.' + pmeToken('form');
};

/**
 * Selector for main table
 *
 * @returns {String}
 */
const pmeTableSelector = function() {
  return 'table.' + pmeToken('main');
};

/**
 * Genereate the default selector.
 *
 * @param {String} selector The selector to construct the final
 * selector from. Maybe a jQuery object.
 *
 * @returns {Strring}
 */
const pmeSelector = function(selector) {
  if (typeof selector === 'undefined') {
    selector = pmeDefaultSelector;
  }
  return selector;
};

/**
 * Generate the jQuery object corresponding to the ambient
 * element. If the given argument is already a jQuery object, then
 * just return the argument.
 *
 * @param {String} selector The selector to construct the final
 * selector from. Maybe a jQuery object.
 *
 * @returns {jQuery}
 */
const pmeContainer = function(selector) {
  let container;
  if (selector instanceof jQuery) {
    container = selector;
  } else {
    selector = pmeSelector(selector);
    container = $(selector);
  }
  return container;
};

// /**
//  * Generate the jQuery object corresponding to the inner container
//  * of the ambient container. If the given argument is already a
//  * jQuery object, then just return its first div child.
//  *
//  * @param {String} selector The selector to construct the final
//  * selector from. Maybe a jQuery object.
//  *
//  * @returns {jQuery}
//  */
// const inner = function(selector) {
//   let container;
//   if (selector instanceof jQuery) {
//     container = selector;
//   } else {
//     selector = pmeSelector(selector);
//     container = $(selector);
//   }
//   return container.children('div:first');
// };

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
  PHPMyEdit,
  pmeContainer as container,
  pmeRec as rec,
  pmeSys as sys,
  pmeData as data,
  pmeToken as token,
  pmeDefaultSelector as defaultSelector,
  pmeSelector as selector,
  pmeFormSelector as formSelector,
  pmeIdSelector as idSelector,
  pmeSysNameSelector as sysNameSelector,
  pmeSysNameSelectors as sysNameSelectors,
  pmeClassSelector as classSelector,
  pmeClassSelectors as classSelectors,
  pmeNavigationSelector as navigationSelector,
  pmeTableSelector as tableSelector,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
