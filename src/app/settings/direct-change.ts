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

import globalState from './../globalstate.ts';
import $ from './../jquery.ts';
import { setPersonalUrl } from './../settings-urls.ts';
import * as Ajax from './../ajax.ts';
import * as Notification from './../notification.ts';
import { subscribe } from '../../services/async-event-bus.ts';
import { SET_DIRECT_CHANGE as EVENT } from '../../event-bus-events.ts';

require('../../legacy/nextcloud/jquery/requesttoken.js');

subscribe(EVENT, (event) => {
  setter(event?.value, event?.showMessage, event?.$control);
});

/**
 * @param value value Value to set.
 *
 * @param showMessage Custom function for displaying
 * feedback from the controller, defaults to a standard toast popup.
 *
 * @param _$control Originating select, may be undefined.
 */
const setter = (value: boolean, showMessage?: typeof Notification.messages, _$control?: JQuery) => {
  showMessage = showMessage || Notification.messages;
  globalState.PHPMyEdit.directChange = value;
  $('.personal-settings input[type="checkbox"].directchange').prop('checked', value);
  return new Promise((resolve, reject) =>
    $.post(setPersonalUrl('directchange'), { value })
      .done(async function(data) {
        showMessage(data.message);
        resolve(data);
      })
      .fail(async function(xhr, status, errorThrown) {
        showMessage(Ajax.failMessage(xhr, status, errorThrown));
        reject(errorThrown);
      }),
  );
};

export default setter;
