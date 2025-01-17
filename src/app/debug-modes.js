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

import globalState from './globalstate.js';
import $ from './jquery.js';
import { setPersonalUrl } from './settings-urls.js';
import * as Ajax from './ajax.js';
import { selected as selectedValues } from './select-utils.js';
import * as Notification from './notification.js';
import { subscribe } from '@nextcloud/event-bus';
import { SET_DEBUG_MODES } from '../event-bus.ts';

require('../legacy/nextcloud/jquery/requesttoken.js');

subscribe(SET_DEBUG_MODES, (event) => {
  setter(event?.value, event?.showMessage, event?.$select, event?.callbacks);
});

/**
 * @param {object} selection Array of objects with a value attribute.
 *
 * @param {Function} showMessage Custom function for displaying
 * feedback from the controller, defaults to a standard toast popup.
 *
 * @param {jQuery} $select Originating select, may be undefined.
 *
 * @param {object} callbacks Object with done(), fail(), always() properties.
 *
 * @returns {Promise}
 */
const setter = (selection, showMessage, $select, callbacks) => {
  showMessage = showMessage || ((messages) => Notification.messages(messages));
  const values = selection.map(({ value }) => value);
  $('.personal-settings select.debugmode').each(function(index) {
    if (this !== $select?.[0]) {
      selectedValues($(this), values);
    }
  });
  return $.post(setPersonalUrl('debugmode'), { value: selection })
    .done(function(data, ...rest) {
      showMessage(data.message);
      console.log(data);
      globalState.debugModes = data.value;
      if (typeof callbacks?.done === 'function') {
        callbacks.done(data, ...rest);
      }
    })
    .fail(function(xhr, status, errorThrown, ...rest) {
      showMessage(Ajax.failMessage(xhr, status, errorThrown));
      // console.error(data);
      if (typeof callbacks?.fail === 'function') {
        callbacks.fail(xhr, status, errorThrown, ...rest);
      }
    })
    .always(function() {
      if (typeof callbacks?.always === 'function') {
        callbacks.always();
      }
    });
};

export default setter;
