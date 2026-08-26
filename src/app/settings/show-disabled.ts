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
import { SHOW_HIDE_DISABLED } from '../../../build/ts-types/php-modules/PageRenderer/CssClasses.ts';
import { SET_SHOW_DISABLED } from '../../event-bus-events.ts';
import * as Ajax from './../ajax.ts';
import globalState from './../globalstate.ts';
import $ from './../jquery.ts';
import * as Notification from './../notification.ts';
import * as PHPMyEdit from './../pme-selectors.ts';
import { setPersonalUrl } from './../settings-urls.ts';

import '../../legacy/nextcloud/jquery/requesttoken.js';
import { hideDisabledCssClass, showDisabledCssClass } from 'variables.scss';

/**
 * @param value Value to set.
 *
 * @param showMessage Custom function for displaying
 * feedback from the controller, defaults to a standard toast popup.
 *
 * @param _$control Originating select, may be undefined.
 */
const setter = (value: boolean, showMessage?: typeof Notification.messages, _$control?: JQuery) => {
  showMessage = showMessage || Notification.messages;
  globalState.PHPMyEdit.showDisabled = value;
  $('.personal-settings input[type="checkbox"].showdisabled').prop('checked', value);
  return new Promise((resolve, reject) =>
    $.post(setPersonalUrl('showDisabled'), { value })
      .done(function(data) {
        showMessage(data.messages);
        const $content = $('#content, #content-vue');
        const $pmeForm = $content.find(`${PHPMyEdit.formSelector}.${SHOW_HIDE_DISABLED}`);
        console.log('form', $pmeForm);
        $pmeForm.each(function() {
          const $form = $(this);
          const $reload = $form.find(PHPMyEdit.classSelector('input', 'reload')).first();
          if ($reload.length > 0) {
            $form.append('<input type="hidden"'
              + ' name="' + PHPMyEdit.sys('sw') + '"'
              + ' value="Clear"/>');
            $reload.trigger('click');
          }
          if (value) {
            $form.addClass(showDisabledCssClass).removeClass(hideDisabledCssClass);
          } else {
            $form.removeClass(showDisabledCssClass).addClass(hideDisabledCssClass);
          }
        });
        resolve(data);
      })
      .fail(async function(xhr, status, errorThrown) {
        await Ajax.handleError(xhr, status, errorThrown);
        reject(xhr);
      }));
};

subscribe(SET_SHOW_DISABLED, (event) => {
  return setter(event?.value, event?.showMessage, event?.$control);
});

export default setter;
