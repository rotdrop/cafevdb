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

import $ from './jquery.js';
import { appName } from '../config.ts';
import { unfocus } from './cafevdb.js';
import generateUrl from './generate-url.js';
import { subscribe, emit } from '@rotdrop/async-nextcloud-event-bus';
import { APP_SETTINGS_POPUP, PUSH_BUSY_STATE, POP_BUSY_STATE } from '../event-bus-events.ts';

require('../legacy/nextcloud/jquery/requesttoken.js');
require('personal-settings-popup.scss');

subscribe(APP_SETTINGS_POPUP, async (event) => {
  console.info('EVENT', event);
  emit(PUSH_BUSY_STATE);
  await appSettingsPopup(event).finally(() => emit(POP_BUSY_STATE));
});

/**
 * A variant of the old fashioned appsettings with a callback
 * instead of script loading
 *
 * @param {object} callbacks Object with done(), fail(), always() properties.
 *
 * @returns {Promise}
 */
export const appSettingsPopup = async function(callbacks) {
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
      callbacks.always();
      console.info('RESOLVE SETTINGS PROMISE false');
      resolve(false);
    } else {
      // const arrowclass = $popup.hasClass('topright') ? 'up' : 'left';
      const route = 'settings/personal/form';
      $.get(generateUrl(route))
        .done(function(data) {
          $popup
            .html(data)
            .ready(function(...args) {
              // assume the first element is a container div
              if ($popup.find('.popup-title').length > 0) {
                $popup.find('.popup-title').append('<a class="close"></a>');
                // $popup.find(">:first-child").prepend('<a class="close"></a>').show();
              } else {
                $popup.find('>:first-child').prepend('<div class="popup-title"><h2>' + t('core', 'Settings') + '</h2><a class="close"></a></div>');
              }
              $popup.find('.close').bind('click', function() {
                $popup.addClass('hidden').html('');
              });
              $popup.trigger(appName + ':content-update'); // trigger jq ui initialization etc.
              callbacks.done.apply($popup.get(0), ...args);
              $popup.find('>:first-child').removeClass('hidden');
              $popup.removeClass('hidden');
              console.info('RESOLVE SETTINGS PROMISE true');
              resolve(true);
            });
        })
        .fail(function(xhr, status, errorThrown, ...rest) {
          console.log(arguments);
          callbacks.fail.apply($popup.get(0), xhr, status, errorThrown, ...rest);
          reject(new Error(errorThrown));
        })
        .always(function() {
          callbacks.always.apply($popup.get(0), arguments);
        });
    }
  });
};

const documentReady = function() {

  const appNav = $('#app-navigation');

  appNav.on('click keydown', '#app-settings-header', function(event) {
    if ($('#app-settings').hasClass('open')) {
      $('#app-settings').switchClass('open', '');
    } else {
      $('#app-settings').switchClass('', 'open');
    }
    $('#app-settings-header').cafevTooltip('hide');
    unfocus('#app-settings-header');
    return false;
  });

  appNav.on('click', '#app-settings-further-settings', function(event) {
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

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
