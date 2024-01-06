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
import { selectedOptions, deselectAll as selectDeselectAll, makePlaceholder as selectPlaceholder } from './select-utils.js';
import * as Notification from './notification.js';
import * as Dialogs from './dialogs.js';

/**
 * Handle the export menu actions.
 *
 * @param {jQuery} $select TBD.
 */
const handleQueryLogMenu = function($select) {
  const $logOption = selectedOptions($select);

  const queryData = $logOption.data('query');
  Dialogs.info(
    '<div class="query-log-container">'
      + '<dl class="query-log-entry">'
      + '<dt>' + t(appName, 'Query')
      + '<a class="copy button" href="#">' + t(appName, 'copy') + '</a>'
      + '</dt>'
      + '<dd>' + queryData.query + '</dd>'
      + '<dt>' + t(appName, 'Duration') + '</dt>'
      + '<dd>' + queryData.duration + ' ' + 'ms' + '</dd>'
      + '<dt>' + t(appName, 'Affected Rows') + '</dt>'
      + '<dd>' + queryData.affectedRows + '</dd>'
      + '<dt>' + t(appName, 'Error Code') + '</dt>'
      + '<dd>' + queryData.errorCode + '</dd>'
      + '</dl>'
      + '</div>',
    t(appName, 'Selected SQL-Query'),
    undefined,
    undefined,
    true, // allow HTML
  );

  $('body')
    .off('click', '.query-log-container a.copy')
    .on('click', '.query-log-container a.copy', function(event) {
      navigator.clipboard.writeText(queryData.query).then(function() {
        Notification.showTemporary(t(appName, 'Query has been copied to the clipboard.'));
      }, function(reason) {
        Notification.showTemporary(t(appName, 'Failed copying query to the clipboard: {reason}.', { reason }));
      });
      return false;
    });

  // Cheating. In principle we mis-use this as a simple pull-down
  // menu, so let the text remain at its default value. Make sure to
  // also remove and re-attach the tool-tips, otherwise some of the
  // tips remain, because chosen() removes the element underneath.
  selectDeselectAll($select);
  $.fn.cafevTooltip.remove();

  $('div.chosen-container').cafevTooltip({ placement: 'auto' });
};

const pmeQueryLogMenu = function(containerSel) {
  if (typeof containerSel === 'undefined') {
    containerSel = '#cafevdb-page-body';
  }
  const $container = $(containerSel);

  // Emulate a pull-down menu with export options via the chosen
  // plugin.
  const $queryLogSelect = $container.find('.query-log select');

  $queryLogSelect.chosen({
    disable_search: true,
    inherit_select_classes: true,
    title_attributes: ['title', 'data-original-title', 'data-cafevdb-title'],
  });

  // install placeholder as first item if chosen is not active
  selectPlaceholder($queryLogSelect);

  $queryLogSelect
    .off('change')
    .on('change', function(event) {
      handleQueryLogMenu($queryLogSelect);
      return false;
    });
};

export default pmeQueryLogMenu;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
