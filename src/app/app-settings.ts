/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import $ from './jquery.ts';
import { appName } from '../config.ts';
import { unfocus } from './cafevdb.ts';
import { translate as t } from '@nextcloud/l10n';
import generateAppUrl from '../toolkit/util/generate-url.ts';
import { subscribe, emit } from '../services/async-event-bus.ts';
import { APP_SETTINGS_POPUP, PUSH_BUSY_STATE, POP_BUSY_STATE } from '../event-bus-events.ts';

require('../legacy/nextcloud/jquery/requesttoken.js');
require('personal-settings-popup.scss');

subscribe(APP_SETTINGS_POPUP, async (event) => {
  console.info('EVENT', event);
  emit(PUSH_BUSY_STATE);
  await appSettingsPopup(event).finally(() => emit(POP_BUSY_STATE));
});

export interface Callbacks {
  done: <T extends HTMLElement>(this: T) => void,
  fail: <T extends HTMLElement>(this: T, xhr: JQueryXHR, status: string, errorThrown: string) => void,
  always: <T extends HTMLElement>(this: T) => void,
}

/**
 * A variant of the old fashioned appsettings with a callback
 * instead of script loading
 *
 * @param callbacks Object with done(), fail(), always() properties.
 */
export const appSettingsPopup = async function(callbacks: Partial<Callbacks>) {
  const defaultCallbacks = {
    done() {},
    fail() {},
    always() {},
  };
  callbacks = Object.assign({}, defaultCallbacks, callbacks);
  const $popup = $('#appsettings_popup');

  console.info('POPUP ELEMENT', $popup);

  await new Promise((resolve, reject) => {
    if ($popup.is(':visible')) {
      $popup.addClass('hidden').html('');
      callbacks.always?.apply($popup.get(0)!);
      console.info('RESOLVE SETTINGS PROMISE false');
      resolve(false);
    } else {
      // const arrowclass = $popup.hasClass('topright') ? 'up' : 'left';
      const route = 'settings/personal/form';
      $.get(generateAppUrl(route))
        .done(function(data) {
          $popup.html(data);
          // assume the first element is a container div
          if ($popup.find('.popup-title').length > 0) {
            $popup.find('.popup-title').append('<a class="close"></a>');
            // $popup.find(">:first-child").prepend('<a class="close"></a>').show();
          } else {
            $popup.find('>:first-child').prepend('<div class="popup-title"><h2>' + t('core', 'Settings') + '</h2><a class="close"></a></div>');
          }
          $popup.find('.close').on('click', function() {
            $popup.addClass('hidden').html('');
          });
          $popup.trigger(appName + ':content-update'); // trigger jq ui initialization etc.
          callbacks.done?.apply($popup.get(0)!);
          $popup.find('>:first-child').removeClass('hidden');
          $popup.removeClass('hidden');
          console.info('RESOLVE SETTINGS PROMISE true');
          resolve(true);
        })
        .fail(function(xhr, status, errorThrown) {
          callbacks.fail?.apply($popup.get(0)!, [xhr, status, errorThrown]);
          reject(new Error(errorThrown));
        })
        .always(() => callbacks.always?.apply($popup.get(0)!));
    }
  });
};

const documentReady = function() {

  const appNav = $('#app-navigation');

  appNav.on('click keydown', '#app-settings-header', function() {
    if ($('#app-settings').hasClass('open')) {
      $('#app-settings').switchClass('open', '');
    } else {
      $('#app-settings').switchClass('', 'open');
    }
    $('#app-settings-header').cafevTooltip('hide');
    unfocus('#app-settings-header');
    return false;
  });

  appNav.on('click', '#app-settings-further-settings', function() {
    const $self = $(this);
    $self.addClass('loading');
    appSettingsPopup({
      done() {
      },
      always() {
        $self.removeClass('loading');
      },
    });

    return false;
  });

};

export default documentReady;
