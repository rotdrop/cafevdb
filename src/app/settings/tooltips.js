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

import globalState from './../globalstate.js';
import $ from './../jquery.js';
import { toolTipsOnOff } from './../cafevdb.js';
import { setPersonalUrl } from './../settings-urls.js';
import * as Ajax from './../ajax.js';
import * as Notification from './../notification.js';
import { subscribe } from '@nextcloud/event-bus';
import { SET_TOOLTIPS_MODE } from '../../event-bus-events.js';

require('../../legacy/nextcloud/jquery/requesttoken.js');

subscribe(SET_TOOLTIPS_MODE, (event) => {
  setter(event?.value, event?.showMessage, event?.$control, event?.callbacks);
});

/**
 * @param {boolean} value Value to set.
 *
 * @param {Function} showMessage Custom function for displaying
 * feedback from the controller, defaults to a standard toast popup.
 *
 * @param {jQuery} $control Originating select, may be undefined.
 *
 * @param {object} callbacks Object with done(), fail(), always() properties.
 *
 * @returns {Promise}
 */
const setter = (value, showMessage, $control, callbacks) => {
  showMessage = showMessage || ((messages) => Notification.messages(messages));
  toolTipsOnOff(value);
  $('.personal-settings input[type="checkbox"].tooltips').prop('checked', globalState.toolTipsEnabled);
  if (globalState.toolTipsEnabled) {
    $('#tooltipbutton').removeClass('tooltips-disabled').addClass('tooltips-enabled');
  } else {
    $('#tooltipbutton').removeClass('tooltips-enabled').addClass('tooltips-disabled');
  }
  return new Promise((resolve, reject) =>
    $.post(setPersonalUrl('tooltips'), { value: globalState.toolTipsEnabled })
      .done(async function(data, ...rest) {
        if (!$control?.is('#tooltipbutton-checkbox')) { // don't annoy with feedback
          showMessage(data.message);
        }
        if (typeof callbacks?.done === 'function') {
          await callbacks.done(data, ...rest);
        }
        if (typeof callbacks?.always === 'function') {
          await callbacks.always(data, ...rest);
        }
        resolve(data);
      })
      .fail(async function(xhr, status, errorThrown, ...rest) {
        showMessage(Ajax.failMessage(xhr, status, errorThrown));
        if (typeof callbacks?.fail === 'function') {
          await callbacks.fail(xhr, status, errorThrown, ...rest);
        }
        if (typeof callbacks?.always === 'function') {
          await callbacks.always(xhr, status, errorThrown, ...rest);
        }
        reject(errorThrown);
      }),
  );
};

export default setter;
