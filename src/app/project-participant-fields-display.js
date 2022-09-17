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

import $ from './jquery.js';
import { appName } from './app-info.js';
import * as Ajax from './ajax.js';
import * as Notification from './notification.js';
import * as WysiwygEditor from './wysiwyg-editor.js';
import * as Dialogs from './dialogs.js';
import { submitOuterForm, tableDialogLoadIndicator } from './pme.js';
import { confirmedReceivablesUpdate } from './project-participant-fields.js';
import { busyIcon as pageBusyIcon } from './page.js';
import generateUrl from './generate-url.js';
import fileDownload from './file-download.js';
import {
  formSelector as pmeFormSelector,
  inputSelector as pmeInputSelector,
} from './pme-selectors.js';

const pmeLabelInputSelector = 'td.label ' + pmeInputSelector + '[type="text"]';
const pmeValueInputSelector = [
  'td.input ' + pmeInputSelector + '[type="text"]',
  'td.input ' + pmeInputSelector + '[type="number"]',
].join(',');

const participantOptionHandlers = function(container, musicianId, projectId, dialogParameters) {

  if (!musicianId) {
    return;
  }

  const $container = $(container);

  // AJAX download support
  $container
    .find('a.download-link.ajax-download')
    .off('click')
    .on('click', function(event) {
      const $this = $(this);
      fileDownload($this.attr('href'), undefined, {
        setup() {
          tableDialogLoadIndicator($container, true);
          pageBusyIcon(true);
        },
        always() {
          pageBusyIcon(false);
          tableDialogLoadIndicator($container, false);
        },
      });
      return false;
    });

  // Handle buttons to revert to default value. Field id must be given
  // as data-value.

  const $pmeForm = $container.find(pmeFormSelector);

  $pmeForm
    .find('tr.participant-field input.revert-to-default')
    .off('click')
    .on('click', function(event) {
      const $self = $(this);
      const $inputElement = $self.parent().find(pmeInputSelector);
      const fieldId = $self.data('fieldId');
      const fieldProperty = $self.data('fieldProperty') || 'defaultValue';

      const revertHandler = function() {
        $.post(
          generateUrl('projects/participant-fields/property/get'), {
            fieldId,
            property: fieldProperty,
          })
          .fail(function(xhr, status, errorThrown) {
            Ajax.handleError(xhr, status, errorThrown);
          })
          .done(function(data) {
            if (!Ajax.validateResponse(data, ['fieldId', 'property', 'value'])) {
              return;
            }
            if ($inputElement.hasClass('wysiwyg-editor')) {
              WysiwygEditor.updateEditor($inputElement, data.value);
            } else {
              $inputElement.val(data.value);
            }
          });
      };

      if ($inputElement.val() !== '') {
        Dialogs.confirm(
          t(appName,
            'Input element is not empty, do your really want to revert it to its default value?'),
          t(appName, 'Revert to default value?'),
          function(confirmed) {
            if (confirmed) {
              revertHandler();
            }
          },
          true
        );
      } else {
        revertHandler();
      }

      return false;
    });

  // handle buttons to update or delete recurrent receivables

  const $recurringReceivablesRows = $pmeForm.find('tr.participant-field.recurring');
  const $recurringReceivablesOperations = $recurringReceivablesRows.find('td.operations');
  const $recurringReceivablesFieldData = $recurringReceivablesRows.find('tr.field-datum');

  $recurringReceivablesOperations
    .find('input.show-empty-options')
    .off('change')
    .on('change', function(event) {
      const $this = $(this);
      $this.closest('table').toggleClass('hide-empty-values', !$this.prop('checked'));
      $this.closest('.resize-target').trigger('resize');
      return false;
    });

  $recurringReceivablesFieldData
    .off('blur', 'td.label, td.input')
    .on('blur', 'td.label, td.input', function(event) {
      const $dataRow = $(event.delegateTarget);
      const $parentTable = $dataRow.closest('table');
      const hasLabel = $dataRow.find(pmeLabelInputSelector).val() !== '';
      const hasValue = $dataRow.find(pmeValueInputSelector).val() !== '';

      let parentHasEmptyLabelledValues = false;
      if (hasLabel && !hasValue) {
        parentHasEmptyLabelledValues = true;
      } else {
        $parentTable.find('tr.field-datum').each(function() {
          const $thisRow = $(this);
          const hasLabel = $thisRow.find(pmeLabelInputSelector).val() !== '';
          const hasValue = $thisRow.find(pmeValueInputSelector).val() !== '';
          if (hasLabel && !hasValue) {
            parentHasEmptyLabelledValues = true;
          }
        });
      }
      $parentTable.toggleClass('has-empty-labelled-values', parentHasEmptyLabelledValues);
    });

  $recurringReceivablesOperations
    .find('input.regenerate')
    .off('click')
    .on('click', function(event) {
      const $this = $(this);
      const row = $this.closest('tr');
      const fieldId = row.data('fieldId');
      const optionKey = row.data('optionKey');
      const updateStrategy = $this.closest('table').find('select.recurring-receivables-update-strategy').val();
      const requestHandler = function(progressToken, progressCleanup) {
        const cleanup = function() {
          progressCleanup();
          $this.removeClass('busy');
        };
        const request = 'option/regenerate';
        $this.addClass('busy');
        return $.post(
          generateUrl('projects/participant-fields/' + request), {
            data: {
              fieldId,
              key: optionKey,
              musicianId,
              updateStrategy,
              progressToken,
            },
          })
          .fail(function(xhr, status, errorThrown) {
            Ajax.handleError(xhr, status, errorThrown, cleanup);
          })
          .done(function(data) {
            if (!Ajax.validateResponse(data, ['amounts'], cleanup)) {
              return;
            }
            if (data.amounts[musicianId]) {
              row.find('input.pme-input.service-fee').val(data.amounts[musicianId]);
            }
            Notification.messages(data.message);
            cleanup();
          });
      };

      confirmedReceivablesUpdate(updateStrategy, requestHandler, 'single');

      return false;
    });

  $recurringReceivablesOperations
    .find('input.delete-undelete')
    .off('click')
    .on('click', function(event) {
      const $this = $(this);
      const row = $this.closest('tr');
      // const fieldId = row.data('fieldId');
      const optionKey = row.data('optionKey');

      // could also search for name with field-id
      const inputs = $container
        .find('input[value="' + optionKey + '"]')
        .add(row.find('.pme-input, .operation.regenerate'));

      if (row.hasClass('deleted')) {
        inputs.prop('disabled', false);
        row.removeClass('deleted');
      } else {
        inputs.prop('disabled', true);
        row.addClass('deleted');
      }

      return false;
    });

  $recurringReceivablesOperations
    .find('input.regenerate-all')
    .off('click')
    .on('click', function(event) {
      const $this = $(this);
      const row = $this.closest('tr');
      const fieldId = row.data('fieldId');
      const updateStrategy = $this.closest('table').find('select.recurring-receivables-update-strategy').val();
      const requestHandler = function(progressToken, progressCleanup) {
        const cleanup = function() {
          progressCleanup();
          $this.removeClass('busy');
        };
        const request = 'option/regenerate';
        $this.addClass('busy');
        return $.post(
          generateUrl('projects/participant-fields/' + request), {
            data: {
              fieldId,
              musicianId,
              updateStrategy,
              progressToken,
            },
          })
          .fail(function(xhr, status, errorThrown) {
            Ajax.handleError(xhr, status, errorThrown, cleanup);
          })
          .done(function(data) {
            if (!Ajax.validateResponse(data, [], cleanup)) {
              return;
            }
            // just trigger reload
            $container.find('form.pme-form input.pme-reload').first().trigger('click');
            cleanup();
            Notification.messages(data.message);
          });
      };
      confirmedReceivablesUpdate(updateStrategy, requestHandler);
      return false;
    });

  if (dialogParameters) {
    const tableOptions = dialogParameters.tableOptions || {};
    const ambientContainerSelector = tableOptions.ambientContainerSelector;
    if (ambientContainerSelector) {
      $pmeForm
        .find('tr.participant-field.cloud-file, tr.participant-field.db-file, tr.participant-field.cloud-folder')
        .find('td.pme-value')
        .on('pme:upload-done pme:upload-deleted', '.file-upload-row', function(event) {
          $container.trigger('pmedialog:changed');
          submitOuterForm(ambientContainerSelector);
        });
    }
  }
};

export default participantOptionHandlers;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
