/**
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

import { appName, $ } from './globals.js';
import { wikiPopup as dokuWikiPopup } from 'dokuwikiembedded/src/doku-wiki-popup';
import { toBackButton as dialogToBackButton } from './dialog-utils.js';
import modalizer from './modalizer.js';

/**
 * Generate a popup-dialog with a wiki-page. Not to much project
 * related, rather general. Page and page-title are assumed to be
 * attached to the "post"-object
 *
 * @param {Object} post Arguments object:
 * { projectName: 'NAME', projectId: XX }
 *
 * @param {bool} reopen If true, close any already dialog and re-open it
 * (the default). If false, only raise an existing dialog to top.
 */
const wikiPopup = function(post, reopen) {
  if (typeof reopen === 'undefined') {
    reopen = false;
  }
  const wikiDlg = $('#dokuwiki_popup');
  if (wikiDlg.dialog('isOpen') === true) {
    if (reopen === false) {
      wikiDlg.dialog('moveToTop');
      return;
    }
    wikiDlg.dialog('close').remove();
  }
  dokuWikiPopup(
    {
      wikiPage: post.wikiPage,
      popupTitle: post.popupTitle,
      cssClass: appName,
      modal: false,
    },
    function(dwDialog, dwDialogWidget) {
      // open callback
      dwDialog.dialog('option', 'appendTo', '#cafevdb-general');
      // Custom shuffle button
      dialogToBackButton(dwDialog);
    },
    function() {
      // close callback
      // Remove modal plane if appropriate
      modalizer(false);
    });
};

export default wikiPopup;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
