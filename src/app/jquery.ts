/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

// jQuery stuff

import localJQuery from 'jquery';

const $ = localJQuery;

// jQuery still installs itself globally ...
// eslint-disable-next-line @nextcloud/no-deprecations
console.debug('JQUERY INSTANCES window / self', {
  // eslint-disable-next-line @nextcloud/no-deprecations
  global: jQuery.fn.jquery,
  local: localJQuery.fn.jquery,
  // eslint-disable-next-line @nextcloud/no-deprecations
  equal: jQuery === localJQuery,
});

/**
 * Type-guard for JQuery<T>.
 *
 * @param arg TBD.
 */
export function isJQuery(arg: unknown): arg is JQuery {
  return arg instanceof localJQuery;
}

export function jq<T extends HTMLElement = HTMLElement>(arg: JQuery<T>): JQuery<T>;
export function jq<T extends HTMLElement = HTMLElement>(arg: T): JQuery<T>;
export function jq(arg?: string): JQuery;
export function jq<T extends HTMLElement = HTMLElement>(arg?: string|T|JQuery<T>): JQuery;
/**
 * Just keep an existing jQuery object but convert selectors and HTML
 * element to jQuery objects.
 *
 * @param arg The thing to pass-through or wrap into an jQuery object.
 */
export function jq<T extends HTMLElement = HTMLElement>(arg?: string|T|JQuery<T>) {
  return isJQuery(arg)
    ? arg
    : ((typeof arg === 'string') ? $(arg) : arg === undefined ? $() : $(arg));
}

export type JQuerySelect = JQuery<HTMLSelectElement>;

/**
 * @param $arg JQuery object.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function isJQuerySelect($arg: any): $arg is JQuerySelect {
  return ($arg as JQuery).is('select');
}

export default localJQuery;
