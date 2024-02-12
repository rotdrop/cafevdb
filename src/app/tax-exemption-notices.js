/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState, $ } from './globals.js';
import * as CAFEVDB from './cafevdb.js';
import * as Page from './page.js';
import * as PHPMyEdit from './pme.js';
import initFileUploadRow from './pme-file-upload-row.js';
import fileDownload from './file-download.js';

require('jquery-ui/ui/widgets/autocomplete');
require('jquery-ui/themes/base/autocomplete.css');

require('tax-exemption-notices.scss');
require('project-participant-fields-display.scss');

const pmeAutocomplete = function($input) {
  const autocompleteData = $input.data('autocomplete');
  if (autocompleteData) {
    $input
      .autocomplete({
        source: autocompleteData.map(x => String(x)),
        minLength: 0,
        open(event, ui) {
          const $input = $(event.target);
          const $results = $input.autocomplete('widget');
          // The following would place the list above the input
          // const top = $results.position().top;
          // const height = $results.outerHeight();
          // const inputHeight = $input.outerHeight();
          // const newTop = top - height - inputHeight;

          // $results.css('top', newTop + 'px');
          const $parent = $results.parent();
          $results.data('savedOverflow', $parent.css('overflow'));
          $parent.css('overflow', 'visible');
        },
        close(event, ui) {
          const $input = $(event.target);
          const $results = $input.autocomplete('widget');
          const $parent = $results.parent();
          $parent.css('overflow', $results.data('savedOverflow'));
          $results.removeData('savedOverflow');
        },
        select(event, ui) {
          const $input = $(event.target);
          $input.val(ui.item.value);
          $input.blur();
        },
      })
      .on('focus, click', function() {
        const $this = $(this);
        if (!$this.autocomplete('widget').is(':visible')) {
          $this.autocomplete('search', $this.val());
        }
      });
  }
};

const pmeFormInit = function(containerSel, parameters, resizeCB) {
  containerSel = PHPMyEdit.selector(containerSel);
  const $container = PHPMyEdit.container(containerSel);
  const $form = $container.find('form[class^="pme-form"]');

  if (!PHPMyEdit.hasEditableData($form)) {
    // no need to do further work
    return;
  }

  pmeAutocomplete($form.find('input.year-autocomplete'));

  $container
    .off('click', 'a.download-link.ajax-download')
    .on('click', 'a.download-link.ajax-download', function(event) {
      const $this = $(this);
      const post = $this.data('post');
      fileDownload($this.attr('href'), post);
      return false;
    });

  // upload supporting document(s)
  $container
    .find('tr.written-notice td.pme-value .file-upload-row')
    .each(function() {
      initFileUploadRow.call(
        this,
        -1, // projectId
        -1, // musicianId,
        resizeCB, {
          upload: 'finance/exemption-notices/documents/upload',
          delete: 'finance/exemption-notices/documents/delete',
        });
      const ambientContainerSelector = parameters?.tableOptions?.ambientContainerSelector;
      if (ambientContainerSelector) {
        $(this).on('pme:upload-done pme:upload-deleted', (event) => {
          event.stopImmediatePropagation();
          $(ambientContainerSelector).trigger('pmedialog:changed');
          PHPMyEdit.submitOuterForm(ambientContainerSelector);
        });
      }
    });
};

const documentReady = function() {

  PHPMyEdit.addTableLoadCallback(
    'tax-exemption-notices', {
      callback(selector, parameters, resizeCB) {
        if (parameters.reason === 'dialogOpen') {
          pmeFormInit(selector, parameters, resizeCB);
        }
        resizeCB();
      },
      context: globalState,
      parameters: [],
    });

  CAFEVDB.addReadyCallback(function() {

    const container = PHPMyEdit.container();

    if (!container.hasClass('tax-exemption-notices')) {
      return;
    }

    const renderer = $(PHPMyEdit.defaultSelector).find('form.pme-form input[name="templateRenderer"]').val();
    if (renderer === Page.templateRenderer('tax-exemption-notices')) {
      pmeFormInit(PHPMyEdit.defaultSelector, undefined, () => null);
    }
  });
};

export {
  documentReady,
};
