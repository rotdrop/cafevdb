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

  // close all action menus when clicking outside
  $container.on('click', function(event) {
    const $menuContainer = $(event.target).closest('.dropdown-container.dropdown-no-hover');
    if ($menuContainer.length === 0) {
      $('.dropdown-container.dropdown-no-hover').removeClass('dropdown-shown');
    }
  });
};

/**
 * @param {(object|string)} element Element or selector. If omitted
 * all action menus are closed.
 */
function closeActionMenu(element) {
  const $element = $(element);
  const $menuContainer = $element.length > 0
    ? $element.closest('.dropdown-container.dropdown-no-hover')
    : $('.dropdown-container.dropdown-no-hover');
  $menuContainer.removeClass('dropdown-shown');
}

export default installActionMenuHandlers;

export {
  closeActionMenu as close,
};
