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

import { $, appName } from './globals.js';

const installActionMenuHandlers = function($container) {
  $container = $container || $('#content.app-' + appName);

  $container.on('click', '.dropdown-container.dropdown-no-hover', function(event) {
    const $this = $(this);
    $this.toggleClass('dropdown-shown');
    if ($this.hasClass('dropdown-shown')) {
      // only keep one menu open
      $('.dropdown-container.dropdown-no-hover').not($this).removeClass('dropdown-shown');
    }
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
