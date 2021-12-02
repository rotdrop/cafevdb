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

import { globalState, appName, $ } from './globals.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as PHPMyEdit from './pme.js';
import * as Notification from './notification.js';
import * as SelectUtils from './select-utils.js';
import * as Dialogs from './dialogs.js';
import * as DialogUtils from './dialog-utils.js';
import * as ProgressStatus from './progress-status.js';
import * as WysiwygEditor from './wysiwyg-editor.js';
import generateUrl from './generate-url.js';
import textareaResize from './textarea-resize.js';
import { rec as pmeRec } from './pme-record-id.js';
import './lock-input.js';
import { textInputSelector, nonTextInputSelector, textElementSelector } from '../util/css-selectors.js';
require('../legacy/nextcloud/jquery/octemplate.js');
require('jquery-ui/ui/widgets/autocomplete');
require('jquery-ui/themes/base/autocomplete.css');

require('./jquery-ui-progressbar.js');
require('./jquery-datetimepicker.js');

// NB: much of the visibility stuff is handled by CSS, e.g. which
// input is shown for which multiplicity.
require('project-participant-fields.scss');

const confirmedReceivablesUpdate = function(updateStrategy, requestHandler, single) {
  const handlerWithProgress = function() {
    ProgressStatus.create(-1, 0, { field: null, musician: '', receivable: '' })
      .fail(Ajax.handleError)
      .done(function(data) {
        if (!Ajax.validateResponse(data, ['id'])) {
          return;
        }
        const progressToken = data.id;
        const progressWrapperTemplate = $('#progressWrapperTemplate');
        const progressWrapperId = 'project-participant-fields-progress';
        const progressWrapper = progressWrapperTemplate.octemplate({
          wrapperId: progressWrapperId,
          caption: '',
          label: '',
        });
        const oldProgressWrapper = $('#' + progressWrapperId);
        if (oldProgressWrapper.length === 0) {
          $('body').append(progressWrapper);
        } else {
          oldProgressWrapper.replaceWith(progressWrapper);
        }
        progressWrapper.find('span.progressbar').progressbar({ value: 0, max: 100 });
        let progressOpen = false;
        progressWrapper.cafevDialog({
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
                // if (ajaxRequest) {
                //   ajaxRequest.abort('cancelled');
                //   ajaxRequest = null;
                // }
                $(this).dialog('close');
              },
            },
          ],
          open() {
            const dialogHolder = $(this);
            DialogUtils.toBackButton(dialogHolder);
            DialogUtils.customCloseButton(dialogHolder, function(event, container) {
              dialogHolder.dialog('widget').find('.cancel.ui-button').trigger('click');
              return false;
            });
            progressOpen = true;
            ProgressStatus.poll(progressToken, {
              update(id, current, target, data) {
                if (data.field) {
                  try {
                    dialogHolder.dialog(
                      'option', 'title',
                      t(appName, 'Updating receivables for {field}, {receivable}, {musician}',
                        { field: data.field || '', receivable: data.receivable || '', musician: data.musician || '' })
                    );
                  } catch (e) {
                    // don't care
                  }
                }
                if (current >= 0 && target > 0) {
                  progressWrapper.find('.progressbar .label').text(
                    t(appName, '{current} of {target}', { current, target }));
                }
                progressWrapper.find('.progressbar').progressbar('option', 'value', current / target * 100.0);
                return current < 0 || current !== target;
              },
              fail(xhr, status, errorThrown) { Ajax.handleError(xhr, status, errorThrown); },
              interval: 500,
            });
          },
          close() {
            progressOpen = false;
            ProgressStatus.poll.stop();
            ProgressStatus.delete(progressToken);
            progressWrapper.dialog('destroy');
            progressWrapper.hide();
          },
        });
        requestHandler(progressToken, function() {
          if (progressOpen) {
            try {
              progressWrapper.dialog('close');
            } catch (e) {
              // don't care
            }
          }
        });
      });
  };
  const handler = single
    ? function() { requestHandler(null, function() {}); }
    : handlerWithProgress;
  if (updateStrategy === 'replace') {
    Dialogs.confirm(
      t(appName, 'Update strategy "{updateStrategy}" replaces the value of existing receivables, please confirm that you want to continue.', { updateStrategy }),
      t(appName, 'Overwrite Existing Records?'),
      (answer) => (answer && handler()));
  } else {
    handler();
  }
};

const ready = function(selector, resizeCB) {
  const container = $(selector);

  const tableTab = container.find('select.tab');
  const newTab = container.find('input.new-tab');
  newTab.prop('readonly', !!tableTab.find(':selected').val());
  container.on('change', 'select.tab', function(event) {
    newTab.prop('readonly', !!tableTab.find(':selected').val());
    return false;
  });

  const setFieldTypeCssClass = function(data) {
    if (!data) {
      return;
    }
    console.info('FIELD TYPE CSS CLASS', data);
    const dataType = data.dataType;
    const multiplicity = data.multiplicity;
    const dueDate = data.depositDueDate;
    const multiplicityClass = 'multiplicity-' + multiplicity;
    const dataTypeClass = 'data-type-' + dataType;
    const depositDueDateClass = 'deposit-due-date-' + dueDate;
    container.find('tr.multiplicity')
      .removeClass(function(index, className) {
        return (className.match(/\b(multiplicity|data-type|deposit-due-date)-\S+/g) || []).join(' ');
      })
      .addClass([multiplicityClass, dataTypeClass, depositDueDateClass]);
    container.find('tr.data-options table.data-options')
      .removeClass(function(index, className) {
        return (className.match(/\b(multiplicity|data-type|deposit-due-date)-\S+/g) || []).join(' ');
      })
      .addClass([multiplicityClass, dataTypeClass]);

    container.find('[class*="-multiplicity-required"], [class*="-data-type-required"], [class*="-deposit-due-date-required"]').each(function(index) {
      const $this = $(this);
      $this.prop(
        'required',
        ($this.hasClass(multiplicity + '-multiplicity-required')
         || $this.hasClass(dataType + '-data-type-required')
         || $this.hasClass(dueDate + '-deposit-due-date-required')
         || $this.hasClass('multiplicity-' + multiplicity + '-'
                           + dueDate + '-deposit-due-date-required')
        )
          && !$this.hasClass('not-multiplicity-' + multiplicity + '-'
                             + dueDate + '-deposit-due-date-required')
      );
    });
    container.find('.data-type-html-disabled, .data-type-html-disabled *').prop('disabled', dataType === 'html');
    container.find('.not-data-type-html-disabled, .not-data-type-html-disabled *').prop('disabled', dataType !== 'html');

    container.find('.data-type-html-wysiwyg-editor').each(function() {
      const $this = $(this);
      WysiwygEditor.removeEditor($this);
      if (dataType === 'html') {
        WysiwygEditor.addEditor($this, resizeCB);
      }
    });

    const inputData = container.find('table.data-options').data('size');
    const $dataInputs = container
      .find(
        'tr.pme-row.' + 'data-options-' + multiplicity + ' td.pme-value'
          + ', '
          + 'tr.pme-row.data-options td.pme-value table.' + multiplicityClass + ' tr:not(.generator)')
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
    case 'service-fee':
      $dataInputs.attr('type', 'number');
      break;
    case 'date':
      $dataInputs
        .attr('type', 'text')
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
        .datetimepicker({
          step: 5,
        });
      break;
    default:
      $dataInputs.attr('type', 'text');
      break;
    }
  };

  const fieldTypeData = function() {
    const multiplicity = container.find('select.multiplicity');
    const dataType = container.find('select.data-type');
    const depositDueDate = container.find('input.deposit-due-date');
    if (multiplicity.length > 0 && dataType.length > 0) {
      const data = {
        multiplicity: multiplicity.val(),
        dataType: dataType.val(),
        depositDueDate: (dataType === 'service-fee' && depositDueDate.val() !== '') ? 'set' : 'unset',
      };
      return data;
    }
    const elem = container.find('td.pme-value.field-type .data');
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
    const allowedValuesTable = container.find('table.data-options');
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
  container.on(
    'change', [
      'select.multiplicity',
      'select.data-type',
      'input.deposit-due-date',
    ].join(), function(event) {

      console.info('FIELD TYPE CHANGE');

      const depositDueDateInput = container.find('input.deposit-due-date');
      const multiplicitySelect = container.find('select.multiplicity');
      const dataTypeSelect = container.find('select.data-type');
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
      const depositDueDate = (dataType === 'service-fee' && depositDueDateInput.val() !== '') ? 'set' : 'unset';
      console.info('DEPOSIT DUE DATE INPUT', depositDueDateInput, depositDueDateInput.val());
      setFieldTypeCssClass({ multiplicity, dataType, depositDueDate });
      allowedHeaderVisibility();
      console.debug('RESIZECB');
      resizeCB();

      $.fn.cafevTooltip.remove(); // remove left-overs

      return false;
    });
  container.find('select.multiplicity:not(.pme-filter)').trigger('change');

  container.on('keypress', 'tr.data-options input' + textInputSelector, function(event) {
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

  const $dataOptionsTable = container.find('table.data-options');
  container.on('change', '#data-options-show-deleted', function(event) {
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

  container.on('change', '#data-options-show-data', function(event) {
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

  container.on('change', 'select.default-multi-value', function(event) {
    const self = $(this);
    container.find('input.pme-input.default-single-value').val(self.find(':selected').val());
    return false;
  });

  container.on('blur', 'input.pme-input.default-single-value', function(event) {
    const self = $(this);
    const dfltSelect = container.find('select.default-multi-value');
    dfltSelect.children('option[value="' + self.val() + '"]').prop('selected', true);
    dfltSelect.trigger('chosen:updated');
    return false;
  });

  container.on('click', 'tr.data-options input.regenerate', function(event) {
    const $self = $(this);
    const $row = $self.closest('tr.data-options');
    const fieldId = pmeRec(container);
    const key = $row.find('input.field-key').val();
    const updateStrategy = $self.closest('table').find('select.recurring-receivables-update-strategy').val();
    const requestHandler = function(progressToken, progressCleanup) {
      const cleanup = function() {
        progressCleanup();
        $self.removeClass('busy');
      };
      const request = 'option/regenerate';
      $self.addClass('busy');
      return $.post(
        generateUrl('projects/participant-fields/' + request), {
          data: {
            fieldId,
            key,
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
          cleanup();
          Notification.messages(data.message);
        });
    };
    confirmedReceivablesUpdate(updateStrategy, requestHandler);
    return false;
  });

  container.on('click', 'tr.data-options input.regenerate-all', function(event) {
    const $self = $(this);
    const $row = $self.closest('tr.data-options');
    const fieldId = $row.data('fieldId');
    const updateStrategy = $row.find('select.recurring-receivables-update-strategy').val();
    const requestHandler = function(progressToken, progressCleanup) {
      const cleanup = function() {
        progressCleanup();
        $self.removeClass('busy');
      };
      const request = 'generator/regenerate';
      $self.addClass('busy');
      return $.post(
        generateUrl('projects/participant-fields/' + request), {
          data: {
            fieldId,
            updateStrategy,
            progressToken,
          },
        })
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown, cleanup);
        })
        .done(function(data) {
          if (!Ajax.validateResponse(data, ['fieldsAffected'], cleanup)) {
            return;
          }
          Notification.messages(data.message);
          cleanup();
        });
    };
    confirmedReceivablesUpdate(updateStrategy, requestHandler);
    return false;
  });

  container.on('click', 'tr.data-options input.generator-run', function(event) {
    const $self = $(this);
    const $row = $self.closest('tr.data-options');
    const fieldId = $row.data('fieldId');
    // defer submit until after validation.
    const submitDefer = PHPMyEdit.deferReload(container);
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
        lockGeneratedValues(container);
        startDate.val(data.startDate);
        resizeCB();
        cleanup();
        Notification.messages(data.message);
      });
    return false;
  });

  container.on('click', 'tr.data-options input.delete-undelete', function(event) {
    const $self = $(this);
    const $row = $self.closest('tr.data-options');
    const dfltSelect = container.find('select.default-multi-value');
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
  container.on(
    'blur',
    [
      'tr.multiplicity.data-type-service-fee ~ tr.data-options-single input' + textInputSelector,
      'tr.multiplicity.data-type-service-fee:not(.multiplicity-recurring) ~ tr.data-options tr.data-options:not(.generator) input.field-data' + textInputSelector,
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
      const submitDefer = PHPMyEdit.deferReload(container);
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
      if (!$this.hasClass('expert-mode-only') && !$this.hasClass('not-expert-mode-hidden')) {
        $this.lockUnlock({
          locked: $this.val().trim() !== '',
        });
      }
    });
  };

  const lockGeneratedValues = function(container) {
    // generated options
    const generatedSelector = 'tr.data-options table.multiplicity-recurring tr.data-line.data-options:not(.generator)';
    lockGeneratedValuesRow(container.find(generatedSelector));
  };

  lockGeneratedValues(container);

  // generator input
  const generatorSelector = 'tr.data-options table.multiplicity-recurring tr.data-line.generator input' + textInputSelector;
  const generator = container.find(generatorSelector);
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
  const dueDate = container.find('tr.multiplicity-recurring ~ tr.due-date td.pme-value input').not(nonTextInputSelector);
  if (dueDate.length > 0) {
    dueDate.lockUnlock({
      locked: dueDate.val().trim() !== '',
    });
  }

  container.on(
    'blur',
    generatorSelector + '.field-data',
    function(event) {
      const self = $(this);
      if (self.prop('readonly')) {
        return false;
      }
      const row = self.closest('tr.data-options');

      const request = 'generator/define';
      const data = $.extend({}, fieldTypeData(), row.data());
      const allowed = row.find(textElementSelector);
      const postData = $.param({ request, data })
            + '&' + allowed.serialize();

      // defer submit until after validation.
      const submitDefer = PHPMyEdit.deferReload(container);
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
            [/* check for required fields */],
            cleanup)) {
            return;
          }

          const empty = self.val().trim() === '';
          if (empty) {
            self.removeClass('readonly');
          } else {
            self.addClass('readonly');
          }
          self.lockUnlock('lock', !empty);
          row.find('.operation.generator-run').prop('disabled', empty);

          Notification.messages(data.message);

          cleanup();
        });
      return false;
    });

  // multi-field input matrix
  container.on(
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
      const dflt = container.find('select.default-multi-value');

      const request = 'option/define';
      const data = $.extend({ default: dflt.val() }, fieldTypeData(), $row.data());
      const allowed = $row.find(textElementSelector);
      const postData = $.param({ request, data })
            + '&' + allowed.serialize();

      // defer submit until after validation.
      const submitDefer = PHPMyEdit.deferReload(container);
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
  container.on('change', 'select.readers', function(event) {
    console.log('readers change');
    const self = $(this);

    let changed = false;
    const writers = container.find('select.writers');
    self.find('option').not(':selected').each(function() {
      const writer = writers.find('option[value="' + this.value + '"]');
      if (writer.prop('selected')) {
        writer.prop('selected', false);
        changed = true;
      }
    });
    if (changed) {
      writers.trigger('chosen:updated');
    }
    return false;
  });

  // When a writer-group is added, then add it to the
  // readers as well ;)
  container.on('change', 'select.writers', function(event) {
    console.log('writers change');
    const self = $(this);

    let changed = false;
    const readers = container.find('select.readers');
    self.find('option:selected').each(function() {
      const reader = readers.find('option[value="' + this.value + '"]');
      if (!reader.prop('selected')) {
        reader.prop('selected', true);
        changed = true;
      }
    });
    if (changed) {
      readers.trigger('chosen:updated');
    }
    return false;
  });

  const tableContainerId = PHPMyEdit.idSelector('table-container');

  // TODO: check whether these are still necessary
  container.on('chosen:showing_dropdown', tableContainerId + ' select', function(event) {
    console.log('chosen:showing_dropdown');
    // const widget = container.cafevDialog('widget');
    // const tableContainer = container.find(tableContainerId);
    // widget.css('overflow', 'visible');
    // container.css('overflow', 'visible');
    // tableContainer.css('overflow', 'visible');
    return true;
  });

  // TODO: check whether these are still necessary
  container.on('chosen:hiding_dropdown', tableContainerId + ' select', function(event) {
    console.log('chosen:hiding_dropdown');
    // const widget = container.cafevDialog('widget');
    // const tableContainer = container.find(tableContainerId);
    // tableContainer.css('overflow', '');
    // container.css('overflow', '');
    // widget.css('overflow', '');
    return true;
  });

  container.on('chosen:update', 'select.writers, select.readers', function(event) {
    resizeCB();
    return false;
  });

  setFieldTypeCssClass(fieldTypeData());

  allowedHeaderVisibility();

  // set autocomplete for generator selection
  const generatorRow = container.find('tr.data-options.generator');
  const generators = generatorRow.data('generators');
  generatorRow.find('input.field-data')
    .autocomplete({
      source: generators,
      position: { my: 'left bottom', at: 'left top', collision: 'none' },
      minLength: 0,
    })
    .on('focus', function() {
      $(this).autocomplete('search', '');
    });

  // synthesize resize events for textareas.
  textareaResize(container, 'textarea.field-tooltip, textarea.participant-field-tooltip, textarea.pme-input');
  resizeCB();
};

const documentReady = function() {

  CAFEVDB.addReadyCallback(function() {
    // const container = PHPMyEdit.container();
    // if (!container.hasClass('project-participant-fields')) {
    //   return; // not for us
    // }
    // ready(); // ????
  });

};

export {
  ready,
  documentReady,
  confirmedReceivablesUpdate,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
