/**
 * Orchestra member, musicion and project management application.
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
/**
 * @file
 *
 * PME table epxort.
 */

import { appName, webRoot, $ } from './globals.js';
import generateUrl from './generate-url.js';
import generateId from './generate-id.js';
import { fixupNoChosenMenu, selectMenuReset } from './cafevdb.js';
import * as Dialogs from './dialogs.js';

/**
 * Handle the export menu actions.
 *
 * @param {jQuery} select TBD.
 */
const handleTableExportMenu = function(select) {
  const exportFormat = select.find('option:selected').val();

  // this is the form; we need its values
  const form = $('form.pme-form');

  const post = form.serializeArray();

  const cookieValue = generateId();
  const cookieName = appName + '_' + 'database_table_download';
  post.push({ name: 'DownloadCookieName', value: cookieName });
  post.push({ name: 'DownloadCookieValue', value: cookieValue });
  post.push({ name: 'requesttoken', value: OC.requestToken });
  post.push({ name: 'exportFormat', value: exportFormat });

  console.info('DOWNLOAD POST', post);

  $.fileDownload(
    generateUrl('page/pme/export'), {
      httpMethod: 'POST',
      data: post,
      cookieName,
      cookieValue,
      cookiePath: webRoot,
    })
    .fail(function(responseHtml, url) {
      Dialogs.alert(
        t(appName, 'Unable to export to format "{format}": {response}',
          { format: exportFormat, response: responseHtml }),
        t(appName, 'Error'),
        function() {},
        true, true);
    })
    .done(function(url) { console.info('DONE downloading', url); });

  // Cheating. In principle we mis-use this as a simple pull-down
  // menu, so let the text remain at its default value. Make sure to
  // also remove and re-attach the tool-tips, otherwise some of the
  // tips remain, because chosen() removes the element underneath.
  selectMenuReset(select);
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
  const exportSelect = container.find('select.pme-export-choice');
  exportSelect.chosen({
    disable_search: true,
    inherit_select_classes: true,
  });

  // install placeholder as first item if chosen is not active
  fixupNoChosenMenu(exportSelect);

  container.find('select.pme-export-choice')
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
