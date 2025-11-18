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
import { selected as selectedValues } from './../select-utils.ts';
import * as Notification from './../notification.ts';
import { subscribe } from '../../services/async-event-bus.ts';
import { SET_DEBUG_MODES } from '../../event-bus-events.ts';

require('../../legacy/nextcloud/jquery/requesttoken.js');

subscribe(SET_DEBUG_MODES, (event) => {
  setter(event?.value, event?.showMessage, event?.$control);
});

/**
 * @param selection Array of objects with a value attribute.
 *
 * @param showMessage Custom function for displaying feedback from the
 * controller, defaults to a standard toast popup.
 *
 * @param $control Originating select, may be undefined.
 */
const setter = (
  selection: { value: number }[],
  showMessage?: typeof Notification.messages,
  $control?: JQuery<HTMLSelectElement>,
) => {
  showMessage = showMessage || Notification.messages;
  const values = selection.map(({ value }) => '' + value);
  ($('.personal-settings select.debugmode') as JQuery<HTMLSelectElement>).each(function() {
    if (this !== $control?.[0]) {
      selectedValues($(this), values);
    }
  });
  return new Promise((resolve, reject) =>
    $.post(setPersonalUrl('debugmode'), { value: selection })
      .done(async function(data) {
        showMessage(data.message);
        console.log(data);
        globalState.debugModes = data.value;
        resolve(data);
      })
      .fail(async function(xhr, status, errorThrown) {
        showMessage(Ajax.failMessage(xhr, status, errorThrown));
        reject(errorThrown);
      }),
  );
};

export default setter;
