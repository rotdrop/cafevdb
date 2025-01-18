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
import { appPrefix } from '../../config.js';
import { setPersonalUrl } from './../settings-urls.js';
import * as Ajax from './../ajax.js';
import * as PHPMyEdit from './../pme-selectors.js';
import * as Notification from './../notification.js';
import { subscribe } from '@nextcloud/event-bus';
import { SET_EXPERT_MODE } from '../../event-bus.ts';

require('../../legacy/nextcloud/jquery/requesttoken.js');

subscribe(SET_EXPERT_MODE, (event) => {
  setter(event?.value, event?.showMessage);
});

const setter = (checked, showMessage) => {
  showMessage = showMessage || ((messages) => Notification.messages(messages));
  $.post(setPersonalUrl('expert-mode'), { value: checked })
    .done(function(data) {
      showMessage(data.message);
      console.log(data);
      if (globalState.PHPMyEdit !== undefined) {
        const pmeForm = $('#content ' + PHPMyEdit.formSelector);
        pmeForm.each(function(index) {
          const reload = $(this).find(PHPMyEdit.classSelector('input', 'reload')).first();
          reload.trigger('click');
        });
      }
    })
    .fail(function(xhr, status, errorThrown) {
      showMessage(Ajax.failMessage(xhr, status, errorThrown));
      // console.error(data);
    });

  $('.expert-mode-container').toggleClass('hidden', !checked);
  $('body').toggleClass(appPrefix('expert-mode'), checked);
  $('.personal-settings input[type="checkbox"].expert-mode').prop('checked', checked);
  $('select.debug-mode').prop('disabled', false).trigger('chosen:updated');
  $.fn.cafevTooltip.remove(); // remove any left-over items.
  globalState.expertMode = checked;
};

export default setter;
