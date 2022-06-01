/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import { unfocus } from './cafevdb.js';
import generateUrl from './generate-url.js';

/**
 * A variant of the old fashioned appsettings with a callback
 * instead of script loading
 *
 * @param {string} route TBD.
 *
 * @param {object} callbacks TBD.
 */
const appSettings = function(route, callbacks) {
  const defaultCallbacks = {
    done() {},
    fail() {},
    always() {},
  };
  callbacks = $.extend({}, defaultCallbacks, callbacks);
  const $popup = $('#appsettings_popup');
  if ($popup.is(':visible')) {
    $popup.addClass('hidden').html('');
    // $popup.hide().html('');
  } else {
    // const arrowclass = $popup.hasClass('topright') ? 'up' : 'left';
    $.get(generateUrl(route))
      .done(function(data) {
        $popup
          .html(data)
          .ready(function() {
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
            callbacks.done.apply($popup.get(0), arguments);
            $popup.find('>:first-child').removeClass('hidden');
            $popup.removeClass('hidden');
          });
      })
      .fail(function() {
        console.log(arguments);
        callbacks.fail.apply($popup.get(0), arguments);
      })
      .always(function() {
        callbacks.always.apply($popup.get(0), arguments);
      });
  }
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
    appSettings(
      'settings/personal/form', {
        done() {
          const $popup = $(this);
          $popup.trigger('cafevdb:content-update'); // perhaps remove this
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
