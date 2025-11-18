/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import $ from './jquery.ts';
import { addReadyCallback } from './cafevdb.ts';
import { templateRenderer } from './template-renderer.ts';
import * as PHPMyEdit from './pme.ts';
import initFileUploadRow from './pme-file-upload-row.ts';
import fileDownload from './file-download.ts';
import { formSelector as pmeFormSelector } from './pme-selectors.ts';
import { filename } from './path.ts';
import pmeAutocomplete from './pme-autocomplete.ts';
import type { TableDialogCallbackData } from './pme-state.ts';

require('jquery-ui/ui/widgets/autocomplete');
require('jquery-ui/themes/base/autocomplete.css');

require('tax-exemption-notices.scss');
require('project-participant-fields-display.scss');

const templateName = filename(__filename);

const pmeFormInit = (containerSel: string, parameters?: TableDialogCallbackData, resizeCB: () => void = () => {}) => {
  containerSel = PHPMyEdit.selector(containerSel);
  const $container = PHPMyEdit.container(containerSel);
  const $form = $container.find(pmeFormSelector) as JQuery<HTMLFormElement>;

  if (!PHPMyEdit.hasEditableData($form)) {
    // no need to do further work
    return;
  }

  pmeAutocomplete($form.find('input.year-autocomplete'));

  $container
    .off('click', 'a.download-link.ajax-download')
    .on('click', 'a.download-link.ajax-download', function() {
      const $this = $(this);
      const post = $this.data('post');
      fileDownload($this.attr('href')!, post);
      return false;
    });

  // upload supporting document(s)
  ($container
    .find('tr.written-notice td.pme-value .file-upload-row') as JQuery<HTMLTableRowElement>)
    .each(function() {
      initFileUploadRow.call(
        this,
        -1, // projectId
        -1, // musicianId,
        resizeCB, {
          upload: 'documents/finance/' + templateName + '/upload',
          delete: 'documents/finance/' + templateName + '/delete',
        });
      const ambientContainerSelector = parameters?.tableDialogOptions?.ambientContainerSelector;
      if (ambientContainerSelector) {
        $(this).on('pme:upload-done pme:upload-deleted', (event) => {
          event.stopImmediatePropagation();
          $(ambientContainerSelector).trigger('pmedialog:changed');
          PHPMyEdit.submitOuterFormNoThrow(ambientContainerSelector);
        });
      }
    });
};

const documentReady = function() {

  PHPMyEdit.addTableLoadCallback(
    templateName, {
      callback(selector, parameters, resizeCB) {
        if (parameters.reason === 'dialogOpen') {
          pmeFormInit(selector, parameters, resizeCB);
        }
        resizeCB();
      },
      parameters: [],
    });

  addReadyCallback(async () => {

    const container = PHPMyEdit.container();

    if (!container.hasClass(templateName)) {
      return;
    }

    const renderer = $(PHPMyEdit.defaultSelector).find('form.pme-form input[name="templateRenderer"]').val();
    if (renderer === templateRenderer(templateName)) {
      pmeFormInit(PHPMyEdit.defaultSelector);
    }
  });
};

export {
  documentReady,
};
