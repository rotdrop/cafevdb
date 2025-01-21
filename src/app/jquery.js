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

const jQuery = require('jquery');

for (const property of [/* '$', 'jQuery' */]) {
  try {
    Object.defineProperty(window, property, {
      set(value) { throw Error('Trying to set global jQuery property on window'); },
      get: () => jQuery,
      configurable: false,
    });
    console.info('Defined global "' + property + '" property on window.');
  } catch (e) {
    console.trace('Unable to set global "' + property + '" property on window.', e, window[property].fn.jquery, jQuery.fn.jquery);
  }
}

// jQuery still installs itself ...
console.debug('JQUERY INSTANCES window / self', window.jQuery.fn.jquery, jQuery.fn.jquery);
// window.$ = jQuery;
// window.jQuery = jQuery;

export default jQuery;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
