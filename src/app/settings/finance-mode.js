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
import './../jquery-cafevdb-tooltips.js';
import { appPrefix } from '../../config.ts';
import { setPersonalUrl } from './../settings-urls.js';
import * as Ajax from './../ajax.js';
import * as PHPMyEdit from './../pme-selectors.js';
import * as Notification from './../notification.js';
import { subscribe } from '../../services/async-event-bus.ts';
import { SET_FINANCE_MODE } from '../../event-bus-events.ts';

require('../../legacy/nextcloud/jquery/requesttoken.js');

subscribe(SET_FINANCE_MODE, (event) => {
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
  $('.finance-mode-container').toggleClass('hidden', !value);
  $('body').toggleClass(appPrefix('finance-mode'), value);
  $('.personal-settings input[type="checkbox"].finance-mode').prop('checked', value);
  $('select.debug-mode').prop('disabled', false).trigger('chosen:updated');
  $.fn.cafevTooltip.remove(); // remove any left-over items.
  globalState.financeMode = value;
  return new Promise((resolve, reject) =>
    $.post(setPersonalUrl('finance-mode'), { value })
      .done(async function(data, ...rest) {
        showMessage(data.message);
        if (globalState.PHPMyEdit !== undefined) {
          const pmeForm = $('#content ' + PHPMyEdit.formSelector);
          pmeForm.each(function(index) {
            const reload = $(this).find(PHPMyEdit.classSelector('input', 'reload')).first();
            reload.trigger('click');
          });
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
