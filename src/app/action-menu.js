/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2023 Claus-Justus Heine <himself@claus-justus-heine.de>
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

function markDialog($dropDownContainer) {
  const $dialog = $dropDownContainer.closest('.ui-dialog.pme-table-dialog');
  $dialog.toggleClass('dialog-dropdown-shown', $dialog.find('.dropdown-shown').length > 0);
}

const hideActionMenu = function($dropDownContainer) {
  $dropDownContainer
    .removeClass('dropdown-shown')
    .removeClass('context-menu');
  $dropDownContainer.find('.dropdown-content').removeAttr('style');
  return $dropDownContainer;
};

const enforceVisibility = function($element) {
  const element = $element[0];
  const rect = element.getBoundingClientRect();
  const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
  const viewportHeight = window.innerHeight || document.documentElement.clientHeight;

  const shift = { x: 0, y: 0 };
  if (rect.width > viewportWidth || rect.height > viewportHeight) {
    return;
  }
  if (rect.left < 0) {
    shift.x = -rect.left;
  }
  if (rect.top < 0) {
    shift.y = -rect.top;
  }
  if (rect.right > viewportWidth) {
    shift.x = viewportWidth - rect.right; // negative
  }
  if (rect.bottom > viewportHeight) {
    shift.y = viewportHeight - rect.bottom;
  }

  if (shift.x === 0 && shift.y === 0) {
    return;
  }
  $element.css({
    position: 'fixed',
    left: rect.left + shift.x,
    top: rect.top + shift.y,
  });
};

const installActionMenuHandlers = function($container) {
  $container = $container || $('#content.app-' + appName);

  $container.on('click', '.dropdown-container.dropdown-no-hover', function(event) {
    if ($(event.target).closest('li.dropdown-item.dropdown-no-close').length > 0) {
      return; // ignore
    }
    const $this = $(this);
    $this.toggleClass('dropdown-shown');
    if ($this.hasClass('dropdown-shown')) {
      // only keep one menu open
      hideActionMenu($('.dropdown-container.dropdown-no-hover').not($this));
      const $dropdownContent = $this.find('.dropdown-content');
      enforceVisibility($dropdownContent);
    } else {
      hideActionMenu($this);
    }
    markDialog($this);
    $.fn.cafevTooltip.remove();
    if ($(event.target).is('button.action-menu-toggle')) {
      return false;
    }
  });

  // close all action menus when clicking outside
  $container.on('click', function(event) {
    const $menuContainer = $(event.target).closest('.dropdown-container.dropdown-no-hover');
    if ($menuContainer.length === 0) {
      markDialog(hideActionMenu($('.dropdown-container.dropdown-no-hover')));
    }
    $.fn.cafevTooltip.remove();
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
  markDialog(hideActionMenu($menuContainer));
  $.fn.cafevTooltip.remove();
}

export default installActionMenuHandlers;

export {
  closeActionMenu as close,
};
