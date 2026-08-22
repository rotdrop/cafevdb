/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import {
  OPERATION_CHANGE,
  OPERATION_COPY_ADD,
  OPERATION_DELETE,
  OPERATION_LIST,
  OPERATION_VIEW,
} from '../../build/ts-types/php-modules/PageRenderer/DataConstants.ts';
import $, { isJQuery, jq } from './jquery.ts';
import {
  defaultSelector as pmeDefaultSelector,
  prefix as pmePrefix,
  ucPrefix as PMEPrefix,
} from './pme-state.ts';

const pmeFormViewSuffixes = [
  OPERATION_LIST,
  OPERATION_VIEW,
  OPERATION_DELETE,
];

const pmeFormEditSuffixes = [
  OPERATION_CHANGE,
  OPERATION_COPY_ADD,
];

/**
 * Generate a string with PME_sys_.... prefix.
 *
 * @param token TBD.
 */
const pmeSys = function(token: string) {
  return PMEPrefix + '_sys_' + token;
};

/**
 * Generate a string with PME_data_.... prefix.
 *
 * @param token TBD.
 */
const pmeData = function(token: string) {
  return PMEPrefix + '_data_' + token;
};

/**
 * Generate a string with pme-.... prefix.
 *
 * @param token TBD.
 */
function pmeToken(token: string): string;
function pmeToken(token: string[]): string[];
/**
 * Add the pme-prefix to the given string, separated by a dash.
 *
 * @param token TBD.
 */
function pmeToken(token: string|string[]): string|string[] {
  if (Array.isArray(token)) {
    return token.map((value) => pmeToken(value)) as string[];
  }
  return pmePrefix + '-' + token;
}

/**
 * Generate an id selector with pme-.... prefix.
 *
 * @param token TBD.
 */
const pmeIdSelector = function(token: string) {
  return '#' + pmeToken(token);
};

/**
 * Generate a class selector with pme-.... prefix.
 *
 * @param element TBD.
 *
 * @param token TBD.
 */
const pmeClassSelector = function(element: string, token: string) {
  return element + '.' + pmeToken(token);
};

/**
 * Generate a class selector with pme-.... prefix.
 *
 * @param element TBD.
 */
const pmeInputClassSelector = (element?: string) => pmeClassSelector(element || '', 'input');

/**
 * Generate a compound class selector with pme-.... prefix.
 *
 * @param element TBD.
 *
 * @param tokens TBD.
 */
const pmeClassSelectors = function(element: string, tokens: string[]) {
  const elements = tokens.map(function(token) {
    return pmeClassSelector(element, token);
  });
  return elements.join(',');
};

/**
 * Generate a name selector with PME_sys_.... prefix.
 *
 * @param element TBD.
 *
 * @param token TBD.
 *
 * @param [modifier] TBD.
 */
const pmeSysNameSelector = function(element: string, token: string, modifier?: string) {
  if (modifier === undefined) {
    modifier = '';
  }
  return element + '[name' + modifier + '="' + pmeSys(token) + '"]';
};

/**
 * Generate a compound name selector with PME_sys_.... prefix.
 *
 * @param element TBD.
 *
 * @param tokens TBD.
 */
const pmeSysNameSelectors = function(element: string, tokens: string[]) {
  const elements = tokens.map(function(token) {
    return pmeSysNameSelector(element, token);
  });
  return elements.join(',');
};

/**
 * Generate a navigation selector with pme-.... prefix.
 *
 * @param token TBD.
 */
const pmeNavigationSelector = function(token: string) {
  return '.' + pmeToken('navigation') + '  ' + pmeClassSelector('input', token);
};

/**
 * Selector for main form.
 */
const pmeFormSelector = pmeClassSelector('form', 'form');

/**
 * Selector for main table
 */
const pmeTableSelector = pmeClassSelector('table', 'main');

/**
 * Selector for input.
 */
const pmeInputSelector = pmeInputClassSelector('input');

/**
 * Selector for select input.
 */
const pmeSelectInputSelector = pmeInputClassSelector('select');

/**
 * Selector for textarea input.
 */
const pmeTextareaInputSelector = pmeInputClassSelector('textarea');

/**
 * Selector for key.
 */
const pmeKeySelector = 'td.' + pmeToken('key');

/**
 * Selector for value.
 */
const pmeValueSelector = 'td.' + pmeToken('value');

/**
 * Selector for display cell.
 */
const pmeCellSelector = 'td.' + pmeToken('cell');

/**
 * Selector for filter
 */
const pmeFilterSelector = pmeToken('filter');

/**
 * Selector for query-info
 */
const pmeQueryInfoSelector = '.' + pmeToken('queryinfo');

/**
 * Genereate the default selector.
 *
 * @param selector The selector to construct the final
 * selector from. Maybe a jQuery object.
 */
const pmeSelector = function(selector?: string|JQuery) {
  if (typeof selector === 'undefined' || jq(selector).is(pmeDefaultSelector)) {
    selector = pmeDefaultSelector;
  } else if (isJQuery(selector)) {
    const id = selector.attr('id');
    const cssClass = selector.attr('class');
    selector = '';
    if (id) {
      selector = '#' + id;
    } else if (cssClass) {
      selector += '.' + cssClass.split(' ').join('.');
    }
  }
  return selector as string;
};

/**
 * Generate the jQuery object corresponding to the ambient
 * element. If the given argument is already a jQuery object, then
 * just return the argument.
 *
 * @param selector The selector to construct the final
 * selector from. Maybe a jQuery object.
 */
const pmeContainer = (selector?: string|JQuery) => {
  if (typeof selector === 'string' || selector === undefined) {
    return $(pmeSelector(selector));
  } else {
    return selector;
  }
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
  pmeCellSelector as cellSelector,
  pmeClassSelector as classSelector,
  pmeClassSelectors as classSelectors,
  pmeContainer as container,
  pmeData as data,
  pmeDefaultSelector as defaultSelector,
  pmeFilterSelector as filterSelector,
  pmeFormEditSuffixes as formEditSuffixes,
  pmeFormSelector as formSelector,
  pmeFormViewSuffixes as formViewSuffixes,
  pmeIdSelector as idSelector,
  pmeInputClassSelector as inputClassSelector,
  pmeInputSelector as inputSelector,
  pmeKeySelector as keySelector,
  pmeNavigationSelector as navigationSelector,
  pmeQueryInfoSelector as queryInfoSelector,
  pmeSelectInputSelector as selectInputSelector,
  pmeSelector as selector,
  pmeSys as sys,
  pmeSysNameSelector as sysNameSelector,
  pmeSysNameSelectors as sysNameSelectors,
  pmeTableSelector as tableSelector,
  pmeTextareaInputSelector as textareaInputSelector,
  pmeToken as token,
  pmeValueSelector as valueSelector,
};
