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
/**
 * @file
 *
 * PME table epxort.
 */

import $ from './jquery.js';
import { appName } from './app-info.js';
import fileDownload from './file-download.js';
import * as SelectUtils from './select-utils.js';

/**
 * Handle the export menu actions.
 *
 * @param {jQuery} $select TBD.
 */
const handleTableExportMenu = function($select) {
  const exportFormat = SelectUtils.selected($select);

  // this is the form; we need its values
  const form = $('form.pme-form');

  const post = form.serializeArray();
  post.push({ name: 'exportFormat', value: exportFormat });

  fileDownload(
    'page/pme/export',
    post, {
      errorMessage(url, data) {
        return t(
          appName,
          'Unable to download table in format "{format}" from "{url}": ',
          { format: exportFormat, url });
      },
    });

  // Cheating. In principle we mis-use this as a simple pull-down
  // menu, so let the text remain at its default value. Make sure to
  // also remove and re-attach the tool-tips, otherwise some of the
  // tips remain, because chosen() removes the element underneath.
  SelectUtils.deselectAll($select);
  $.fn.cafevTooltip.remove();

  $('div.chosen-container').cafevTooltip({ placement: 'auto' });
};

const pmeExportMenu = function(containerSel) {
  if (typeof containerSel === 'undefined') {
    containerSel = '#cafevdb-page-body';
  }
  const container = $(containerSel);

  // Emulate a pull-down menu with export options via the chosen
  // plugin.
  const $exportSelect = container.find('select.pme-export-choice');
  $exportSelect.chosen({
    disable_search: true,
    inherit_select_classes: true,
    title_attributes: ['title', 'data-original-title', 'data-cafevdb-title'],
  });

  // install placeholder as first item if chosen is not active
  SelectUtils.makePlaceholder($exportSelect);

  $exportSelect
    .off('change')
    .on('change', function(event) {
      handleTableExportMenu($(this));
      return false;
    });

};

export default pmeExportMenu;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
