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

import { globalState, appName, $ } from './globals.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as PHPMyEdit from './pme.js';
import * as Notification from './notification.js';
import * as SelectUtils from './select-utils.js';
import * as Dialogs from './dialogs.js';
import * as DialogUtils from './dialog-utils.js';
import * as WysiwygEditor from './wysiwyg-editor.js';
import generateUrl from './generate-url.js';
import textareaResize from './textarea-resize.js';
import { rec as pmeRec } from './pme-record-id.js';
import './lock-input.js';
import { textInputSelector, nonTextInputSelector, textElementSelector } from '../util/css-selectors.js';
import {
  data as pmeData,
} from './pme-selectors.js';
import { showSuccess } from '@nextcloud/dialogs';
require('./jquery-readonly.js');
require('../legacy/nextcloud/jquery/octemplate.js');
require('jquery-ui/ui/widgets/autocomplete');
require('jquery-ui/themes/base/autocomplete.css');

require('./jquery-ui-progressbar.js');
require('./jquery-datetimepicker.js');

// NB: much of the visibility stuff is handled by CSS, e.g. which
// input is shown for which multiplicity.
require('project-participant-fields.scss');

/**
 * @param {number} projectId The id of the project.
 *
 * @param {string} multiplicity If given restrict to the given multiplicity.
 *
 * @param {string} type If given restrict to the given data-type.
 *
 * @returns {(Array|null)}
 */
const getProjectParticipantFields = async function(projectId, multiplicity, type) {
  try {
    return await $.get(generateUrl('projects/' + projectId + '/participant-fields'), {
      multiplicity,
      type,
    }).promise();
  } catch (xhr) {
    await new Promise((resolve) => Ajax.handleError(xhr, 'error', xhr.statusText, resolve));
    return null;
  }
};

/**
 * @param {number} fieldId The id of the project.
 *
 * @returns {(Array|null)}
 */
const getProjectParticipantFieldOptions = async function(fieldId) {
  try {
    return await $.get(generateUrl('projects/participant-fields/' + fieldId + '/options')).promise();
  } catch (xhr) {
    await new Promise((resolve) => Ajax.handleError(xhr, 'error', xhr.statusText, resolve));
    return null;
  }
};

/**
 * @param {number} projectId The id of the project.
 *
 * @returns {(Array|null)}
 */
const getProjectParticipants = async function(projectId) {
  try {
    return await $.get(generateUrl('projects/' + projectId + '/participants')).promise();
  } catch (xhr) {
    await new Promise((resolve) => Ajax.handleError(xhr, 'error', xhr.statusText, resolve));
    return null;
  }
};

const receivablesStatisticsKeys = ['added', 'removed', 'changed', 'skipped', 'amounts'];

/**
 * Update the given receivables, display a progress bar, handle errors
 * and user cancel.
 *
 * @param {object} field The database id of the field to update.
 *
 * @param {Array} receivables Array of receivables to update.
 *
 * @param {Array} participants Arrray of participants to update.
 *
 * @param {string} updateStrategy The conflict resolution strategy.
 *
 * @returns {boolean}
 */
const confirmedReceivablesUpdate = async function(field, receivables, participants, updateStrategy) {
  console.info('RECEIVABLES', receivables);
  let confirmed = true;
  if (updateStrategy === 'replace') {
    confirmed = await Dialogs.confirm(
      t(appName, 'Update strategy "{updateStrategy}" replaces the value of existing receivables, please confirm that you want to continue.', {
        updateStrategy: t(appName, updateStrategy),
      }),
      t(appName, 'Overwrite Existing Records?')
    );
  }
  if (!confirmed) {
    return false;
  }

  const single = receivables.length === 1 && participants.length === 1;

  const progressWrapperTemplate = $('#progressWrapperTemplate');
  const progressWrapperId = 'project-participant-fields-progress';
  const $progressWrapper = progressWrapperTemplate.octemplate({
    wrapperId: progressWrapperId,
    caption: '',
    label: '',
  });
  const $oldProgressWrapper = $('#' + progressWrapperId);
  if ($oldProgressWrapper.length === 0) {
    $('body').append($progressWrapper);
  } else {
    $oldProgressWrapper.replaceWith($progressWrapper);
  }
  const $progressBar = $progressWrapper.find('span.progressbar');
  $progressBar.progressbar({ value: 0, max: 100 });
  const $label = $progressBar.find('.label');
  let cancel = null;
  if (!single) {
    await new Promise((resolve) => {
      $progressWrapper.cafevDialog({
        title: t(appName, 'Updating recurring receivables'),
        width: 'auto',
        height: 'auto',
        modal: true,
        closeOnEscape: false,
        resizable: false,
        dialogClass: 'progress-status progress',
        buttons: [
          {
            text: t(appName, 'cancel'),
            title: t(appName, 'Cancel the operation in progress.'),
            class: 'cancel',
            click() {
              cancel = t(appName, 'Cancelled by User');
              $progressWrapper.dialog('close');
            },
          },
        ],
        open() {
          const dialogHolder = $(this);
          DialogUtils.toBackButton(dialogHolder);
          DialogUtils.customCloseButton(dialogHolder, function(event, container) {
            $progressWrapper.dialog('widget').find('.cancel.ui-button').trigger('click');
            return false;
          });
          resolve();
        },
        close() {
          $progressWrapper.dialog('destroy');
          $progressWrapper.hide();
        },
      });
    });
  }

  const fieldName = field.name;
  const fieldId = field.id;
  const totals = receivables.length * participants.length;
  let current = 0;
  const statistics = {};
  for (const key of receivablesStatisticsKeys) {
    statistics[key] = 0;
  }
  statistics.cancel = false;
  for (const receivable of receivables) {
    if (cancel) {
      break;
    }
    const key = receivable.key;
    const receivableLabel = receivable.label;
    console.info('RECEIVABLE', key, receivableLabel);
    for (const participant of participants) {
      if (cancel) {
        break;
      }
      const musicianId = participant.musicianId;
      const musicianName = participant.personalPublicName;
      console.info('MUSICIAN', musicianId, musicianName);

      single || $progressWrapper.dialog(
        'option',
        'title',
        t(appName, 'Updating receivables for {fieldName}, {receivableLabel}, {musicianName}',
          { fieldName, receivableLabel, musicianName })
      );

      const request = 'option/regenerate';
      try {
        const data = await $.post(
          generateUrl('projects/participant-fields/' + request), {
            data: {
              fieldId,
              key,
              musicianId,
              updateStrategy,
            },
          }).promise();
        console.info(data);
        for (const key of receivablesStatisticsKeys) {
          statistics[key] += data[key];
        }
        ++current;
        const currentPercentage = current / totals * 100.0;
        single || $progressBar.progressbar('option', 'value', currentPercentage);
        single || $label.html(currentPercentage.toFixed(1) + '%');
        showSuccess(
          t(appName, 'BLAH {musicianName}, {receivableLabel}: "{message}".', {
            musicianName, receivableLabel, message: data.message.join('; '),
          })
        );
      } catch (xhr) {
        const failData = await new Promise((resolve) => Ajax.handleError(xhr, 'error', xhr.statusText, resolve));
        cancel = t(appName, 'Error');
        single || $progressWrapper.dialog('close');
        console.info('FAIL DATA', failData);
        throw new Error(failData.error, { cause: failData.xhr });
      }
    }
  }
  statistics.cancel = cancel;
  if (!cancel) {
    single || $progressWrapper.dialog('close');
  }
  return statistics;
};

const ready = function(selector, resizeCB) {
  const $container = $(selector);

  // @todo Most of the stuff below is a no-op in list-view. We should
  // bail-out early.

  const $tableTab = $container.find('select.tab');
  const $newTab = $container.find('input.new-tab');
  $newTab.prop('readonly', !!SelectUtils.selected($tableTab));
  $container.on('change', 'select.tab', function(event) {
    $newTab.prop('readonly', !!SelectUtils.selected($tableTab));
    return false;
  });

  const setFieldTypeCssClass = function(data) {
    if (!data) {
      return;
    }
    const dataType = data.dataType;
    const multiplicity = data.multiplicity;
    const dueDate = data.depositDueDate;
    const multiplicityClass = 'multiplicity-' + multiplicity;
    const dataTypeClass = 'data-type-' + dataType;
    const depositDueDateClass = 'deposit-due-date-' + dueDate;
    $container.find('tr.multiplicity')
      .removeClass(function(index, className) {
        return (className.match(/\b(multiplicity|data-type|deposit-due-date)-\S+/g) || []).join(' ');
      })
      .addClass([multiplicityClass, dataTypeClass, depositDueDateClass]);
    $container.find('tr.data-options table.data-options')
      .removeClass(function(index, className) {
        return (className.match(/\b(multiplicity|data-type|deposit-due-date)-\S+/g) || []).join(' ');
      })
      .addClass([multiplicityClass, dataTypeClass]);

    $container.find('[class*="-multiplicity-required"], [class*="-data-type-required"], [class*="-deposit-due-date-required"]').each(function(index) {
      const $this = $(this);
      let required = false;
      for (const className of $this.attr('class').split(/\s+/)) {
        switch (className) {
        case multiplicity + '-multiplicity-required':
        case dataType + '-data-type-required':
        case dueDate + '-deposit-due-date-required':
        case 'multiplicity-' + multiplicity + '-' + dueDate + '-deposit-due-date-required':
          required = true;
          break;
        default:
          break;
        }
        if (className === 'not-multiplicity-' + multiplicity + '-' + dueDate + '-deposit-due-date-required') {
          required = false;
          break; // kill-switch, bail out.
        }
        const onlyMultiplicityRequired = className.match(/only-multiplicity-(.*)-multiplicity-required/);
        if (onlyMultiplicityRequired && onlyMultiplicityRequired[1] !== multiplicity) {
          required = false;
          break; // kill-switch, bail out.
        }
      }
      $this.prop('required', required);
    });
    $container.find('.data-type-html-disabled, .data-type-html-disabled *').prop('disabled', dataType === 'html');
    $container.find('.not-data-type-html-disabled, .not-data-type-html-disabled *').prop('disabled', dataType !== 'html');

    $container.find('.data-type-html-wysiwyg-editor').each(function() {
      const $this = $(this);
      if (dataType !== 'html') {
        WysiwygEditor.removeEditor($this);
      } else {
        WysiwygEditor.addEditor($this, resizeCB);
      }
    });

    const inputData = $container.find('table.data-options').data('size');
    const $dataInputs = $container
      .find(
        'tr.pme-row.' + 'data-options-' + multiplicity + ' td.pme-value'
          + ', '
          + 'tr.pme-row.data-options td.pme-value table.' + multiplicityClass + ' tr:not(.generator, .placeholder)')
      .find('input.field-data')
      .not(nonTextInputSelector);

    const dateTimePickerSelector = 'body > .xdsoft_datetimepicker';
    $dataInputs.each(function() {
      const $this = $(this);
      if ($this.hasClass('hasDatepicker')) {
        $this.datepicker('destroy');
      }
      $this.datetimepicker('destroy');
      if (inputData) {
        const inputSize = inputData[dataType] || inputData.default;
        if (inputSize) {
          $this.attr('size', inputSize);
        } else {
          console.warn('No input size defined, even not the default', $this, inputData, dataType);
        }
      }
    });
    $(dateTimePickerSelector).remove();
    switch (dataType) {
    case 'receivables':
    case 'liabilities':
      $dataInputs
        .attr('type', 'number')
        .attr('step', '0.01');
      break;
    case 'date':
      $dataInputs
        .attr('type', 'text')
        .removeAttr('step')
        .datepicker({
          minDate: '01.01.1940', // birthday
        });
      // $dataInputs
      //   .off('change')
      //   .on('change', function(event) {
      //     const $this = $(this);
      //     console.info('DATE', $this.datepicker('getDate'));
      //   });
      break;
    case 'datetime':
      $dataInputs
        .attr('type', 'text')
        .removeAttr('step')
        .datetimepicker({
          step: 5,
        });
      break;
    default:
      $dataInputs
        .attr('type', 'text')
        .removeAttr('step');
      break;
    }
  };

  const isMonetaryType = dataType => dataType === 'receivables' || dataType === 'liabilities';

  const fieldTypeData = function() {
    const multiplicity = $container.find('select.multiplicity');
    const dataType = $container.find('select.data-type');
    const depositDueDate = $container.find('input.deposit-due-date');
    if (multiplicity.length > 0 && dataType.length > 0) {
      const data = {
        multiplicity: multiplicity.val(),
        dataType: dataType.val(),
        depositDueDate: (isMonetaryType(dataType) && depositDueDate.val() !== '') ? 'set' : 'unset',
      };
      return data;
    }
    const elem = $container.find('td.pme-value.field-type .data');
    if (elem.length <= 0) {
      return null;
    }
    return elem.data('data');
  };

  /**
   * Hide the header for single choice options because in View and
   * Delete mode the mult-value table is used as well.
   */
  const allowedHeaderVisibility = function() {
    const allowedValuesTable = $container.find('table.data-options');
    if (allowedValuesTable.hasClass('multiplicity-multiple')
        || allowedValuesTable.hasClass('multiplicity-parallel')
        || allowedValuesTable.hasClass('multiplicity-recurring')
        || allowedValuesTable.hasClass('multiplicity-groups-of-people')
        || allowedValuesTable.find('tbody tr:visible').length >= 1) {
      allowedValuesTable.find('thead').show();
    } else {
      allowedValuesTable.find('thead').hide();
    }
  };

  // Field-Type Selectors
  $container.on(
    'change', [
      'select.multiplicity',
      'select.data-type',
      'input.deposit-due-date',
    ].join(), function(event) {
      const depositDueDateInput = $container.find('input.deposit-due-date');
      const multiplicitySelect = $container.find('select.multiplicity');
      const dataTypeSelect = $container.find('select.data-type');
      const multiplicity = multiplicitySelect.val();
      const dataTypeMask = SelectUtils.optionByValue(multiplicitySelect, multiplicity).data('data');
      let dataType = dataTypeSelect.val();
      const enabledTypes = [];
      dataTypeSelect.find('option').each(function(index) {
        const $option = $(this);
        const value = $option.val();
        if (value === '') {
          return;
        }
        const enabled = dataTypeMask.indexOf(value) === -1;
        if (enabled) {
          enabledTypes.push(value);
        }
        $option.prop('disabled', !enabled);
      });
      if (enabledTypes.indexOf(dataType) === -1) {
        if (enabledTypes.length > 0) {
          dataType = enabledTypes[0];
          dataTypeSelect.val(dataType);
        } else {
          dataType = '';
          console.error('no data types left to enable');
        }
      }
      dataTypeSelect.trigger('chosen:updated');
      const depositDueDate = (isMonetaryType(dataType) && depositDueDateInput.val() !== '') ? 'set' : 'unset';
      setFieldTypeCssClass({ multiplicity, dataType, depositDueDate });
      allowedHeaderVisibility();
      console.debug('RESIZECB');
      resizeCB();

      $.fn.cafevTooltip.remove(); // remove left-overs

      return false;
    });

  const $multiplicitySelect = $container.find('select.multiplicity.pme-input');
  const $multiplicityLock = $container.find('#pme-field-multiplicity-lock');
  const $dataTypeSelect = $container.find('select.data-type.pme-input');
  const $dataTypeLock = $container.find('#pme-field-data-type-lock');

  $multiplicitySelect.trigger('change');
  const usage = $multiplicitySelect.data('fieldUsage');

  $multiplicitySelect.readonly(usage > 0);
  $multiplicityLock.prop('checked', usage > 0);

  $dataTypeSelect.readonly(usage > 0);
  $dataTypeLock.prop('checked', usage > 0);

  $container.on('change', '#pme-field-multiplicity-lock', function(event) {
    const $this = $(this);
    const checked = $this.prop('checked');
    if (!checked) {
      Dialogs.confirm(
        t(appName, 'This field already is filled out for some participants. Changing the multiplicity is still possible in some cases, but generally irrevertible. The app will allow changes from checkboxes and multiple-choice fields to general "simple" free-form content and disallow any other changes. Selected choices will then be collected into the remaining free-form field. Mostly probably the outcome will be broken unless the data type is "text" or "HTML-text".'),
        t(appName, 'Really allow changing the multiplicity?'), {
          default: 'cancel',
          callback(answer) {
            const checked = !answer;
            $this.prop('checked', checked);
            const $multiplicitySelect = $this.closest('td').find('select.multiplicity.pme-input');
            $multiplicitySelect.readonly(checked);
          },
        }
      );
    } else {
      const $multiplicitySelect = $this.closest('td').find('select.multiplicity.pme-input');
      $multiplicitySelect.readonly(checked);
    }
  });

  $container.on('change', '#pme-field-data-type-lock', function(event) {
    const $this = $(this);
    const checked = $this.prop('checked');
    if (!checked) {
      Dialogs.confirm(
        t(appName, 'This field already is filled out for some participants. Changing the data-type is still possible in some cases, but does not always make sense. The app will allow changes between data-types and try to be smart, but please be prepared that the results might look unexpected.'),
        t(appName, 'Really allow changing the data-type?'), {
          default: 'cancel',
          callback(answer) {
            const checked = !answer;
            $this.prop('checked', checked);
            const $dataTypeSelect = $this.closest('td').find('select.data-type.pme-input');
            $dataTypeSelect.readonly(checked);
          },
        }
      );
    } else {
      const $dataTypeSelect = $this.closest('td').find('select.data-type.pme-input');
      $dataTypeSelect.readonly(checked);
    }
  });

  $container.on('keypress', 'tr.data-options input' + textInputSelector, function(event) {
    let pressedKey;
    if (event.which) {
      pressedKey = event.which;
    } else {
      pressedKey = event.keyCode;
    }
    if (pressedKey === 13) { // enter pressed
      event.stopImmediatePropagation();
      $(this).blur();
      return false;
    }
    return true; // other key pressed
  });

  const $dataOptionsTable = $container.find('table.data-options');
  $container.on('change', '#data-options-show-deleted', function(event) {
    if ($(this).prop('checked')) {
      $dataOptionsTable.addClass('show-deleted');
    } else {
      $dataOptionsTable.removeClass('show-deleted');
    }
    $.fn.cafevTooltip.remove();
    allowedHeaderVisibility();
    resizeCB();
    return false;
  });

  $container.on('change', '#data-options-show-data', function(event) {
    if ($(this).prop('checked')) {
      $dataOptionsTable.addClass('show-data');
    } else {
      $dataOptionsTable.removeClass('show-data');
    }
    $.fn.cafevTooltip.remove();
    allowedHeaderVisibility();
    resizeCB();
    return false;
  });

  $container.on('change', 'select.default-multi-value', function(event) {
    const $self = $(this);
    $container.find('input.pme-input.default-single-value').val(SelectUtils.selected($self));
    return false;
  });

  $container.on('blur', 'input.pme-input.default-single-value', function(event) {
    const self = $(this);
    const dfltSelect = $container.find('select.default-multi-value');
    dfltSelect.children('option[value="' + self.val() + '"]').prop('selected', true);
    dfltSelect.trigger('chosen:updated');
    return false;
  });

  $container.on('click', 'tr.data-options input.regenerate', function(event) {
    const $self = $(this);
    const $row = $self.closest('tr.data-options');
    const field = {
      id: pmeRec($container),
      name: $container.find('input[name="' + pmeData('name') + '"]').val(),
    };
    const receivable = {
      key: $row.find('input.field-key').val(),
      label: $row.find('input.field-label').val(),
      data: $row.find('input.field-data').val(),
      limit: $row.find('input.field-limit').val(),
    };
    const projectId = $container.find('input[name="' + pmeData('project_id') + '"]').val();
    (async () => {
      const participants = await getProjectParticipants(projectId);
      if (!participants) {
        return;
      }
      const updateStrategy = $self.closest('table').find('select.recurring-receivables-update-strategy').val();

      $self.addClass('busy');
      confirmedReceivablesUpdate(field, [receivable], participants, updateStrategy)
        .then(
          function() {
            console.info('SUCCESS', ...arguments);
          },
          function() {
            console.info('ERROR', ...arguments);
          }
        )
        .finally(() => $self.removeClass('busy'));
    })();
    return false;
  });

  $container.on('click', 'tr.data-options input.regenerate-all', async function(event) {
    const $self = $(this);
    const $row = $self.closest('tr.data-options');
    const field = {
      id: $row.data('fieldId'),
      name: $container.find('input[name="' + pmeData('name') + '"]').val(),
    };
    const updateStrategy = $row.find('select.recurring-receivables-update-strategy').val();
    const projectId = $container.find('input[name="' + pmeData('project_id') + '"]').val();
    const participants = await getProjectParticipants(projectId);
    if (!participants) {
      return false;
    }
    console.info('PARTICIPANTS', participants);

    // let options = await $.get(generateUrl('projects/participant-fields/' + fieldId + '/options'));
    // console.info('OPTIONS', options);

    // or parse the Dom:
    const receivables = [];
    $row.closest('table').find('tr.data-option-row.active').each(function() {
      const $row = $(this);
      console.info('ROW', $row);
      const receivable = {
        key: $row.find('input.field-key').val(),
        label: $row.find('input.field-label').val(),
        data: $row.find('input.field-data').val(),
        limit: $row.find('input.field-limit').val(),
      };
      receivables.push(receivable);
    });

    $self.addClass('busy');
    confirmedReceivablesUpdate(field, receivables, participants, updateStrategy)
      .then(
        function() {
          console.info('SUCCESS', ...arguments);
        },
        function() {
          console.info('ERROR', ...arguments);
        }
      )
      .finally(() => $self.removeClass('busy'));
    return false;
  });

  $container.on('click', 'tr.data-options input.generator-run', function(event) {
    const $self = $(this);
    const $row = $self.closest('tr.data-options');
    const fieldId = $row.data('fieldId');
    // defer submit until after validation.
    const submitDefer = PHPMyEdit.deferReload($container);
    const cleanup = function() {
      $self.removeClass('busy');
      setFieldTypeCssClass(fieldTypeData());
      submitDefer.resolve();
    };
    const request = 'generator/run';
    const startDate = $self.closest('tr').find('.field-limit');
    $self.addClass('busy');
    $.post(
      generateUrl('projects/participant-fields/' + request), {
        data: {
          fieldId,
          startDate: startDate.val(),
        },
      })
      .fail(function(xhr, status, errorThrown) {
        Ajax.handleError(xhr, status, errorThrown, cleanup);
      })
      .done(function(data) {
        if (!Ajax.validateResponse(data, ['startDate', 'dataOptionFormInputs'], cleanup)) {
          return;
        }
        const body = $self.closest('tbody');
        body.find('tr').not('.generator, .placeholder').remove();
        body.parents('table').find('thead').show();
        const tail = body.children().first();
        for (const input of data.dataOptionFormInputs) {
          tail.before(input);
        }
        lockGeneratedValues($container);
        startDate.val(data.startDate);
        resizeCB();
        cleanup();
        Notification.messages(data.message);
      });
    return false;
  });

  $container.on('click', 'tr.data-options input.delete-undelete', function(event) {
    const $self = $(this);
    const $row = $self.closest('tr.data-options');
    const dfltSelect = $container.find('select.default-multi-value');
    let used = $row.data('used');
    used = !(!used || used === 'unused');
    if ($row.data('deleted') !== '') {
      // undelete
      $row.data('deleted', '');
      $row.switchClass('deleted', 'active');
      $row.find('input.field-deleted').val('');
      $row.find('input' + textInputSelector + ':not(.field-key), textarea').prop('readonly', false);
      $row.find('input.operation').prop('disabled', false);
      const key = $row.find('input.field-key');
      const label = $row.find('input.field-label');
      const option = '<option value="' + key.val() + '">' + label.val() + '</option>';
      dfltSelect.children('option').first().after(option);
    } else {
      const key = $row.find('input.field-key').val();
      if (!used) {
        // just remove the row
        $row.remove();
      } else {
        // must not delete, mark as inactive
        $row.data('deleted', Date.now() / 1000.0);
        $row.switchClass('active', 'deleted');
        $row.find('input.field-deleted').val($row.data('deleted'));
        $row.find('input' + textInputSelector + ', textarea').prop('readonly', true);
        $row.find('input.operation.regenerate').prop('disabled', true);
      }
      dfltSelect.find('option[value="' + key + '"]').remove();
    }
    setTimeout(function() {
      dfltSelect.trigger('chosen:updated');
      $.fn.cafevTooltip.remove();
      allowedHeaderVisibility();
      resizeCB();
    }, 400);
    return false;
  });

  // validate monetary inputs
  $container.on(
    'blur',
    [
      'tr.multiplicity.data-type-receivables ~ tr.data-options-single input' + textInputSelector,
      'tr.multiplicity.data-type-receivables:not(.multiplicity-recurring) ~ tr.data-options tr.data-options:not(.generator) input.field-data' + textInputSelector,
      'tr.multiplicity.data-type-liabilities ~ tr.data-options-single input' + textInputSelector,
      'tr.multiplicity.data-type-liabilities:not(.multiplicity-recurring) ~ tr.data-options tr.data-options:not(.generator) input.field-data' + textInputSelector,
    ].join(),
    function(event) {
      const self = $(this);
      if (self.prop('readonly')) {
        return false;
      }
      const amount = self.val().trim();
      if (amount === '') {
        self.val('');
        return false;
      }

      // defer submit until after validation.
      const submitDefer = PHPMyEdit.deferReload($container);
      self.prop('readonly', true);

      const cleanup = function() {
        self.prop('readonly', false);
        submitDefer.resolve();
      };

      $.post(
        generateUrl('validate/general/monetary-value'), { value: amount })
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown, cleanup);
        })
        .done(function(data) {
          if (!Ajax.validateResponse(data, ['amount'], cleanup)) {
            return;
          }
          self.val(data.amount);
          cleanup();
        });
      return false;
    });

  const lockGeneratedValuesRow = function($row) {
    const generated = $row.find('input' + textInputSelector + ', textarea');
    generated.each(function(index) {
      const $this = $(this);
      if ((!$this.hasClass('expert-mode-only') && !$this.hasClass('not-expert-mode-hidden'))
          || (!$this.hasClass('finance-mode-only') && !$this.hasClass('not-finance-mode-hidden'))) {
        $this.lockUnlock({
          locked: $this.val().trim() !== '',
        });
      }
    });
  };

  const lockGeneratedValues = function($container) {
    // generated options
    const generatedSelector = 'tr.data-options table.multiplicity-recurring tr.data-line.data-options:not(.generator)';
    lockGeneratedValuesRow($container.find(generatedSelector));
  };

  lockGeneratedValues($container);

  // generator input
  const generatorSelector = 'tr.data-options table.multiplicity-recurring tr.data-line.generator input' + textInputSelector;
  const generator = $container.find(generatorSelector);
  generator.each(function(index) {
    const $this = $(this);
    $this.lockUnlock({
      locked: $this.val().trim() !== '',
    });
    if ($this.is('.field-limit')) {
      $this.datepicker({
        minDate: '01.01.2000', // no receivables before this time
      });
    }
  });

  // generated due date
  const dueDate = $container.find('tr.multiplicity-recurring ~ tr.due-date td.pme-value input').not(nonTextInputSelector);
  if (dueDate.length > 0) {
    dueDate.lockUnlock({
      locked: dueDate.val().trim() !== '',
    });
  }

  $container.on(
    'blur',
    generatorSelector + '.field-data',
    function(event) {
      const $self = $(this);
      if ($self.prop('readonly')) {
        return false;
      }
      const $row = $self.closest('tr.data-options');

      const request = 'generator/define';
      const data = $.extend({}, fieldTypeData(), $row.data());
      const allowed = $row.find(textElementSelector);
      const postData = $.param({ request, data })
            + '&' + allowed.serialize();

      // defer submit until after validation.
      const submitDefer = PHPMyEdit.deferReload($container);
      allowed.each(function(index) {
        const $this = $(this);
        $this.data('readonly-saved', $this.prop('readonly'));
        $this.prop('readonly', true);
      });
      const cleanup = function() {
        allowed.each(function(index) {
          const $this = $(this);
          $this.prop('readonly', $this.data('readonly-saved') || $this.hasClass('readonly'));
          $this.removeData('readonly-saved');
        });
        submitDefer.resolve();
      };

      $.post(
        generateUrl('projects/participant-fields/' + request),
        postData)
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown, cleanup);
        })
        .done(function(data) {
          if (!Ajax.validateResponse(
            data,
            ['value', 'slug', 'operationLabels', 'availableUpdateStrategies'],
            cleanup)) {
            return;
          }

          if (data.value !== $self.val()) {
            $self.val(data.value);
          }

          const fieldId = parseInt($row.data('fieldId'));
          const empty = $self.val().trim() === '';
          if (empty) {
            $self.removeClass('readonly');
          } else {
            $self.addClass('readonly');
          }
          $self.lockUnlock('lock', !empty);
          $row.find('.operation.generator-run').prop('disabled', empty || !(fieldId > 0));

          const $table = $row.closest('table');
          const oldGeneratorSlug = $row.data('generatorSlug');
          $table.removeClass('recurring-generator-' + oldGeneratorSlug);
          $table.addClass('recurring-generator-' + data.slug);
          $row.data('generatorSlug', data.slug);
          $row.removeClass('update-strategy-count-' + $row.data('availableUpdateStrategies').length);
          $row.data('availableUpdateStrategies', data.availableUpdateStrategies);
          $row.addClass('update-strategy-count-' + $row.data('availableUpdateStrategies').length);

          const operationClass = (operation, value) => [
            'recurring',
            operation,
            (value ? 'enabled' : 'disabled'),
          ].join('-');
          for (const [operation, value] of Object.entries(data.operationLabels)) {
            $table.removeClass([
              operationClass(operation, true),
              operationClass(operation, false),
            ]);
            $table.addClass(operationClass(operation, value));
          }

          // adjust update strategy options
          const $updateStrategies = $row.find('select.recurring-receivables-update-strategy');
          const defaultStrategy = $updateStrategies.data('defaultValue');
          $updateStrategies.find('option').each(function() {
            const $option = $(this);
            const optionValue = $option.val();
            if (optionValue !== '') {
              $option.prop('disabled', data.availableUpdateStrategies.indexOf($option.val()) === -1);
              if ($option.prop('disabled')) {
                $option.prop('selected', false);
              } else if (optionValue === defaultStrategy) {
                $updateStrategies.val(defaultStrategy);
              }
            }
          });
          if (data.availableUpdateStrategies.length === 1) {
            $updateStrategies.val(data.availableUpdateStrategies[0]);
          }

          Notification.messages(data.message);

          cleanup();
        });
      return false;
    });

  // multi-field input matrix
  $container.on(
    'blur', [
      'tr.data-options tr.data-line:not(.generator) input' + textInputSelector,
      'tr.data-options tr.data-line textarea',
    ].join(),
    function(event) {
      const self = $(this);
      if (self.prop('readonly')) {
        return false;
      }
      const $row = self.closest('tr.data-options');
      const $table = $row.closest('table.data-options');
      const placeHolder = $row.hasClass('placeholder');
      if (placeHolder && self.val().trim() === '') {
        // don't add empty fields (but of course allow to remove field data)
        self.val('');
        return false;
      }

      // default data selector, if applicable
      const dflt = $container.find('select.default-multi-value');

      const request = 'option/define';
      const data = $.extend({ default: dflt.val() }, fieldTypeData(), $row.data());
      const allowed = $row.find(textElementSelector);
      const postData = $.param({ request, data })
            + '&' + allowed.serialize();

      // defer submit until after validation.
      const submitDefer = PHPMyEdit.deferReload($container);
      allowed.prop('readonly', true);
      const cleanup = function() {
        if (!allowed.hasClass('readonly')) {
          allowed.prop('readonly', false);
        }
        setFieldTypeCssClass(fieldTypeData());
        submitDefer.resolve();
      };

      $.post(
        generateUrl('projects/participant-fields/' + request),
        postData)
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown, cleanup);
        })
        .done(function(data) {
          if (!Ajax.validateResponse(
            data,
            ['dataOptionSelectOption', 'dataOptionFormInputs'],
            cleanup)) {
            return;
          }
          const option = data.dataOptionSelectOption;
          const input = data.dataOptionFormInputs;
          $.fn.cafevTooltip.remove();
          if (placeHolder) {
            $row.parents('table').find('thead').show();
            $row.before(input).prev().find('input, textarea').cafevTooltip({ placement: 'auto right' });
            self.val('');
            $row.data('index', +$row.data('index') + 1); // next index
            resizeCB();
          } else {
            const $nextRow = $row.next();
            $row.replaceWith(input);
            const $newRow = $nextRow.prev();
            $newRow.find('input, textarea').cafevTooltip({ placement: 'auto right' });
            if ($table.hasClass('multiplicity-recurring')) {
              lockGeneratedValuesRow($newRow);
            }
          }
          // get the key <-> value connection right for the default selector
          const newValue = $(option).val();
          const oldOption = dflt.find('option[value="' + newValue + '"]');
          if (oldOption.length > 0) {
            oldOption.replaceWith(option);
          } else {
            dflt.children('option').first().after(option);
          }
          dflt.trigger('chosen:updated');

          if (globalState.toolTipsEnabled) {
            $.fn.cafevTooltip.enable();
          } else {
            $.fn.cafevTooltip.disable();
          }

          cleanup();
        });
      return false;
    });

  // When a reader-group is removed, we also deselect it from the
  // writers. This -- of course -- only works if initially
  // the readers and writers list is in a sane state ;)
  $container.on('change', 'select.readers', function(event) {
    console.log('readers change');
    const $self = $(this);

    let changed = false;
    const $writers = $container.find('select.writers');
    SelectUtils.options($self).not(':selected').each(function() {
      const $writer = SelectUtils.optionByValue($writers, this.value);
      if ($writer.prop('selected')) {
        $writer.prop('selected', false);
        changed = true;
      }
    });
    if (changed) {
      SelectUtils.refreshWidget($writers);
    }
    return false;
  });

  // When a writer-group is added, then add it to the
  // readers as well ;)
  $container.on('change', 'select.writers', function(event) {
    const $self = $(this);

    let changed = false;
    const $readers = $container.find('select.readers');
    SelectUtils.selectedOptions($self).each(function() {
      const $reader = SelectUtils.optionByValue($readers, this.value);
      if (!$reader.prop('selected')) {
        $reader.prop('selected', true);
        changed = true;
      }
    });
    if (changed) {
      SelectUtils.refreshWidget($readers);
    }
    return false;
  });

  const tableContainerId = PHPMyEdit.idSelector('table-container');

  // TODO: check whether these are still necessary
  $container.on('chosen:showing_dropdown', tableContainerId + ' select', function(event) {
    console.log('chosen:showing_dropdown');
    // const widget = $container.cafevDialog('widget');
    // const tableContainer = $container.find(tableContainerId);
    // widget.css('overflow', 'visible');
    // $container.css('overflow', 'visible');
    // tableContainer.css('overflow', 'visible');
    return true;
  });

  // TODO: check whether these are still necessary
  $container.on('chosen:hiding_dropdown', tableContainerId + ' select', function(event) {
    console.log('chosen:hiding_dropdown');
    // const widget = $container.cafevDialog('widget');
    // const tableContainer = $container.find(tableContainerId);
    // tableContainer.css('overflow', '');
    // $container.css('overflow', '');
    // widget.css('overflow', '');
    return true;
  });

  $container.on('chosen:update', 'select.writers, select.readers', function(event) {
    resizeCB();
    return false;
  });

  setFieldTypeCssClass(fieldTypeData());

  allowedHeaderVisibility();

  // set autocomplete for generator selection
  const generatorRow = $container.find('tr.data-options.generator');
  const generators = generatorRow.data('generators');
  generatorRow.find('input.field-data')
    .autocomplete({
      source: generators,
      position: { my: 'left bottom', at: 'left top', collision: 'none' },
      minLength: 0,
      select(event, ui) {
        // trigger blur event for validation
        const $input = $(event.target);
        $input.val(ui.item.value);
        $input.blur();
      },
    })
    .on('focus', function() {
      const $this = $(this);
      if (!$this.autocomplete('widget').is(':visible')
          && !$this.prop('disabled') && !$this.prop('readonly')) {
        $(this).autocomplete('search', '');
      }
    });

  // synthesize resize events for textareas.
  textareaResize($container, 'textarea.field-tooltip, textarea.participant-field-tooltip, textarea.pme-input');
  resizeCB();
};

const documentReady = function() {

  CAFEVDB.addReadyCallback(function() {
    // const $container = PHPMyEdit.container();
    // if (!$container.hasClass('project-participant-fields')) {
    //   return; // not for us
    // }
    // ready(); // ????
  });

};

export {
  ready,
  documentReady,
  confirmedReceivablesUpdate,
  getProjectParticipants,
  getProjectParticipantFields,
  getProjectParticipantFieldOptions,
  receivablesStatisticsKeys,
};
