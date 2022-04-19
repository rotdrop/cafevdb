/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license GNU AGPL version 3 or any later version
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
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

import { $, appName } from './globals.js';

const installActionMenuHandlers = function($container) {
  $container = $container || $('#content.app-' + appName);

  $container.on('click', '.dropdown-container.dropdown-no-hover', function(event) {
    const $this = $(this);
    $this.toggleClass('dropdown-shown');
    if ($(event.target).is('button.action-menu-toggle')) {
      return false;
    }
  });

  $container.on('click', function(event) {
    const $container = $(event.target).closest('.dropdown-container.dropdown-no-hover');
    if ($container.length === 0) {
      $('.dropdown-container.dropdown-no-hover').removeClass('dropdown-shown');
    }
  });
};

export default installActionMenuHandlers;
