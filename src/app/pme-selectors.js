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
/**
 * @file
 *
 * PME CSS selector support.
 */

import * as PHPMyEdit from './pme-state.js';
import jQuery from './jquery.js';

const $ = jQuery;

const pmeDefaultSelector = PHPMyEdit.defaultSelector;
const pmePrefix = PHPMyEdit.prefix;
const PMEPrefix = PHPMyEdit.ucPrefix;

/**
 * Generate a string with PME_sys_.... prefix.
 *
 * @param {string} token TBD.
 *
 * @returns {string}
 */
const pmeSys = function(token) {
  return PMEPrefix + '_sys_' + token;
};

/**
 * Generate a string with PME_data_.... prefix.
 *
 * @param {string} token TBD.
 *
 * @returns {string}
 */
const pmeData = function(token) {
  return PMEPrefix + '_data_' + token;
};

/**
 * Generate a string with pme-.... prefix.
 *
 * @param {string} token TBD.
 *
 * @returns {string}
 */
const pmeToken = function(token) {
  return pmePrefix + '-' + token;
};

/**
 * Generate an id selector with pme-.... prefix.
 *
 * @param {string} token TBD.
 *
 * @returns {string}
 */
const pmeIdSelector = function(token) {
  return '#' + pmeToken(token);
};

/**
 * Generate a class selector with pme-.... prefix.
 *
 * @param {string} element TBD.
 *
 * @param {string} token TBD.
 *
 * @returns {string}
 */
const pmeClassSelector = function(element, token) {
  return element + '.' + pmeToken(token);
};

/**
 * Generate a compound class selector with pme-.... prefix.
 *
 * @param {string} element TBD.
 *
 * @param {string} tokens TBD.
 *
 * @returns {string}
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
 * @param {string} element TBD.
 *
 * @param {string} token TBD.
 *
 * @param {string} modifier TBD.
 *
 * @returns {string}
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
 * @param {string} element TBD.
 *
 * @param {string} tokens TBD.
 *
 * @returns {string}
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
 * @param {string} token TBD.
 *
 * @returns {string}
 */
const pmeNavigationSelector = function(token) {
  return '.' + pmeToken('navigation') + '  ' + pmeClassSelector('input', token);
};

/**
 * Selector for main form
 *
 * @returns {string}
 */
const pmeFormSelector = function() {
  return 'form.' + pmeToken('form');
};

/**
 * Selector for main table
 *
 * @returns {string}
 */
const pmeTableSelector = function() {
  return 'table.' + pmeToken('main');
};

/**
 * Genereate the default selector.
 *
 * @param {string} selector The selector to construct the final
 * selector from. Maybe a jQuery object.
 *
 * @returns {string}
 */
const pmeSelector = function(selector) {
  if (typeof selector === 'undefined' || $(selector).is(pmeDefaultSelector)) {
    selector = pmeDefaultSelector;
  } else if (selector instanceof jQuery) {
    const id = selector.attr('id');
    const cssClass = selector.attr('class');
    selector = '';
    if (id) {
      selector = '#' + id;
    } else if (cssClass) {
      selector += '.' + cssClass.split(' ').join('.');
    }
  }
  return selector;
};

/**
 * Generate the jQuery object corresponding to the ambient
 * element. If the given argument is already a jQuery object, then
 * just return the argument.
 *
 * @param {string} selector The selector to construct the final
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
//  * @param {string} selector The selector to construct the final
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

export {
  PHPMyEdit,
  pmeContainer as container,
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
