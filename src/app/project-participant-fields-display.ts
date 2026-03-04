/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2023, 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import $, { jq, isJQuerySelect } from './jquery.ts';
import { appName } from '../config.ts';
import * as Ajax from './ajax.ts';
import * as WysiwygEditor from './wysiwyg-editor.ts';
import * as Dialogs from './dialogs.ts';
import { submitOuterFormNoThrow, tableDialogLoadIndicator } from './pme.ts';
import { translate as t } from '@nextcloud/l10n';
import {
  confirmedReceivablesUpdate,
  autocompleteFocusHandler,
  fetchBalancingAccountsAutocompleteData,
  balancingAccountsAutocompleteData,
  type UpdateStrategy,
} from './project-participant-fields.ts';
import pageBusyIcon from './busy-icon.ts';
import generateAppUrl from '../toolkit/util/generate-url.ts';
import fileDownload from './file-download.ts';
import { selected as selectedValues } from './select-utils.ts';
import {
  formSelector as pmeFormSelector,
  inputSelector as pmeInputSelector,
  inputClassSelector as pmeInputClassSelector,
} from './pme-selectors.ts';
import type { TableDialogCallbackData } from './pme-state.ts';
import type { ResponseData } from '../types/ajax/response-data.d.ts';
import type { ParticipantFieldPropertyGetDefaultValue, ParticipantFieldPropertyGetResponse } from '../../build/ts-types/php-modules/Controller/DTO.ts';
import { type EnumParticipantFieldPropertyGet } from '../../build/ts-types/php-modules/Controller.ts';
import { REVERT_TO_DEFAULT } from '../../build/ts-types/php-modules/PageRenderer/CssClasses.ts';
import type { RationalNumber } from '../../build/ts-types/php-modules/Common.ts';
import { END_POINT as participantFieldsEndPoint } from '../../build/ts-types/php-modules/Controller/ProjectParticipantFieldsController.ts';
import {
  EnumParticipantFieldRequestTopic,
  EnumParticipantFieldRequestSubTopic,
} from '../../build/ts-types/php-modules/Controller.ts';
import { RESIZE_TARGET, WYSIWYG_EDITOR } from '../../build/ts-types/php-modules/Controller/CssClasses.ts';

require('jquery-ui/ui/widgets/autocomplete');
require('jquery-ui/themes/base/autocomplete.css');

const balancingAccountsAutocompleteOptions: JQueryUI.AutocompleteOptions = {
  source: [],
  position: { my: 'right center', at: 'left center', collision: 'flipfit flipfit' },
  minLength: 0,
  select(event, ui) {
    // trigger blur event for validation
    const $input = $(event.target) as JQuery<HTMLInputElement>;
    $input.val(ui.item.value);
    $input.trigger('blur');
  },
};

const pmeLabelInputSelector = 'td.label ' + pmeInputSelector + '[type="text"]';
const pmeValueInputSelector = [
  'td.input ' + pmeInputSelector + '[type="text"]',
  'td.input ' + pmeInputSelector + '[type="number"]',
].join(',');

const participantOptionHandlers = (
  container: string|JQuery,
  musicianId: number,
  projectId: number,
  dialogParameters?: TableDialogCallbackData,
) => {

  if (!musicianId) {
    return;
  }

  const $container = jq(container);

  fetchBalancingAccountsAutocompleteData(projectId)
    .then(() => {
      $container
        .find('tr.multiplicity-recurring')
        .filter('.data-type-liabilities, .data-type-receivables')
        .each(function() {
          const $row = $(this);
          const autocompleteFlavour = $row.hasClass('data-type-liabilities') ? 'expense' : 'income';
          $row.find<HTMLInputElement>('td.balancing-account ' + pmeInputSelector)
            .each(function() {
              const $input = $(this);
              const autocompleteOptions = { ...balancingAccountsAutocompleteOptions };
              autocompleteOptions.position.of = $input;
              autocompleteOptions.position.within = $input.closest('.ui-dialog');
              $input
                .autocomplete(autocompleteOptions)
                .on('focus', autocompleteFocusHandler);
              $input.autocomplete('option', 'source', balancingAccountsAutocompleteData[autocompleteFlavour]);
              $input.autocomplete('widget').css({ 'max-height': '100%', 'overflow-y': 'scroll' });
            });
        });
    });

  // AJAX download support
  $container
    .find<HTMLAnchorElement>('a.download-link.ajax-download')
    .off('click')
    .on('click', function() {
      const $this = $(this);
      fileDownload($this.attr('href')!, undefined, {
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
    .find<HTMLInputElement>(`tr.participant-field input.${REVERT_TO_DEFAULT}`)
    .off('click')
    .on('click', function() {
      const $self = $(this);
      const $inputElement = $self.parent().find(pmeInputClassSelector());
      const fieldId = $self.data('fieldId');
      const fieldProperty = ($self.data('fieldProperty') ?? 'defaultValue') as EnumParticipantFieldPropertyGet;

      const revertHandler = () => {
        $.post(
          generateAppUrl(`${participantFieldsEndPoint}/${EnumParticipantFieldRequestTopic.PROPERTY}/${EnumParticipantFieldRequestSubTopic.GET}`), {
            fieldId,
            property: fieldProperty,
          })
          .fail(function(xhr, status, errorThrown) {
            Ajax.handleError(xhr, status, errorThrown);
          })
          .done(function(data: ResponseData<ParticipantFieldPropertyGetResponse>) {
            if (!Ajax.validateResponse(data, ['fieldId', 'property', 'value'])) {
              return;
            }
            if (fieldProperty === 'defaultValue') {
              const value = data.value as ParticipantFieldPropertyGetDefaultValue;
              if ($inputElement.hasClass(WYSIWYG_EDITOR)) {
                WysiwygEditor.updateEditor($inputElement, value.data ?? '');
              } else if (isJQuerySelect($inputElement)) {
                selectedValues($inputElement, value.key);
              } else {
                $inputElement.val(value.data ?? '');
              }
            } else {
              const value = data.value as RationalNumber|undefined;
              // deposit, only a plain input element makes sense
              $inputElement.val(value ?? '');
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
          true,
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
    .find<HTMLInputElement>('input.show-empty-options')
    .off('change')
    .on('change', function(this: HTMLInputElement) {
      const $this = $(this);
      $this.closest('table').toggleClass('hide-empty-values', !$this.prop('checked'));
      $this.closest(`.${RESIZE_TARGET}`).trigger('resize');
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
    .find<HTMLInputElement>('input.regenerate')
    .off('click')
    .on('click', function() {
      const $this = $(this);
      const $row = $this.closest('tr');
      const field = {
        id: $row.data('fieldId'),
        name: $row.data('fieldName'),
      };
      const receivable = {
        key: $row.data('optionKey') as string,
        label: $row.find<HTMLInputElement>('td.label input.pme-input[type="text"]').val()!,
      };
      const participant = {
        musicianId,
        publicName: $pmeForm.find<HTMLInputElement>('input.pme-input.musician-public-name').val()!,
        personalPublicName: $pmeForm.find<HTMLInputElement>('input.pme-input.musician-personal-public-name').val()!,
      };
      const updateStrategy = $this.closest('table').find<HTMLSelectElement>('select.recurring-receivables-update-strategy').val()! as UpdateStrategy;

      $this.addClass('busy');
      confirmedReceivablesUpdate(field, [receivable], [participant], updateStrategy)
        .then(
          function(data, ...rest) {
            console.info('SUCCESS', { data, ...rest });
            if (data === false) {
              console.warn('"data === false" in success callback');
              return;
            }
            $row.find('input.pme-input.receivables, input.pme-input.liabilities').val(data.amounts[musicianId]);
          },
          function(...rest) {
            console.info('ERROR', { ...rest });
          },
        )
        .finally(() => $this.removeClass('busy'));

      return false;
    });

  $recurringReceivablesOperations
    .find('input.delete-undelete')
    .off('click')
    .on('click', function() {
      const $this = $(this);
      const $row = $this.closest('tr');
      // const fieldId = $row.data('fieldId');
      const optionKey = $row.data('optionKey');

      // could also search for name with field-id
      const inputs = $container
        .find('input[value="' + optionKey + '"]')
        .add($row.find('.pme-input, .operation.regenerate'));

      if ($row.hasClass('deleted')) {
        inputs.prop('disabled', false);
        $row.removeClass('deleted');
      } else {
        inputs.prop('disabled', true);
        $row.addClass('deleted');
      }

      return false;
    });

  $recurringReceivablesOperations
    .find('input.regenerate-all')
    .off('click')
    .on('click', function() {
      const $this = $(this);
      const updateStrategy = $this.closest('table').find('select.recurring-receivables-update-strategy').val()! as UpdateStrategy;
      const $row = $this.closest('tr');
      const field = {
        id: $row.data('fieldId'),
        name: $row.data('fieldName'),
      };
      const participant = {
        musicianId,
        publicName: $pmeForm.find<HTMLInputElement>('input.pme-input.musician-public-name').val()!,
        personalPublicName: $pmeForm.find<HTMLInputElement>('input.pme-input.musician-personal-public-name').val()!,
      };

      // or parse the Dom:
      const receivables: { key: string, label: string }[] = [];
      if (!$this.hasClass('no-progress')) {
        $row.closest('table').find('tr.field-datum').each(function() {
          const $row = $(this);
          const receivable = {
            key: $row.data('optionKey') as string,
            label: $row.find<HTMLInputElement>('td.label input.pme-input[type="text"]').val()!,
          };
          receivables.push(receivable);
        });
      }
      if (receivables.length === 0) {
        // this triggers bulk-update without progress
        receivables.push({ key: '', label: '' });
      }
      $this.addClass('busy');
      confirmedReceivablesUpdate(field, receivables, [participant], updateStrategy)
        .then(
          function(...rest) {
            console.info('SUCCESS', { ...rest });
          },
          function(...rest) {
            console.info('ERROR', { ...rest });
          },
        )
        .finally(() => {
          $this.removeClass('busy');
          // just trigger reload
          $container.find('form.pme-form input.pme-reload').first().trigger('click');
        });

      return false;
    });

  if (dialogParameters) {
    const ambientContainerSelector = dialogParameters.tableDialogOptions?.ambientContainerSelector;
    if (ambientContainerSelector) {
      $pmeForm
        .find('tr.participant-field.cloud-file, tr.participant-field.db-file, tr.participant-field.cloud-folder')
        .find('td.pme-value')
        .on('pme:upload-done pme:upload-deleted', '.file-upload-row', function() {
          $container.trigger('pmedialog:changed');
          submitOuterFormNoThrow(ambientContainerSelector);
        });
    }
  }
};

export default participantOptionHandlers;
