/*
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

// @@TODO these are rather personal settings

import { $ } from './globals.js';
import { appSettings, toolTipsInit, unfocus } from './cafevdb.js';

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
    appSettings(
      'settings/personal/form',
      function(container) {
        container.trigger('cafevdb:content-update'); // perhaps remove this
      });

    return false;
  });

  // appNav.on('click', '#app-settings-expert-operations', function(event) {
  //   appSettings(
  //     'expertmode/form',
  //     function(container) {
  //       $.fn.cafevTooltip.remove(); // remove any left-over items
  //       toolTipsInit(container);
  //     });
  //   return false;
  // });

};

export default documentReady;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
