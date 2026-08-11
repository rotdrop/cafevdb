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

import { SET_DEBUG_QUERY_SQL_FILTER } from '../../event-bus-events.ts';
import { subscribe } from '../../services/async-event-bus.ts';
import * as Ajax from './../ajax.ts';
import globalState from './../globalstate.ts';
import $ from './../jquery.ts';
import * as Notification from './../notification.ts';
import { setPersonalUrl } from './../settings-urls.ts';

import '../../legacy/nextcloud/jquery/requesttoken.js';

/**
 * @param value Value to store and propagate to all selects.
 *
 * @param showMessage Custom function for displaying
 * feedback from the controller, defaults to a standard toast popup.
 *
 * @param _$control Originating select, may be undefined.
 */
const setter = (value: string, showMessage?: typeof Notification.messages, _$control?: JQuery) => {
  showMessage = showMessage || Notification.messages;
  globalState.debugQuerySqlFilter = value;
  return new Promise((resolve, reject) =>
    $.post(setPersonalUrl('debugQuerySqlFilter'), { value })
      .done(function(data) {
        showMessage(data.messages);
        console.log(data);
        resolve(data);
      })
      .fail(function(xhr, status, errorThrown) {
        Ajax.handleError(xhr, status, errorThrown);
        // console.error(data);
        reject(xhr);
      }));
};

subscribe(SET_DEBUG_QUERY_SQL_FILTER, (event) => {
  return setter(event?.value, event?.showMessage, event?.$control);
});

export default setter;
