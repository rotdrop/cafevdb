/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022, 2023 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import { appName } from './app-info.js';
import * as ncRouter from '@nextcloud/router';
import wikiPopup from './wiki-popup.js';

const userManualMenuHandler = function(event) {
  const $this = $(this);
  const $item = $this.parent();
  const menuId = $item.data('id');
  switch (menuId) {
  case 'tooltips': {
    const $checkbox = $('#tooltipbutton-checkbox');
    $checkbox.trigger('click');
    break;
  }
  case 'manual_window':
  case 'manual_dialog': {
    let manualPage = $item.data('manualPage');
    const namespace = $item.data('namespace');
    if (!manualPage) {
      manualPage = $('#app-inner-content input[name="template"]').val();
    }
    if (!manualPage) {
      manualPage = 'intro';
    }
    const wikiPage = [
      namespace,
      appName,
      'documentation',
      'user-manual',
      manualPage,
    ].join(':');
    if (menuId === 'manual_dialog') {
      let dialogTitle = $item.data('dialogTitle');
      if (!dialogTitle) {
        const $titleProvider = $('#pme-short-title');
        dialogTitle = $titleProvider.length > 0 ? $titleProvider.html() : manualPage;
      }
      wikiPopup({ wikiPage, popupTitle: t(appName, 'User Manual: {section}', { section: dialogTitle }, 0, { escape: false }) });
    } else {
      const wikiUrl = ncRouter.generateUrl('/apps/dokuwiki/page/index')
            + '?wikiPage=' + wikiPage;
      window.open(wikiUrl, appName + ':user-manual');
    }
    break;
  }
  default:
    break;
  }

  return false;
};

const handleUserManualMenu = function(container) {
  container.on('click', '.help-dropdown li a', userManualMenuHandler);
};

export {
  userManualMenuHandler as menuHandler,
  handleUserManualMenu as handleMenu,
};
