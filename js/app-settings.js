/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

$(document).ready(function() {

  var appNav = $('#app-navigation');

  appNav.on('click keydown', '#app-settings-header', function(event) {
    if ($('#app-settings').hasClass('open')) {
      $('#app-settings').switchClass('open', '');
    } else {
      $('#app-settings').switchClass('', 'open');
    }
    $('#app-settings-header').cafevTooltip('hide');
    CAFEVDB.unfocus('#app-settings-header');
    return false;
  });

  appNav.on('click', '#app-settings-further-settings', function(event) {
    CAFEVDB.appSettings(
      'settings/personal/form',
      function(container) {
        $('#personal-settings-container').tabs({ selected: 0});
        container.trigger('cafevdb:content-update');
        $.fn.cafevTooltip.remove(); // remove any left-over items
        CAFEVDB.toolTipsInit(container);
      });

    return false;
  });

  appNav.on('click', '#app-settings-expert-operations', function(event) {
    CAFEVDB.appSettings(
      'expertmode/form',
      function(container) {
        $.fn.cafevTooltip.remove(); // remove any left-over items
        CAFEVDB.toolTipsInit(container);
      });
    return false;
  });

});

// Local Variables: ***
// js3-indent-level: 2 ***
// js-indent-level: 2 ***
// End: ***
