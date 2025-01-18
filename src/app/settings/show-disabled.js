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

import { globalState } from './../pme-state.js';
import $ from './../jquery.js';
import { setPersonalUrl } from './../settings-urls.js';
import * as Ajax from './../ajax.js';
import * as PHPMyEdit from './../pme-selectors.js';
import * as Notification from './../notification.js';
import { subscribe } from '@nextcloud/event-bus';
import { SET_SHOW_DISABLED } from '../../event-bus.ts';

require('../../legacy/nextcloud/jquery/requesttoken.js');

subscribe(SET_SHOW_DISABLED, (event) => {
  setter(event?.value, event?.showMessage);
});

const setter = (checked, showMessage) => {
  showMessage = showMessage || ((messages) => Notification.messages(messages));
  $.post(setPersonalUrl('showdisabled'), { value: checked })
    .done(function(data) {
      showMessage(data.message);
      console.log(data);
      if (globalState.PHPMyEdit !== undefined) {
        const $content = $('#content, #content-vue');
        const $pmeForm = $content.find(PHPMyEdit.formSelector + '.show-hide-disabled');
        console.log('form', $pmeForm);
        $pmeForm.each(function(index) {
          const $form = $(this);
          const $reload = $form.find(PHPMyEdit.classSelector('input', 'reload')).first();
          if ($reload.length > 0) {
            $form.append('<input type="hidden"'
              + ' name="' + PHPMyEdit.sys('sw') + '"'
              + ' value="Clear"/>');
            $reload.trigger('click');
          }
          if (checked) {
            $form.addClass('show-disabled').removeClass('hide-disabled');
          } else {
            $form.removeClass('show-disabled').addClass('hide-disabled');
          }
        });
      }
      return false;
    })
    .fail(function(xhr, status, errorThrown) {
      showMessage(Ajax.failMessage(xhr, status, errorThrown));
      // console.error(data);
    });
  globalState.PHPMyEdit.showDisabled = checked;
  $('.personal-settings input[type="checkbox"].showdisabled').prop('checked', checked);
};

export default setter;
