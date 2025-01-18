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
import { toolTipsOnOff } from './cafevdb.js';
import { setPersonalUrl } from './settings-urls.js';
import * as Ajax from './ajax.js';
import * as Notification from './notification.js';
import { subscribe } from '@nextcloud/event-bus';
import { SET_TOOLTIPS_MODE } from '../event-bus.ts';

require('../legacy/nextcloud/jquery/requesttoken.js');

subscribe(SET_TOOLTIPS_MODE, (event) => {
  setter(event?.value, event?.showMessage, event?.$control);
});

const setter = (checked, showMessage, $control) => {
  showMessage = showMessage || ((messages) => Notification.messages(messages));
  toolTipsOnOff(checked);
  $.post(setPersonalUrl('tooltips'), { value: globalState.toolTipsEnabled })
    .done(function(data) {
      if (!$control?.is('#tooltipbutton-checkbox')) { // don't annoy with feedback
        showMessage(data.message);
      }
      console.log(data);
    })
    .fail(function(xhr, status, errorThrown) {
      showMessage(Ajax.failMessage(xhr, status, errorThrown));
      // console.error(data);
    });
  $('.personal-settings input[type="checkbox"].tooltips').prop('checked', globalState.toolTipsEnabled);
  if (globalState.toolTipsEnabled) {
    $('#tooltipbutton').removeClass('tooltips-disabled').addClass('tooltips-enabled');
  } else {
    $('#tooltipbutton').removeClass('tooltips-enabled').addClass('tooltips-disabled');
  }
};

export default setter;
