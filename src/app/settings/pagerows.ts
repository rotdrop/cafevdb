/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { subscribe } from '@rotdrop/async-nextcloud-event-bus';
import { SET_PAGE_ROWS } from '../../event-bus-events.ts';
import * as Ajax from './../ajax.ts';
import globalState from './../globalstate.ts';
import $ from './../jquery.ts';
import * as Notification from './../notification.ts';
import { selected as selectedValues } from './../select-utils.ts';
import { setPersonalUrl } from './../settings-urls.ts';

import '../../legacy/nextcloud/jquery/requesttoken.js';

/**
 * @param value Value to store and propagate to all selects.
 *
 * @param showMessage Custom function for displaying
 * feedback from the controller, defaults to a standard toast popup.
 *
 * @param $control Originating select, may be undefined.
 */
const setter = (value: number, showMessage?: typeof Notification.messages, $control?: JQuery<HTMLSelectElement>) => {
  showMessage = showMessage || Notification.messages;
  $('.personal-settings').find<HTMLSelectElement>('select.pagerows').each(function() {
    if (this !== $control?.[0]) {
      selectedValues($(this), '' + value);
    }
  });
  globalState.PHPMyEdit.pageRowsDefault = value;
  return new Promise((resolve, reject) =>
    $.post(setPersonalUrl('pageRowsDefault'), { value })
      .done(function(data) {
        showMessage(data.messages);
        console.log(data);
        resolve(data);
      })
      .fail(async function(xhr, status, errorThrown) {
        await Ajax.handleError(xhr, status, errorThrown);
        // console.error(data);
        reject(xhr);
      }));
};

subscribe(SET_PAGE_ROWS, (event) => {
  return setter(event?.value, event?.showMessage, event?.$control);
});

export default setter;
